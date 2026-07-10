<?php
declare(strict_types=1);

/**
 * Personas — alta, edición y borrado (soft-delete) de personas.
 * ------------------------------------------------------------
 * Toda la escritura usa SENTENCIAS PREPARADAS y valida la entrada. El mapeo
 * entre los campos de family-chart (data) y las columnas de la BD vive AQUÍ
 * (un solo sitio), para que el resto del backend no dependa de la librería.
 *
 * PASO 7: el campo "avatar" YA se persiste (es el NOMBRE del archivo de la foto,
 * que cabe en VARCHAR(255)). Al EDITAR, si el avatar cambia, se borra el archivo
 * anterior (limpieza). La subida/redimensión del archivo la hace Fotos (por el
 * endpoint api/foto.php); aquí solo se guarda el nombre.
 */
require_once __DIR__ . '/Fechas.php';
require_once __DIR__ . '/Arbol.php';
require_once __DIR__ . '/Relaciones.php';

final class Personas
{
    /** Columnas de texto que se guardan, con su clave en el `data` de family-chart y su longitud máx. */
    private const CAMPOS = [
        // columna        clave family-chart   máx
        'nombre'        => ['first name',   100],
        'apellido1'     => ['last name',    100],
        'apellido2'     => ['last name 2',  100],
        'lugar'         => ['place',        150],
        'ocupacion'     => ['occupation',   150],
        'notas'         => ['notes',      100000],  // TEXT
    ];

    /**
     * Convierte el `data` de family-chart en [columna => valor] validado y listo
     * para INSERT/UPDATE. Lanza InvalidArgumentException si algún dato es inválido.
     */
    private static function columnasDesde(array $data): array
    {
        $c = [];

        // Campos de texto (con recorte y límite de longitud).
        foreach (self::CAMPOS as $col => [$clave, $max]) {
            $v = $data[$clave] ?? '';
            if (!is_scalar($v)) $v = '';
            $v = trim((string) $v);
            if (mb_strlen($v) > $max) {
                throw new InvalidArgumentException("El campo '$clave' es demasiado largo (máx. $max).");
            }
            $c[$col] = $v;
        }

        // VAL-01: el NOMBRE es obligatorio también en el servidor (coherente con el
        // aviso en rojo del cliente): no se acepta vacío ni solo espacios.
        if ($c['nombre'] === '') {
            throw new InvalidArgumentException('El nombre es obligatorio.');
        }

        // Sexo: 'M' o 'F'; cualquier otra cosa (vacío, 'unknown'…) => NULL (desconocido).
        $sexo = $data['gender'] ?? null;
        $c['sexo'] = ($sexo === 'M' || $sexo === 'F') ? $sexo : null;

        // Fechas: "" | "AAAA" | "AAAA-MM-DD" (el formato del front). Otro => error.
        $c['nacimiento']    = self::validarFecha($data['birthday'] ?? '', 'nacimiento');
        $c['fallecimiento'] = self::validarFecha($data['death'] ?? '', 'fallecimiento');

        // VAL-02: el fallecimiento no puede ser ANTERIOR al nacimiento. Si ambas son
        // fecha exacta, se compara por fecha (capta morir antes dentro del mismo año);
        // si alguna es solo año, se compara por año (mismo año se admite).
        $nac = $c['nacimiento']; $fall = $c['fallecimiento'];
        if ($nac !== '' && $fall !== '') {
            $ambasExactas = preg_match('/^\d{4}-\d{2}-\d{2}$/', $nac) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fall);
            $imposible = $ambasExactas
                ? ($fall < $nac)
                : ((int) substr($fall, 0, 4) < (int) substr($nac, 0, 4));
            if ($imposible) {
                throw new InvalidArgumentException(
                    'Fecha imposible: el fallecimiento (' . $fall . ') no puede ser anterior al nacimiento (' . $nac . ').'
                );
            }
        }

        // Avatar (SEC-10): SOLO se admite una foto SUBIDA al servidor —el nombre de
        // archivo que genera Fotos ("<32 hex>.jpg")— o vacío. Se PROHÍBEN las URLs
        // externas y los dataURL: servir una URL externa a todos los lectores filtra
        // su privacidad (tracking del tercero), provoca contenido mixto bajo HTTPS y
        // rompe la CSP estricta. Las fotos propias van redimensionadas, sin EXIF y por
        // el portero foto.php (mismo origen).
        $av = $data['avatar'] ?? '';
        if (!is_scalar($av)) $av = '';
        $av = trim((string) $av);
        if ($av !== '' && !preg_match(Fotos::PATRON, $av)) {   // única fuente del patrón (PHP-3)
            throw new InvalidArgumentException('La foto debe subirse desde el árbol; no se admiten URLs externas.');
        }
        $c['avatar'] = $av === '' ? null : $av;

        return $c;
    }

    /**
     * Valida y normaliza una fecha del front. Devuelve la cadena tal cual.
     * VAL-03 (calendario real): además del formato ('' | 'AAAA' | 'AAAA-MM-DD'),
     * exige que sea una fecha EXISTENTE — mes 1-12, día válido para ese mes/año
     * (checkdate) — y un año plausible (ni 0000 ni en el futuro). Así se rechazan
     * cosas como 2020-13-45, 2020-00-00 o 0000.
     */
    private static function validarFecha($v, string $campo): string
    {
        if (!is_scalar($v)) return '';
        $v = trim((string) $v);
        if ($v === '') return '';
        if (!preg_match('/^\d{4}$/', $v) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            throw new InvalidArgumentException("La fecha de $campo debe ser 'AAAA' o 'AAAA-MM-DD'.");
        }
        // VAL-03: calendario real y año plausible (autoridad única en Fechas).
        if (!Fechas::esFechaCalendario($v)) {
            throw new InvalidArgumentException("La fecha de $campo no existe en el calendario o el año no es válido.");
        }
        return $v;
    }

    /** Crea una persona y devuelve su id nuevo (el que asigna la BD). */
    public static function crear(PDO $pdo, array $data): int
    {
        $c = self::columnasDesde($data);
        $cols  = array_keys($c);
        $campos = implode(', ', $cols);                                  // nombres = constantes nuestras (seguro)
        $marcadores = implode(', ', array_map(fn($k) => ":$k", $cols));  // valores por marcador (preparado)
        $st = $pdo->prepare("INSERT INTO arb_personas ($campos) VALUES ($marcadores)");
        $st->execute($c);
        return (int) $pdo->lastInsertId();
    }

    /** Edita los datos de una persona existente (no toca sus vínculos). */
    public static function editar(PDO $pdo, int $id, array $data): void
    {
        if ($id <= 0) throw new InvalidArgumentException('Id de persona inválido.');
        $c = self::columnasDesde($data);

        // CONC-02: cerrojo de árbol como PRIMERA sentencia (antes de cualquier
        // lectura) → las comprobaciones transitivas de fecha/sexo de abajo son
        // atómicas frente a otras escrituras estructurales simultáneas.
        Arbol::bloquear($pdo);

        // REGLA de fechas (INT-04): al cambiar el nacimiento, comprobar coherencia
        // con TODA su ascendencia y descendencia (un descendiente debe nacer DESPUÉS).
        Fechas::validarNacimiento($pdo, $id, (string) $c['nacimiento']);

        // INT-02 (evasión por edición): cambiar el sexo no puede dejar a un hijo con
        // dos progenitores del mismo sexo (p.ej. crear padre+madre y luego cambiar
        // uno para que coincida con el otro).
        Relaciones::validarSexoAlEditar($pdo, $id, $c['sexo']);

        // INT-07: comprobar EXISTENCIA (activa) ANTES de actualizar. Se hace por SELECT
        // —no por rowCount del UPDATE— porque MySQL cuenta filas CAMBIADAS: reguardar
        // con los mismos valores daría 0 y sería un falso positivo. Aquí, si no existe
        // o está en la papelera, no hay fila y se avisa (no "ok" en silencio). De paso
        // recuperamos el avatar anterior para la limpieza diferida.
        $sel = $pdo->prepare('SELECT avatar FROM arb_personas WHERE id = :id AND borrado_en IS NULL');
        $sel->execute(['id' => $id]);
        $fila = $sel->fetch(PDO::FETCH_ASSOC);
        if ($fila === false) {
            throw new InvalidArgumentException('No se pudo editar: la persona no existe o está en la papelera.');
        }
        $avatarAnterior = $fila['avatar'];

        $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($c)));
        $c['id'] = $id;
        $st = $pdo->prepare("UPDATE arb_personas SET $sets WHERE id = :id AND borrado_en IS NULL");
        $st->execute($c);

        // INT-06: la limpieza del archivo anterior se PROGRAMA para después del commit
        // (si la transacción se revierte, el archivo NO se borra → sin referencia colgante).
        // Fotos::borrar solo actúa sobre archivos NUESTROS; ignora URLs y valores vacíos.
        if ($avatarAnterior && $avatarAnterior !== $c['avatar']) {
            Fotos::programarBorrado((string) $avatarAnterior);
        }
    }

    /**
     * Lista las personas ACTIVAS sin nombre (nombre vacío o solo espacios). Son
     * restos que pueden quedar de importaciones o de vínculos heredados; el panel
     * de administración («Personas sin nombre») permite renombrarlas o mandarlas a
     * la papelera. Devuelve [id, apellido1, apellido2, sexo, nacimiento, avatar].
     */
    public static function sinNombre(PDO $pdo): array
    {
        $sql = "SELECT id, apellido1, apellido2, sexo, nacimiento, fallecimiento, avatar
                FROM arb_personas
                WHERE borrado_en IS NULL AND (nombre IS NULL OR TRIM(nombre) = '')
                ORDER BY id";
        return $pdo->query($sql)->fetchAll();
    }

    /**
     * Renombra una persona: actualiza SOLO nombre y apellidos, sin tocar el resto
     * de sus datos ni sus vínculos (a diferencia de editar(), que reescribe todas
     * las columnas). Pensado para la herramienta «Personas sin nombre» del panel.
     */
    public static function renombrar(PDO $pdo, int $id, string $nombre, string $apellido1, string $apellido2 = ''): void
    {
        if ($id <= 0) throw new InvalidArgumentException('Id de persona inválido.');
        $nombre    = trim($nombre);
        $apellido1 = trim($apellido1);
        $apellido2 = trim($apellido2);
        if ($nombre === '') throw new InvalidArgumentException('El nombre no puede estar vacío.');
        foreach (['nombre' => $nombre, 'apellido1' => $apellido1, 'apellido2' => $apellido2] as $campo => $v) {
            if (mb_strlen($v) > 100) throw new InvalidArgumentException("El campo '$campo' es demasiado largo (máx. 100).");
        }
        $st = $pdo->prepare(
            'UPDATE arb_personas SET nombre = :n, apellido1 = :a1, apellido2 = :a2
             WHERE id = :id AND borrado_en IS NULL'
        );
        $st->execute(['n' => $nombre, 'a1' => $apellido1, 'a2' => $apellido2, 'id' => $id]);
    }

    /**
     * Restaura una persona de la papelera (borrado_en = NULL). IDEMPOTENTE: si ya
     * está activa no hace nada; si no existe en absoluto, error claro (INT-07).
     * ------------------------------------------------------------------------------
     * La usa el guardado por LOTES (guardar.php) cuando un REHACER reintroduce a una
     * persona que un DESHACER anterior había mandado a la papelera: en vez de crear un
     * DUPLICADO (persona nueva + vínculo nuevo, que además chocaba con el vínculo viejo
     * "dormido"), se REUTILIZA su misma identidad. Sus vínculos dormidos vuelven a estar
     * activos automáticamente (son filas de arb_filiacion/arb_pareja que nunca se borraron;
     * al reactivar la persona, arbol.php vuelve a mostrarlos). Así el ciclo crear→deshacer→
     * rehacer no deja duplicados, ni huérfanos, ni vínculos colgantes.
     */
    public static function restaurar(PDO $pdo, int $id): void
    {
        if ($id <= 0) throw new InvalidArgumentException('Id de persona inválido.');
        $sel = $pdo->prepare('SELECT borrado_en FROM arb_personas WHERE id = :id');
        $sel->execute(['id' => $id]);
        $fila = $sel->fetch(PDO::FETCH_ASSOC);
        if ($fila === false) {
            throw new InvalidArgumentException('No se pudo restaurar: la persona no existe.');
        }
        if ($fila['borrado_en'] === null) return;   // ya activa → idempotente
        $st = $pdo->prepare('UPDATE arb_personas SET borrado_en = NULL WHERE id = :id');
        $st->execute(['id' => $id]);
    }

    /**
     * Borra una persona a la PAPELERA (soft-delete): marca borrado_en. NO elimina
     * la fila ni dispara cascada; sus vínculos quedan (se ignoran al leer porque
     * la persona ya no está activa). Recuperable en el PASO 10.
     */
    public static function borrar(PDO $pdo, int $id): void
    {
        if ($id <= 0) throw new InvalidArgumentException('Id de persona inválido.');
        $st = $pdo->prepare('UPDATE arb_personas SET borrado_en = NOW() WHERE id = :id AND borrado_en IS NULL');
        $st->execute(['id' => $id]);
        // INT-07: borrar un id inexistente o ya en la papelera no debe pasar por "ok".
        if ($st->rowCount() === 0) {
            throw new InvalidArgumentException('No se pudo borrar: la persona no existe o ya está en la papelera.');
        }
    }
}
