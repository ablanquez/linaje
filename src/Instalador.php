<?php
declare(strict_types=1);

/**
 * Instalador — motor del asistente de instalación (PASO 12).
 * ---------------------------------------------------------
 * Toda la LÓGICA de instalar vive aquí, separada de la interfaz (public/instalar/).
 * Piezas modulares y reutilizables (también por el futuro panel):
 *   · requisitos()        — comprueba el entorno (PHP, extensiones, permisos).
 *   · probarConexion()    — abre PDO con los datos del formulario, sin guardar.
 *   · escribirConfig()    — escribe config/config.php (o lo devuelve para pegar).
 *   · crearEstructura()   — ejecuta db/esquema.sql (idempotente).
 *   · finalizar()         — en TRANSACCIÓN: primera persona + ajustes + acceso +
 *                           árbol id=1 + versión + cerrojo (instalado=1).
 *   · estaInstalado()     — CERROJO en tiempo de ejecución (config + BD + marca).
 *   · estado()            — para reanudar una instalación a medias.
 *
 * NO depende de bootstrap.php (corre ANTES de que exista config.php). Abre sus
 * propias conexiones PDO para no arrastrar configuración cacheada.
 *
 * Las credenciales de la BD se guardan en config.php (como wp-config.php). Las
 * claves de acceso NO: se guardan HASHEADAS en la BD (vía Acceso).
 */

require_once __DIR__ . '/Personas.php';
require_once __DIR__ . '/Ajustes.php';
require_once __DIR__ . '/Acceso.php';
require_once __DIR__ . '/Arbol.php';

final class Instalador
{
    /** Versión del esquema que instala esta versión de la app (para migraciones futuras). */
    public const VERSION_ESQUEMA = '1';

    // ── Rutas (self-contenidas; no dependen de bootstrap) ────────────────────
    private static function raiz(): string { return defined('RAIZ') ? RAIZ : dirname(__DIR__); }
    public static function rutaConfig(): string { return self::raiz() . '/config/config.php'; }
    private static function rutaEsquema(): string { return self::raiz() . '/db/esquema.sql'; }

    // ── Requisitos del entorno ───────────────────────────────────────────────
    // Devuelve una lista de comprobaciones {ok, critico, titulo, detalle}.
    // Solo las 'critico' bloquean la instalación (PHP y pdo_mysql). El resto son
    // avisos (config/ no escribible → pegado manual; gd → recomendable).
    public static function requisitos(): array
    {
        $r = [];

        $php = PHP_VERSION_ID >= 80000;
        $r[] = ['ok' => $php, 'critico' => true, 'titulo' => 'Versión de PHP',
                'detalle' => 'PHP ' . PHP_VERSION . ($php ? ' (≥ 8.0)' : ' — se requiere 8.0 o superior')];

        $pdo = extension_loaded('pdo_mysql');
        $r[] = ['ok' => $pdo, 'critico' => true, 'titulo' => 'Extensión pdo_mysql',
                'detalle' => $pdo ? 'Cargada' : 'NO cargada — actívala en php.ini (necesaria para la BD)'];

        $gd = extension_loaded('gd');
        $r[] = ['ok' => $gd, 'critico' => false, 'titulo' => 'Extensión gd (fotos)',
                'detalle' => $gd ? 'Cargada' : 'No cargada — recomendable para redimensionar fotos'];

        $rutaConfig = self::rutaConfig();
        $dirConfig  = dirname($rutaConfig);
        $cfgW = is_file($rutaConfig) ? is_writable($rutaConfig) : is_writable($dirConfig);
        $r[] = ['ok' => $cfgW, 'critico' => false, 'titulo' => 'Carpeta config/ escribible',
                'detalle' => $cfgW ? 'Se guardará config/config.php automáticamente'
                                   : 'No escribible — te daremos el archivo para pegarlo a mano'];

        $dirAlmacen = self::raiz() . '/almacen';
        $almW = is_dir($dirAlmacen) ? is_writable($dirAlmacen) : is_writable(self::raiz());
        $r[] = ['ok' => $almW, 'critico' => false, 'titulo' => 'Carpeta almacen/ escribible',
                'detalle' => $almW ? 'Fotos y copias de seguridad podrán guardarse'
                                   : 'No escribible — revisa los permisos de almacen/ (fotos y copias)'];

        return $r;
    }

    /** ¿Hay algún requisito CRÍTICO sin cumplir? (bloquea la instalación). */
    public static function requisitosBloquean(array $reqs): bool
    {
        foreach ($reqs as $c) {
            if (!empty($c['critico']) && empty($c['ok'])) return true;
        }
        return false;
    }

    // ── Conexión y prueba ────────────────────────────────────────────────────

    /** Abre una conexión PDO con los datos dados (lanza si falla). Prefijo fijo arb_. */
    public static function conectar(array $db): PDO
    {
        $host    = trim((string) ($db['host'] ?? ''));
        $puerto  = (int) ($db['puerto'] ?? 3306);
        $nombre  = trim((string) ($db['nombre'] ?? ''));
        $usuario = (string) ($db['usuario'] ?? '');
        $clave   = (string) ($db['clave'] ?? '');

        if ($host === '' || $nombre === '' || $usuario === '') {
            throw new InvalidArgumentException('Faltan datos: host, base de datos y usuario son obligatorios.');
        }
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $puerto, $nombre);
        return new PDO($dsn, $usuario, $clave, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    /** Prueba la conexión sin guardar nada. Devuelve {ok, version?, bd?} o {ok:false, error}. */
    public static function probarConexion(array $db): array
    {
        try {
            $pdo = self::conectar($db);
            $ver = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            $bd  = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
            return ['ok' => true, 'version' => $ver, 'bd' => $bd];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ── config.php (credenciales de la BD) ───────────────────────────────────

    /**
     * Genera el CONTENIDO de config.php a partir de los datos de conexión. Si
     * $instalado es true, incluye la marca `'instalado' => true`: un flag que, POR
     * SÍ SOLO y SIN necesidad de la BD, cierra el instalador (arreglo SEC-01: una
     * caída transitoria de la BD ya no reabre la instalación).
     */
    public static function generarContenidoConfig(array $db, bool $instalado = false): string
    {
        $host    = (string) ($db['host'] ?? 'localhost');
        $puerto  = (int) ($db['puerto'] ?? 3306);
        $nombre  = (string) ($db['nombre'] ?? '');
        $usuario = (string) ($db['usuario'] ?? '');
        $clave   = (string) ($db['clave'] ?? '');
        $e = static fn($s) => var_export($s, true);   // escape seguro de cadenas
        $marca = $instalado ? "    'instalado' => true,   // instalación completada (cierra el instalador sin depender de la BD)\n" : '';

        return "<?php\n"
            . "/**\n"
            . " * CONFIGURACIÓN REAL — generada por el instalador.\n"
            . " * ------------------------------------------------\n"
            . " * Contiene credenciales de la BD: NO subir a GitHub (está en .gitignore).\n"
            . " * Las CLAVES de acceso NO van aquí: viven hasheadas en la BD (arb_ajustes),\n"
            . " * y se cambian desde el panel. Este archivo solo dice 'cómo conecto a la BD'.\n"
            . " */\n\n"
            . "return [\n"
            . $marca
            . "    'db' => [\n"
            . "        'host'    => {$e($host)},\n"
            . "        'puerto'  => {$puerto},\n"
            . "        'nombre'  => {$e($nombre)},\n"
            . "        'usuario' => {$e($usuario)},\n"
            . "        'clave'   => {$e($clave)},\n"
            . "        'charset' => 'utf8mb4',\n"
            . "    ],\n"
            . "];\n";
    }

    /**
     * Escribe config/config.php. Si la carpeta no es escribible, devuelve el
     * contenido para que el usuario lo pegue a mano (fallback típico en hosting).
     * Devuelve {escrito:bool, contenido:string, error?:string}.
     */
    public static function escribirConfig(array $db): array
    {
        $ruta = self::rutaConfig();
        // DEFENSA EN PROFUNDIDAD (SEC-01): NUNCA sobrescribir una configuración ya
        // instalada. Para reinstalar hay que quitar config/config.php a mano (como
        // wp-config.php). Junto con el cerrojo DB-independiente, cierra la primitiva
        // de "repuntar la app a otra BD" durante una caída transitoria de la BD.
        if (is_file($ruta)) {
            try {
                $actual = require $ruta;
                if (is_array($actual) && !empty($actual['instalado'])) {
                    return ['escrito' => false, 'contenido' => '',
                            'error' => 'Ya existe una instalación (config/config.php). Para reinstalar, quítalo a mano primero.'];
                }
            } catch (Throwable $e) { /* config ilegible: se intenta reescribir abajo */ }
        }
        $contenido = self::generarContenidoConfig($db);
        $dir  = dirname($ruta);
        $puede = is_file($ruta) ? is_writable($ruta) : is_writable($dir);
        if ($puede && @file_put_contents($ruta, $contenido) !== false) {
            @chmod($ruta, 0600);   // SEC-18: solo el propietario puede leer las credenciales de BD.
            return ['escrito' => true, 'contenido' => $contenido];
        }
        return ['escrito' => false, 'contenido' => $contenido,
                'error' => 'No se pudo escribir config/config.php automáticamente; pégalo a mano.'];
    }

    // ── Estado / cerrojo ─────────────────────────────────────────────────────

    public static function configExiste(): bool
    {
        return is_file(self::rutaConfig());
    }

    /** Devuelve una conexión PDO usando config.php, o null si no se puede. */
    public static function pdoDesdeConfig(): ?PDO
    {
        if (!self::configExiste()) return null;
        try {
            $config = require self::rutaConfig();
            if (!is_array($config) || !isset($config['db'])) return null;
            return self::conectar($config['db']);
        } catch (Throwable $e) {
            return null;
        }
    }

    /** ¿Están creadas las 6 tablas del esquema? */
    public static function tablasInstaladas(PDO $pdo): bool
    {
        try {
            $req = ['arb_personas', 'arb_filiacion', 'arb_pareja', 'arb_usuarios', 'arb_ajustes', 'arb_arboles'];
            $marcadores = implode(',', array_fill(0, count($req), '?'));
            $st = $pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name IN ($marcadores)"
            );
            $st->execute($req);
            return (int) $st->fetchColumn() === count($req);
        } catch (Throwable $e) {
            return false;
        }
    }

    /** ¿Está puesta la marca de instalación (arb_ajustes.instalado = '1')? */
    public static function marcaInstalado(PDO $pdo): bool
    {
        try {
            $v = $pdo->query("SELECT valor FROM arb_ajustes WHERE clave = 'instalado'")->fetchColumn();
            return $v === '1';
        } catch (Throwable $e) {
            return false;   // la tabla puede no existir aún
        }
    }

    /**
     * CERROJO anti-reinstalación (en tiempo de EJECUCIÓN, no por borrar archivos).
     * Instalado = config.php presente Y BD alcanzable Y marca instalado=1.
     * A prueba de fallos: cualquier problema → false (permite (re)instalar).
     */
    public static function estaInstalado(): bool
    {
        if (!self::configExiste()) return false;
        // 1) Marca INDEPENDIENTE de la BD: flag `instalado` en config.php. Basta por
        //    sí sola para cerrar el instalador (arreglo SEC-01: una caída transitoria
        //    de la BD ya NO reabre la instalación). Es el camino normal.
        try {
            $config = require self::rutaConfig();
            if (is_array($config) && !empty($config['instalado'])) return true;
        } catch (Throwable $e) { /* config ilegible: se prueba el respaldo */ }
        // 2) RESPALDO: config + BD alcanzable + marca en BD. Solo cuenta para el caso
        //    raro de config pegado a mano sin el flag; con la BD arriba (estado
        //    normal) el árbol queda cerrado igualmente.
        try {
            $pdo = self::pdoDesdeConfig();
            return $pdo ? self::marcaInstalado($pdo) : false;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Graba en config.php la marca `'instalado' => true` (SEC-01). Se llama al
     * terminar la instalación. Reescribe el archivo conservando los datos de la BD.
     * Si config.php no es escribible (pegado a mano), no falla: el cerrojo usará el
     * respaldo por BD. No pasa por escribirConfig() (que se niega a sobrescribir).
     */
    private static function marcarInstaladoEnConfig(): void
    {
        $ruta = self::rutaConfig();
        if (!is_file($ruta) || !is_writable($ruta)) return;
        try {
            $config = require $ruta;
            if (!is_array($config) || !isset($config['db']) || !is_array($config['db'])) return;
            @file_put_contents($ruta, self::generarContenidoConfig($config['db'], true));
            @chmod($ruta, 0600);   // SEC-18: mantener permisos restrictivos tras reescribir.
        } catch (Throwable $e) { /* el respaldo por BD cubre este caso */ }
    }

    /** Estado para reanudar una instalación a medias: {config, conecta, tablas, instalado}. */
    public static function estado(): array
    {
        $config = self::configExiste();
        $conecta = false; $tablas = false; $instalado = false;
        if ($config) {
            $pdo = self::pdoDesdeConfig();
            if ($pdo) {
                $conecta = true;
                $tablas = self::tablasInstaladas($pdo);
                if ($tablas) $instalado = self::marcaInstalado($pdo);
            }
        }
        return ['config' => $config, 'conecta' => $conecta, 'tablas' => $tablas, 'instalado' => $instalado];
    }

    // ── Crear estructura (ejecutar el esquema) ───────────────────────────────
    public static function crearEstructura(PDO $pdo): void
    {
        $sql = @file_get_contents(self::rutaEsquema());
        if ($sql === false) throw new RuntimeException('No se encontró db/esquema.sql.');
        foreach (self::sentenciasSql($sql) as $sent) {
            $pdo->exec($sent);
        }
    }

    /**
     * Parte un script SQL en sentencias ejecutables, RESPETANDO las cadenas y los
     * identificadores. Es un escáner carácter a carácter (no un explode ingenuo):
     * varios COMMENT del esquema llevan ';' DENTRO de la cadena (p.ej. "…único;
     * lo asigna…"), así que separar por ';' a lo bruto rompía la sentencia.
     * Ignora comentarios de bloque, de línea (-- …) y de almohadilla (# …).
     */
    private static function sentenciasSql(string $sql): array
    {
        $sql = preg_replace('~/\*.*?\*/~s', '', $sql) ?? $sql;   // comentarios de bloque
        $sentencias = [];
        $buf = '';
        $len = strlen($sql);
        $enCadena = false;   // dentro de '...'
        $enId = false;       // dentro de `...`

        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];
            $sig = $i + 1 < $len ? $sql[$i + 1] : '';

            if ($enCadena) {                          // dentro de una cadena '...'
                $buf .= $c;
                if ($c === '\\' && $sig !== '') { $buf .= $sig; $i++; }        // escape \x
                elseif ($c === "'") {
                    if ($sig === "'") { $buf .= $sig; $i++; }                   // '' escapada
                    else { $enCadena = false; }
                }
                continue;
            }
            if ($enId) {                              // dentro de un identificador `...`
                $buf .= $c;
                if ($c === '`') $enId = false;
                continue;
            }

            // Comentario de línea: -- (seguido de espacio/fin) o #, hasta el salto de línea.
            $dosGuiones = ($c === '-' && $sig === '-' && ($i + 2 >= $len || ctype_space($sql[$i + 2])));
            if ($dosGuiones || $c === '#') {
                while ($i < $len && $sql[$i] !== "\n") $i++;
                $buf .= "\n";
                continue;
            }

            if ($c === "'") { $enCadena = true; $buf .= $c; continue; }
            if ($c === '`') { $enId = true;     $buf .= $c; continue; }
            if ($c === ';') {                         // fin de sentencia
                $s = trim($buf);
                if ($s !== '') $sentencias[] = $s;
                $buf = '';
                continue;
            }
            $buf .= $c;
        }
        $s = trim($buf);
        if ($s !== '') $sentencias[] = $s;
        return $sentencias;
    }

    // ── Finalizar (transacción: persona + ajustes + acceso + cerrojo) ─────────
    /**
     * $persona  = [nombre, apellido1, apellido2?, sexo?('M'|'F'|''), nacimiento('AAAA'|'AAAA-MM-DD'|'')]
     * $acceso   = [activo:bool, clave_edicion?, clave_lectura?]
     * $identidad= [titulo, subtitulo?]
     * Devuelve ['main_id' => int].
     */
    public static function finalizar(PDO $pdo, array $persona, array $acceso, array $identidad): array
    {
        // CERROJO: no reinstalar por encima de una instalación existente.
        if (self::estaInstalado()) {
            throw new RuntimeException('El árbol ya está instalado; no se puede reinstalar.');
        }

        $nombre = trim((string) ($persona['nombre'] ?? ''));
        $ape1   = trim((string) ($persona['apellido1'] ?? ''));
        $sexo   = (string) ($persona['sexo'] ?? '');
        if ($nombre === '') throw new InvalidArgumentException('El nombre de la primera persona es obligatorio.');
        if ($ape1 === '')   throw new InvalidArgumentException('El primer apellido es obligatorio.');
        if ($sexo !== 'M' && $sexo !== 'F') throw new InvalidArgumentException('El sexo es obligatorio (Hombre o Mujer).');

        // El primer usuario es el ADMINISTRADOR. Administrar SIEMPRE exige iniciar
        // sesión con la clave de edición —también en árbol abierto, que solo quita
        // el login para VER—. Por eso la fecha (identifica al admin al entrar) y la
        // clave de edición son SIEMPRE obligatorias; la de lectura solo si el árbol
        // es protegido (si es abierto, ver no pide clave).
        $activo = !empty($acceso['activo']);
        $nac = trim((string) ($persona['nacimiento'] ?? ''));
        if ($nac === '') {
            throw new InvalidArgumentException('La fecha de nacimiento es obligatoria: identifica al administrador para iniciar sesión.');
        }
        if (trim((string) ($acceso['clave_edicion'] ?? '')) === '') {
            throw new InvalidArgumentException('Falta la clave de edición (administrador).');
        }
        if ($activo && trim((string) ($acceso['clave_lectura'] ?? '')) === '') {
            throw new InvalidArgumentException('Falta la clave de lectura.');
        }

        $titulo    = trim((string) ($identidad['titulo'] ?? '')) ?: 'Nuestro árbol';
        $subtitulo = trim((string) ($identidad['subtitulo'] ?? ''));

        $pdo->beginTransaction();
        try {
            // 1) Primera persona (vía Personas::crear, el mismo alta que usa el árbol).
            $data = [
                'first name'  => $nombre,
                'last name'   => $ape1,
                'last name 2' => (string) ($persona['apellido2'] ?? ''),
                'gender'      => ($persona['sexo'] ?? '') === 'M' || ($persona['sexo'] ?? '') === 'F' ? $persona['sexo'] : null,
                'birthday'    => $nac,
                'death'       => '',
                'place'       => '',
                'occupation'  => '',
                'notes'       => '',
                'avatar'      => '',
            ];
            $mainId = Personas::crear($pdo, $data);

            // 2) Árbol id=1 (preparación multi-árbol).
            $st = $pdo->prepare(
                'INSERT INTO arb_arboles (id, nombre) VALUES (1, :n)
                 ON DUPLICATE KEY UPDATE nombre = :n2'
            );
            $st->execute(['n' => $titulo, 'n2' => $titulo]);

            // 3) Ajustes de identidad + persona central (valida que main_id exista/activa).
            Ajustes::guardar($pdo, ['titulo' => $titulo, 'subtitulo' => $subtitulo, 'main_id' => $mainId]);

            // 4) Control de acceso: la clave de EDICIÓN (admin) se fija SIEMPRE
            //    (administrar exige login incluso en árbol abierto). La de LECTURA
            //    solo si el árbol es protegido (si es abierto, ver no pide clave).
            Acceso::establecerControl($pdo, $activo);
            Acceso::establecerClave($pdo, 'edicion', (string) $acceso['clave_edicion']);
            if ($activo) {
                Acceso::establecerClave($pdo, 'lectura', (string) $acceso['clave_lectura']);
            }

            // 5) Versión del esquema + CERROJO (instalado=1).
            self::ponerAjuste($pdo, 'version_esquema', self::VERSION_ESQUEMA);
            self::ponerAjuste($pdo, 'instalado', '1');

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        // Marca INDEPENDIENTE de la BD en config.php (cierra el fail-open del
        // cerrojo, SEC-01). Va tras el commit: es una operación de FICHERO, fuera
        // de la transacción de BD.
        self::marcarInstaladoEnConfig();

        return ['main_id' => $mainId];
    }

    /** Upsert de un ajuste (clave/valor). */
    private static function ponerAjuste(PDO $pdo, string $clave, string $valor): void
    {
        $st = $pdo->prepare(
            'INSERT INTO arb_ajustes (clave, valor) VALUES (:c, :v)
             ON DUPLICATE KEY UPDATE valor = :v2'
        );
        $st->execute(['c' => $clave, 'v' => $valor, 'v2' => $valor]);
    }
}
