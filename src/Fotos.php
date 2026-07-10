<?php
declare(strict_types=1);

/**
 * Fotos — subida y gestión de las imágenes de personas (como ARCHIVO).
 * -------------------------------------------------------------------
 * Las fotos NO van en la base de datos: se guardan como archivo en
 * almacen/fotos/ (fuera de public/, no accesible por web). En la BD solo se
 * guarda el NOMBRE del archivo (columna avatar). Se sirven por el "portero"
 * public/foto.php.
 *
 * Al subir: se valida que sea una imagen REAL, se limita el tamaño, y se
 * REDIMENSIONA y reencoda a JPEG con GD (uniforma el formato y elimina los
 * metadatos EXIF por privacidad). El nombre es aleatorio (sin datos personales).
 */
final class Fotos
{
    private const MAX_LADO        = 512;               // lado máximo en píxeles tras redimensionar
    private const MAX_BYTES       = 8 * 1024 * 1024;   // 8 MB de archivo de entrada como máximo
    private const MAX_LADO_ENTRA  = 10000;             // SEC-08: lado máximo admitido ANTES de decodificar
    private const MAX_PIXELES     = 25000000;          // SEC-08: 25 MP máx. (limita la memoria de decodificación)
    private const CALIDAD         = 85;                // calidad JPEG
    public const PATRON           = '/^[a-f0-9]{32}\.jpg$/';  // forma de los nombres que generamos (única fuente, PHP-3)

    /** Carpeta donde viven las fotos (fuera de public/). */
    public static function carpeta(): string
    {
        return RAIZ . '/almacen/fotos';
    }

    /** Ruta completa y SEGURA de una foto por su nombre (evita traversal). */
    public static function ruta(string $nombre): ?string
    {
        $nombre = basename($nombre);                       // corta cualquier ruta
        if (!preg_match(self::PATRON, $nombre)) return null; // solo nuestros nombres
        return self::carpeta() . '/' . $nombre;
    }

    /**
     * Guarda una imagen subida (ruta temporal de PHP) redimensionada como JPEG.
     * Devuelve el NOMBRE de archivo generado. Lanza InvalidArgumentException si
     * la entrada no es una imagen válida o es demasiado grande.
     */
    public static function guardarDesdeArchivo(string $rutaTmp): string
    {
        if (!is_file($rutaTmp)) {
            throw new InvalidArgumentException('No se recibió el archivo de imagen.');
        }
        if (filesize($rutaTmp) > self::MAX_BYTES) {
            throw new InvalidArgumentException('La imagen es demasiado grande (máx. 8 MB).');
        }

        // Validación REAL: getimagesize falla si no es imagen; comprobamos el tipo.
        $info = @getimagesize($rutaTmp);
        if ($info === false) {
            throw new InvalidArgumentException('El archivo no es una imagen válida.');
        }
        $permitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($info['mime'], $permitidos, true)) {
            throw new InvalidArgumentException('Formato de imagen no admitido (usa JPG, PNG, GIF o WebP).');
        }

        // SEC-08 (bomba de descompresión): validar las DIMENSIONES declaradas ANTES de
        // decodificar. Un PNG diminuto puede declarar 30000×30000 y agotar la memoria al
        // expandirse a un mapa de bits (≈ ancho·alto·4 bytes). getimagesize ya nos ha
        // dado el tamaño sin decodificar, así que rechazamos aquí lo desproporcionado.
        $w0 = (int) ($info[0] ?? 0);
        $h0 = (int) ($info[1] ?? 0);
        if ($w0 < 1 || $h0 < 1) {
            throw new InvalidArgumentException('El archivo no es una imagen válida.');
        }
        if ($w0 > self::MAX_LADO_ENTRA || $h0 > self::MAX_LADO_ENTRA || ($w0 * $h0) > self::MAX_PIXELES) {
            throw new InvalidArgumentException('La imagen tiene dimensiones excesivas (máx. ' . self::MAX_LADO_ENTRA . ' px por lado).');
        }

        // Cargar la imagen en memoria (imagecreatefromstring cubre los formatos anteriores).
        $origen = @imagecreatefromstring(file_get_contents($rutaTmp));
        if ($origen === false) {
            throw new InvalidArgumentException('No se pudo leer la imagen.');
        }

        $w = imagesx($origen);
        $h = imagesy($origen);
        $escala = min(1, self::MAX_LADO / max($w, $h));    // nunca ampliar
        $nw = max(1, (int) round($w * $escala));
        $nh = max(1, (int) round($h * $escala));

        // Lienzo destino con fondo blanco (por si el origen tiene transparencia).
        $destino = imagecreatetruecolor($nw, $nh);
        $blanco = imagecolorallocate($destino, 255, 255, 255);
        imagefilledrectangle($destino, 0, 0, $nw, $nh, $blanco);
        imagecopyresampled($destino, $origen, 0, 0, 0, 0, $nw, $nh, $w, $h);

        // Guardar como JPEG (sin EXIF) con nombre aleatorio.
        self::asegurarCarpeta();
        $nombre = bin2hex(random_bytes(16)) . '.jpg';
        $ruta = self::carpeta() . '/' . $nombre;
        $ok = imagejpeg($destino, $ruta, self::CALIDAD);

        imagedestroy($origen);
        imagedestroy($destino);

        if (!$ok) {
            throw new RuntimeException('No se pudo guardar la imagen en el servidor.');
        }
        return $nombre;
    }

    /** Borra una foto por su nombre (si existe y es un nombre válido). */
    public static function borrar(?string $nombre): void
    {
        if (!$nombre) return;
        $ruta = self::ruta($nombre);
        if ($ruta && is_file($ruta)) @unlink($ruta);
    }

    // ── Borrado DIFERIDO al commit (INT-06) ──────────────────────────────────
    // Borrar el archivo de una foto es un efecto de FICHERO irreversible; hacerlo
    // DENTRO de la transacción es peligroso: si el commit falla, el archivo ya no
    // está pero la BD lo sigue apuntando (referencia colgante). Solución: durante la
    // escritura NO se borra; se ENCOLA, y el envoltorio de transacción (ejecutarEscritura)
    // purga la cola SOLO tras un commit correcto, o la descarta si hubo rollback.
    /** @var string[] Nombres de foto pendientes de borrar tras un commit correcto. */
    private static array $pendientes = [];

    /** Programa el borrado de una foto para DESPUÉS de un commit correcto. */
    public static function programarBorrado(?string $nombre): void
    {
        if ($nombre) self::$pendientes[] = (string) $nombre;
    }

    /** Purga (borra de disco) las fotos programadas. Lo llama ejecutarEscritura tras commit. */
    public static function purgarProgramados(): void
    {
        $lista = self::$pendientes;
        self::$pendientes = [];
        foreach ($lista as $n) self::borrar($n);
    }

    /** Descarta la cola SIN borrar nada (tras un rollback: la BD sigue apuntando los archivos). */
    public static function descartarProgramados(): void
    {
        self::$pendientes = [];
    }

    // ── Recolección de fotos HUÉRFANAS (SEC-15) ──────────────────────────────
    // Subir una foto y no guardar la persona, o re-subir antes de guardar, deja
    // archivos que ya nadie referencia (crecimiento ilimitado del disco). Esta
    // recolección compara los archivos en disco con los avatares referenciados por
    // CUALQUIER persona (activas Y en la papelera, para no borrar fotos de la
    // papelera) y elimina los no referenciados que además tengan cierta ANTIGÜEDAD
    // (periodo de gracia), para no pisar una foto recién subida en una edición aún
    // en curso. Se dispara desde el panel (api/mantenimiento.php).

    /** Lista los archivos de foto NUESTROS presentes en disco: [nombre => mtime]. */
    public static function listarArchivos(): array
    {
        $dir = self::carpeta();
        if (!is_dir($dir)) return [];
        $out = [];
        foreach (scandir($dir) ?: [] as $n) {
            if (preg_match(self::PATRON, $n)) $out[$n] = (int) @filemtime($dir . '/' . $n);
        }
        return $out;
    }

    /**
     * Borra las fotos huérfanas (sin persona que las referencie) con más de
     * $graciaSegundos de antigüedad. Devuelve un resumen {revisadas, borradas, archivos}.
     */
    public static function purgarHuerfanas(PDO $pdo, int $graciaSegundos = 86400): array
    {
        $archivos = self::listarArchivos();
        if (!$archivos) return ['revisadas' => 0, 'borradas' => 0, 'archivos' => []];

        // Referencias VIVAS: todas las filas, incluidas las de la papelera.
        $refs = [];
        $col = $pdo->query('SELECT DISTINCT avatar FROM arb_personas WHERE avatar IS NOT NULL')
                   ->fetchAll(PDO::FETCH_COLUMN);
        foreach ($col as $a) $refs[(string) $a] = true;

        $limite = time() - max(0, $graciaSegundos);
        $borradas = [];
        foreach ($archivos as $nombre => $mtime) {
            if (isset($refs[$nombre])) continue;     // en uso: no se toca
            if ($mtime > $limite) continue;          // recién subida: posible edición en curso
            self::borrar($nombre);
            $borradas[] = $nombre;
        }
        return ['revisadas' => count($archivos), 'borradas' => count($borradas), 'archivos' => $borradas];
    }

    /** Crea la carpeta de fotos si no existe; error claro si no se puede escribir. */
    private static function asegurarCarpeta(): void
    {
        $dir = self::carpeta();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No existe la carpeta de fotos y no se pudo crear: ' . $dir);
        }
        if (!is_writable($dir)) {
            throw new RuntimeException('La carpeta de fotos no tiene permisos de escritura: ' . $dir);
        }
    }
}
