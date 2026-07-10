<?php
declare(strict_types=1);

/**
 * Backup — copias de seguridad y restauración (PASO 11).
 * ------------------------------------------------------
 * TODO por PHP puro, sin mysqldump ni ZipArchive (no dependemos de herramientas
 * del hosting). Una copia es UN ÚNICO ARCHIVO JSON autocontenido:
 *
 *   {
 *     "manifest": { tipo, formato_version, esquema_version, fecha, recuentos:{...} },
 *     "datos":    { personas:[...], filiacion:[...], pareja:[...], usuarios:[...], ajustes:[...] },
 *     "fotos":    { "<archivo>.jpg": "<base64>", ... }
 *   }
 *
 * Incluye TODO el estado: personas ACTIVAS y en la PAPELERA (soft-delete), todos
 * los vínculos, ajustes, usuarios y las fotos. Restaurar reemplaza el estado
 * COMPLETO (todo o nada), dentro de una transacción con reversión si algo falla.
 *
 * Las copias se guardan en almacen/backups/ (fuera de public/, no accesible por
 * web, gitignored). Acceso: solo rol edición (lo aplican los endpoints).
 */
require_once __DIR__ . '/Fechas.php';   // autoridad del calendario (INT-08: valida fechas de la copia)

final class Backup
{
    public const FORMATO          = 'arbol-genealogico-backup';
    public const FORMATO_VERSION  = 1;
    public const ESQUEMA_VERSION  = '1';          // versión del esquema de la BD
    private const RETENCION        = 5;            // nº máximo de copias guardadas
    private const MAX_BYTES_SUBIDA = 64 * 1024 * 1024;   // 64 MB máx. al restaurar desde archivo
    private const PATRON_NOMBRE    = '/^arbol-backup-[0-9]{8}-[0-9]{6}(-[a-z0-9]+)*\.json$/';
    // El patrón del nombre de foto vive en Fotos::PATRON (única fuente, PHP-3).

    // .htaccess de defensa en profundidad de almacen/fotos/ (deny web + engine off).
    // El swap de carpeta al restaurar lo perdería; se repone para no degradar la defensa.
    private const HTACCESS_FOTOS =
        "# Defensa en profundidad para la carpeta de fotos (repuesto por Backup::restaurar).\n" .
        "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n" .
        "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n" .
        "<IfModule mod_php.c>\n    php_admin_flag engine off\n</IfModule>\n" .
        "<IfModule mod_php7.c>\n    php_admin_flag engine off\n</IfModule>\n" .
        "<IfModule mod_php8.c>\n    php_admin_flag engine off\n</IfModule>\n" .
        "RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .pht .phar\n" .
        "AddType text/plain .php .phtml .phar\n";

    // Tablas incluidas en la copia (en el orden en que se insertan al restaurar;
    // con las claves foráneas desactivadas el orden no es crítico, pero arboles y
    // personas primero es lo natural). arb_arboles entra desde el PASO 12 para que
    // la restauración sea SIEMPRE COMPLETA (incluye la preparación multi-árbol).
    private const TABLAS = ['arb_arboles', 'arb_personas', 'arb_filiacion', 'arb_pareja', 'arb_usuarios', 'arb_ajustes'];
    // Nombre corto en el JSON ↔ tabla real.
    private const MAPA = [
        'arboles'   => 'arb_arboles',
        'personas'  => 'arb_personas',
        'filiacion' => 'arb_filiacion',
        'pareja'    => 'arb_pareja',
        'usuarios'  => 'arb_usuarios',
        'ajustes'   => 'arb_ajustes',
    ];

    // LISTA BLANCA de columnas por tabla (SEC-03). Los nombres de columna del INSERT
    // salen SIEMPRE de aquí (constantes del código), NUNCA de las claves del JSON:
    // así una copia manipulada no puede inyectar SQL por el nombre de columna. Las
    // claves extra del JSON se ignoran; los valores van por marcador (preparado).
    private const COLUMNAS = [
        'arboles'   => ['id', 'nombre', 'creado_en'],
        'personas'  => ['id', 'nombre', 'apellido1', 'apellido2', 'sexo', 'nacimiento', 'fallecimiento', 'lugar', 'ocupacion', 'notas', 'avatar', 'creado_en', 'actualizado_en', 'borrado_en'],
        'filiacion' => ['id', 'progenitor_id', 'hijo_id'],
        'pareja'    => ['id', 'persona_a_id', 'persona_b_id'],
        'usuarios'  => ['id', 'persona_id', 'rol', 'password_hash', 'activo', 'creado_en'],
        'ajustes'   => ['clave', 'valor'],
    ];

    // Ajustes PROTEGIDOS (SEC-03): credenciales, interruptor de acceso y marcas de
    // instalación. NUNCA se restauran desde una copia (podría ser ajena): se
    // PRESERVAN los del árbol ACTUAL. Un backup trae datos y ajustes de pantalla,
    // no cambia quién es el administrador ni abre el árbol.
    private const AJUSTES_PROTEGIDOS = ['acceso_activo', 'clave_edicion_hash', 'clave_lectura_hash', 'instalado', 'version_esquema'];

    // ── Rutas ────────────────────────────────────────────────────────────────
    public static function carpeta(): string { return RAIZ . '/almacen/backups'; }

    private static function asegurarCarpeta(): void
    {
        $dir = self::carpeta();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No existe la carpeta de copias y no se pudo crear: ' . $dir);
        }
        if (!is_writable($dir)) {
            throw new RuntimeException('La carpeta de copias no tiene permisos de escritura: ' . $dir);
        }
    }

    /** Ruta SEGURA de una copia por su nombre (evita traversal; solo nuestros nombres). */
    public static function ruta(string $nombre): ?string
    {
        $nombre = basename($nombre);
        if (!preg_match(self::PATRON_NOMBRE, $nombre)) return null;
        return self::carpeta() . '/' . $nombre;
    }

    private static function metaRuta(string $nombre): ?string
    {
        $r = self::ruta($nombre);
        return $r ? $r . '.meta' : null;   // sidecar pequeño con el manifest (listado rápido)
    }

    // ── Construir el contenido de una copia (array) ──────────────────────────
    public static function construir(PDO $pdo): array
    {
        $datos = [];
        foreach (self::MAPA as $corto => $tabla) {
            $datos[$corto] = $pdo->query('SELECT * FROM ' . $tabla)->fetchAll(PDO::FETCH_ASSOC);
        }

        // Fotos: todos los archivos válidos de almacen/fotos/, en base64.
        $fotos = [];
        $dirFotos = Fotos::carpeta();
        if (is_dir($dirFotos)) {
            foreach (scandir($dirFotos) ?: [] as $f) {
                if (!preg_match(Fotos::PATRON, $f)) continue;
                $bin = @file_get_contents($dirFotos . '/' . $f);
                if ($bin !== false) $fotos[$f] = base64_encode($bin);
            }
        }

        $recuentos = [];
        foreach ($datos as $corto => $filas) $recuentos[$corto] = count($filas);
        $recuentos['fotos'] = count($fotos);

        $manifest = [
            'tipo'            => self::FORMATO,
            'formato_version' => self::FORMATO_VERSION,
            'esquema_version' => self::ESQUEMA_VERSION,
            'fecha'           => date('Y-m-d H:i:s'),
            'app'             => 'Árbol genealógico',
            'recuentos'       => $recuentos,
        ];

        return ['manifest' => $manifest, 'datos' => $datos, 'fotos' => $fotos];
    }

    // ── Generar y GUARDAR una copia en el servidor (con retención) ───────────
    // $sufijo: etiqueta opcional (p.ej. 'previo' para el backup automático antes
    // de restaurar). Devuelve ['archivo'=>nombre, 'manifest'=>...].
    public static function generar(PDO $pdo, string $sufijo = ''): array
    {
        self::asegurarCarpeta();
        $copia = self::construir($pdo);

        $suf = $sufijo !== '' ? '-' . preg_replace('/[^a-z0-9]/', '', strtolower($sufijo)) : '';
        $base = 'arbol-backup-' . date('Ymd-His') . $suf;
        $nombre = $base . '.json';
        // Evitar colisión si se generan varias en el mismo segundo: token aleatorio.
        while (is_file(self::carpeta() . '/' . $nombre)) {
            $nombre = $base . '-' . substr(bin2hex(random_bytes(2)), 0, 3) . '.json';
        }
        $ruta = self::carpeta() . '/' . $nombre;

        $json = json_encode($copia, JSON_UNESCAPED_UNICODE);
        if ($json === false) throw new RuntimeException('No se pudo serializar la copia: ' . json_last_error_msg());
        if (@file_put_contents($ruta, $json) === false) {
            throw new RuntimeException('No se pudo escribir la copia en el servidor.');
        }
        // Sidecar con solo el manifest, para listar rápido sin leer el archivo entero.
        @file_put_contents($ruta . '.meta', json_encode($copia['manifest'], JSON_UNESCAPED_UNICODE));

        self::aplicarRetencion();
        return ['archivo' => $nombre, 'manifest' => $copia['manifest']];
    }

    /** Conserva solo las RETENCION copias más recientes; borra las más viejas (y sus sidecars). */
    private static function aplicarRetencion(): void
    {
        $lista = self::listar();                       // ya viene ordenada (más nueva primero)
        for ($i = self::RETENCION; $i < count($lista); $i++) {
            $r = self::ruta($lista[$i]['archivo']);
            if ($r && is_file($r)) @unlink($r);
            $m = self::metaRuta($lista[$i]['archivo']);
            if ($m && is_file($m)) @unlink($m);
        }
    }

    /** Lista las copias guardadas (más reciente primero): archivo, fecha, tamaño, recuentos. */
    public static function listar(): array
    {
        $dir = self::carpeta();
        if (!is_dir($dir)) return [];
        $out = [];
        foreach (scandir($dir) ?: [] as $f) {
            if (!preg_match(self::PATRON_NOMBRE, $f)) continue;
            $ruta = $dir . '/' . $f;
            $manifest = null;
            $meta = $ruta . '.meta';
            if (is_file($meta)) { $manifest = json_decode((string) @file_get_contents($meta), true); }
            $out[] = [
                'archivo'   => $f,
                'fecha'     => $manifest['fecha'] ?? date('Y-m-d H:i:s', filemtime($ruta) ?: time()),
                'bytes'     => filesize($ruta) ?: 0,
                'recuentos' => $manifest['recuentos'] ?? null,
                'mtime'     => filemtime($ruta) ?: 0,
            ];
        }
        usort($out, static fn($a, $b) => $b['mtime'] <=> $a['mtime']);   // más nueva primero
        return $out;
    }

    /** Elimina una copia guardada (y su sidecar). */
    public static function eliminar(string $nombre): void
    {
        $ruta = self::ruta($nombre);
        if (!$ruta || !is_file($ruta)) throw new InvalidArgumentException('Esa copia no existe.');
        @unlink($ruta);
        $m = self::metaRuta($nombre);
        if ($m && is_file($m)) @unlink($m);
    }

    // ── Validación de una copia (antes de restaurar) ─────────────────────────
    // Recibe el array decodificado. Lanza InvalidArgumentException si no es válida
    // o incompatible. Devuelve el manifest si es correcta.
    public static function validar(array $copia): array
    {
        $man = $copia['manifest'] ?? null;
        if (!is_array($man) || ($man['tipo'] ?? '') !== self::FORMATO) {
            throw new InvalidArgumentException('El archivo no es una copia de seguridad de este árbol.');
        }
        if ((int) ($man['formato_version'] ?? 0) > self::FORMATO_VERSION) {
            throw new InvalidArgumentException('La copia es de una versión más nueva de la aplicación.');
        }
        if ((string) ($man['esquema_version'] ?? '') !== self::ESQUEMA_VERSION) {
            throw new InvalidArgumentException('La copia corresponde a otra versión del esquema de datos (incompatible).');
        }
        $datos = $copia['datos'] ?? null;
        if (!is_array($datos)) throw new InvalidArgumentException('La copia no contiene datos.');
        foreach (array_keys(self::MAPA) as $corto) {
            if (!isset($datos[$corto]) || !is_array($datos[$corto])) {
                throw new InvalidArgumentException('La copia está incompleta (falta "' . $corto . '").');
            }
        }
        if (!isset($copia['fotos']) || !is_array($copia['fotos'])) {
            throw new InvalidArgumentException('La copia no contiene la sección de fotos.');
        }
        // Integridad: los recuentos del manifest deben cuadrar con el contenido real.
        $rec = $man['recuentos'] ?? [];
        foreach (array_keys(self::MAPA) as $corto) {
            if (isset($rec[$corto]) && (int) $rec[$corto] !== count($datos[$corto])) {
                throw new InvalidArgumentException('La copia está dañada (recuento de "' . $corto . '" no coincide).');
            }
        }
        if (isset($rec['fotos']) && (int) $rec['fotos'] !== count($copia['fotos'])) {
            throw new InvalidArgumentException('La copia está dañada (recuento de fotos no coincide).');
        }
        return $man;
    }

    /** Decodifica y valida un contenido de copia (cadena JSON). Devuelve el array de la copia. */
    public static function desdeCadena(string $json): array
    {
        if (strlen($json) > self::MAX_BYTES_SUBIDA) {
            throw new InvalidArgumentException('El archivo es demasiado grande.');
        }
        $copia = json_decode($json, true);
        if (!is_array($copia)) throw new InvalidArgumentException('El archivo no es una copia válida (JSON incorrecto).');
        self::validar($copia);
        return $copia;
    }

    // ── RESTAURACIÓN (destructiva; reemplaza TODO el estado) ─────────────────
    // Reemplaza la BD (en TRANSACCIÓN, con reversión si algo falla → árbol intacto)
    // y las fotos (por carpeta temporal + swap). Quien llama debe haber generado
    // ANTES la copia automática de seguridad del estado actual.
    public static function restaurar(PDO $pdo, array $copia): void
    {
        self::validar($copia);
        // Integridad referencial + tipos (SEC-03 / INT-08): rechaza copias con
        // relaciones huérfanas o ids basura ANTES de tocar nada.
        self::validarIntegridad($copia['datos']);
        $datos = $copia['datos'];
        $fotos = $copia['fotos'];

        // 1) Preparar las fotos en una carpeta TEMPORAL (antes de tocar la BD, para
        //    que si esto falla no se haya cambiado nada).
        $dirFotos = Fotos::carpeta();
        $baseAlmacen = dirname($dirFotos);
        $tmp = $baseAlmacen . '/fotos.tmp_restore_' . bin2hex(random_bytes(4));
        if (!@mkdir($tmp, 0775, true)) throw new RuntimeException('No se pudo preparar la carpeta temporal de fotos.');
        try {
            foreach ($fotos as $nombre => $b64) {
                if (!preg_match(Fotos::PATRON, (string) $nombre)) continue;   // ignora nombres raros
                $bin = base64_decode((string) $b64, true);
                if ($bin === false) throw new InvalidArgumentException('Una foto de la copia está dañada.');
                if (@file_put_contents($tmp . '/' . $nombre, $bin) === false) {
                    throw new RuntimeException('No se pudo escribir una foto al restaurar.');
                }
            }
            @file_put_contents($tmp . '/.gitkeep', '');
            // Conservar el .htaccess de defensa en profundidad de la carpeta de fotos:
            // NO viaja en la copia (es del repo), pero el swap de carpeta lo perdería en
            // cada restauración. Se copia el actual si existe; si no, se repone el canónico.
            $htOrigen = $dirFotos . '/.htaccess';
            if (is_file($htOrigen)) {
                @copy($htOrigen, $tmp . '/.htaccess');
            } else {
                @file_put_contents($tmp . '/.htaccess', self::HTACCESS_FOTOS);
            }
        } catch (Throwable $e) {
            self::borrarDir($tmp);
            throw $e;
        }

        // 2) Restaurar la BD en TRANSACCIÓN. Usamos DELETE (no TRUNCATE) a propósito:
        //    TRUNCATE hace commit implícito y no se podría revertir; DELETE es DML y
        //    SÍ entra en la transacción, así que un fallo revierte y deja el árbol intacto.
        try {
            $pdo->beginTransaction();
            // PRESERVAR credenciales/interruptor/marcas del árbol ACTUAL (SEC-03):
            // se capturan ANTES de borrar y se reponen; el backup no los cambia.
            $protegidos = self::leerAjustesProtegidos($pdo);
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach (self::TABLAS as $t) $pdo->exec('DELETE FROM ' . $t);
            foreach (self::MAPA as $corto => $tabla) {
                $filas = $datos[$corto];
                if ($corto === 'usuarios') {
                    // Un backup NUNCA inyecta credenciales de login: hash neutralizado.
                    $filas = array_map(static function ($f) { $f['password_hash'] = null; return $f; }, $filas);
                } elseif ($corto === 'ajustes') {
                    // Ajustes NO protegidos del backup + los PROTEGIDOS actuales (preservados).
                    $filas = array_values(array_filter($filas, static fn($r) => !in_array($r['clave'] ?? '', self::AJUSTES_PROTEGIDOS, true)));
                    $filas = array_merge($filas, $protegidos);
                }
                self::insertarFilas($pdo, $tabla, $filas, self::COLUMNAS[$corto]);
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            try { $pdo->exec('SET FOREIGN_KEY_CHECKS = 1'); } catch (Throwable $x) {}
            self::borrarDir($tmp);                          // limpiar fotos temporales
            throw $e;                                       // la BD queda EXACTAMENTE como estaba
        }

        // 3) La BD ya está restaurada y confirmada. Ahora el SWAP de fotos (rápido).
        $viejas = $baseAlmacen . '/fotos.old_' . bin2hex(random_bytes(4));
        if (is_dir($dirFotos)) {
            if (!@rename($dirFotos, $viejas)) { self::borrarDir($tmp); throw new RuntimeException('No se pudieron reemplazar las fotos (rename 1).'); }
        }
        if (!@rename($tmp, $dirFotos)) {
            // Intentar dejar las fotos anteriores en su sitio.
            @rename($viejas, $dirFotos);
            self::borrarDir($tmp);
            throw new RuntimeException('No se pudieron reemplazar las fotos (rename 2).');
        }
        self::borrarDir($viejas);                           // borrar las fotos antiguas
    }

    /**
     * INSERT de filas usando SOLO columnas de la LISTA BLANCA (SEC-03). Los nombres
     * de columna son constantes del código; las claves extra del JSON se ignoran;
     * los valores van por marcador. Imposible inyectar SQL por el nombre de columna.
     */
    private static function insertarFilas(PDO $pdo, string $tabla, array $filas, array $columnas): void
    {
        if (!$filas) return;
        $placeholders = implode(',', array_fill(0, count($columnas), '?'));
        $sql = 'INSERT INTO ' . $tabla . ' (`' . implode('`,`', $columnas) . '`) VALUES (' . $placeholders . ')';
        $st = $pdo->prepare($sql);
        foreach ($filas as $fila) {
            $st->execute(array_map(static fn($c) => $fila[$c] ?? null, $columnas));
        }
    }

    /**
     * Valida la INTEGRIDAD REFERENCIAL y de tipos de una copia antes de restaurar
     * (SEC-03 / INT-08): ids de persona enteros y positivos, y toda filiación/pareja
     * apuntando a personas presentes en la copia. Evita relaciones huérfanas o ids
     * basura que la restauración con FKs desactivadas no detectaría.
     */
    private static function validarIntegridad(array $datos): void
    {
        $idEntero = static function ($v): ?int {
            if (is_int($v)) return $v > 0 ? $v : null;
            if (is_string($v) && ctype_digit($v)) { $n = (int) $v; return $n > 0 ? $n : null; }
            return null;
        };
        $ids = [];
        foreach ($datos['personas'] as $p) {
            $id = $idEntero($p['id'] ?? null);
            if ($id === null) throw new InvalidArgumentException('La copia contiene una persona con id inválido.');
            $ids[$id] = true;

            // TIPOS (INT-08): sexo debe ser el ENUM del esquema; fechas de calendario
            // real; y el fallecimiento no anterior al nacimiento. Rechaza basura que
            // el INSERT con FOREIGN_KEY_CHECKS=0 no detectaría.
            $sexo = $p['sexo'] ?? null;
            if (!($sexo === null || $sexo === '' || $sexo === 'M' || $sexo === 'F')) {
                throw new InvalidArgumentException('La copia contiene un sexo inválido (solo M, F o vacío).');
            }
            $nac  = (string) ($p['nacimiento'] ?? '');
            $fall = (string) ($p['fallecimiento'] ?? '');
            if (!Fechas::esFechaCalendario($nac) || !Fechas::esFechaCalendario($fall)) {
                throw new InvalidArgumentException('La copia contiene una fecha imposible (no es una fecha de calendario válida).');
            }
            if ($nac !== '' && $fall !== '') {
                $ambasExactas = preg_match('/^\d{4}-\d{2}-\d{2}$/', $nac) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fall);
                $imposible = $ambasExactas ? ($fall < $nac) : ((int) substr($fall, 0, 4) < (int) substr($nac, 0, 4));
                if ($imposible) throw new InvalidArgumentException('La copia contiene una persona que fallece antes de nacer.');
            }
        }
        foreach (['filiacion' => ['progenitor_id', 'hijo_id'], 'pareja' => ['persona_a_id', 'persona_b_id']] as $tabla => $refs) {
            foreach ($datos[$tabla] as $fila) {
                $vals = [];
                foreach ($refs as $c) {
                    $ref = $idEntero($fila[$c] ?? null);
                    if ($ref === null || !isset($ids[$ref])) {
                        throw new InvalidArgumentException('La copia está dañada: una relación (' . $tabla . ') apunta a una persona inexistente.');
                    }
                    $vals[] = $ref;
                }
                // Sin auto-referencias: nadie es su propio progenitor ni su propia pareja.
                if ($vals[0] === $vals[1]) {
                    throw new InvalidArgumentException('La copia contiene una relación (' . $tabla . ') de una persona consigo misma.');
                }
            }
        }
        // Rol de usuarios: solo el ENUM del esquema.
        foreach ($datos['usuarios'] ?? [] as $u) {
            $rol = $u['rol'] ?? 'lectura';
            if (!in_array($rol, ['lectura', 'edicion'], true)) {
                throw new InvalidArgumentException('La copia contiene un usuario con rol inválido.');
            }
        }
    }

    /** Lee los ajustes PROTEGIDOS actuales (para preservarlos al restaurar). */
    private static function leerAjustesProtegidos(PDO $pdo): array
    {
        $marc = implode(',', array_fill(0, count(self::AJUSTES_PROTEGIDOS), '?'));
        $st = $pdo->prepare('SELECT clave, valor FROM arb_ajustes WHERE clave IN (' . $marc . ')');
        $st->execute(self::AJUSTES_PROTEGIDOS);
        return $st->fetchAll();
    }

    /** Borra recursivamente una carpeta (para las temporales de fotos). */
    private static function borrarDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') continue;
            $p = $dir . '/' . $f;
            is_dir($p) ? self::borrarDir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
