<?php
declare(strict_types=1);

/**
 * LimiteAcceso — freno anti-fuerza-bruta del login POR IP y PERSISTENTE (SEC-04).
 * ------------------------------------------------------------------------------
 * El freno anterior vivía en $_SESSION: bastaba con NO enviar la cookie de sesión
 * para que el contador no se acumulara nunca (un atacante automatizado no manda
 * cookies). Este freno NO depende de la sesión ni de ninguna cookie: cuenta los
 * fallos por DIRECCIÓN IP y los guarda en disco, así que persiste entre peticiones
 * aunque el atacante llegue "en blanco" cada vez.
 *
 * PERSISTENCIA — por qué en FICHERO (y no en BD):
 *   · Cada IP tiene un archivo JSON en almacen/ratelimit/<sha256(ip)>.json con el
 *     conteo de la ventana actual y hasta cuándo está bloqueada.
 *   · Es autocontenido: NO toca el esquema de la BD (no hay que migrar tablas en
 *     instalaciones ya existentes) y funciona en cualquier hosting con la carpeta
 *     almacen/ escribible (que ya hace falta para fotos y copias).
 *   · La concurrencia se resuelve con flock() (bloqueo exclusivo por archivo): el
 *     login es de bajo volumen, así que es más que suficiente y sin dependencias.
 *   · Se auto-limpia: al acertar la clave se borra el archivo de esa IP, y de vez
 *     en cuando se purgan los archivos viejos (no se acumulan indefinidamente).
 *
 * POLÍTICA: hasta MAX_FALLOS fallos dentro de una VENTANA; al superarlos, bloqueo
 * temporal con BACKOFF exponencial (cada bloqueo consecutivo dura el doble, con
 * un tope). Un login correcto limpia el rastro por completo.
 */
final class LimiteAcceso
{
    private const MAX_FALLOS   = 5;      // fallos permitidos dentro de la ventana
    private const VENTANA      = 900;    // 15 min: ventana de conteo de fallos
    private const BLOQUEO_BASE = 60;     // 1 min de bloqueo al superar el umbral
    private const BLOQUEO_MAX  = 3600;   // tope de 1 h (crecimiento exponencial)

    /** Carpeta de estado (fuera de public/, no accesible por web). */
    private static function dir(): string
    {
        $raiz = defined('RAIZ') ? RAIZ : dirname(__DIR__);
        return $raiz . '/almacen/ratelimit';
    }

    /** IP del cliente. REMOTE_ADDR es la fuente fiable (no falsificable por cabecera). */
    public static function ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return $ip !== '' ? $ip : 'desconocida';
    }

    /** Ruta del archivo de estado de una IP (crea la carpeta si hace falta). */
    private static function ruta(string $ip): ?string
    {
        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return null;
        return $dir . '/' . hash('sha256', $ip) . '.json';
    }

    /** Segundos de bloqueo que le quedan a esta IP (0 = no bloqueada). */
    public static function segundosBloqueo(string $ip): int
    {
        $r = self::ruta($ip);
        if (!$r || !is_file($r)) return 0;
        $d = json_decode((string) @file_get_contents($r), true);
        if (!is_array($d)) return 0;
        $hasta = (int) ($d['bloqueado_hasta'] ?? 0);
        $ahora = time();
        return $hasta > $ahora ? $hasta - $ahora : 0;
    }

    /**
     * Registra un intento FALLIDO para esta IP. Devuelve los segundos de bloqueo
     * si este fallo ha activado (o mantiene) un bloqueo; 0 si aún no.
     */
    public static function registrarFallo(string $ip): int
    {
        $r = self::ruta($ip);
        if (!$r) return 0;
        $fh = @fopen($r, 'c+');
        if (!$fh) return 0;
        try {
            flock($fh, LOCK_EX);
            $d = json_decode((string) stream_get_contents($fh), true);
            if (!is_array($d)) $d = [];

            $ahora    = time();
            $inicio   = (int) ($d['ventana_inicio'] ?? 0);
            $fallos   = (int) ($d['fallos'] ?? 0);
            $bloqueos = (int) ($d['bloqueos'] ?? 0);

            // Si la ventana ha caducado, se reinicia el conteo.
            if ($ahora - $inicio > self::VENTANA) { $inicio = $ahora; $fallos = 0; }
            $fallos++;

            $bloqueoSeg = 0;
            if ($fallos >= self::MAX_FALLOS) {
                $bloqueos++;
                // Backoff exponencial: 60s, 120s, 240s… con tope BLOQUEO_MAX.
                $bloqueoSeg = (int) min(self::BLOQUEO_MAX, self::BLOQUEO_BASE * (2 ** ($bloqueos - 1)));
                $d['bloqueado_hasta'] = $ahora + $bloqueoSeg;
                $fallos = 0;          // empieza una tanda nueva tras el bloqueo
                $inicio = $ahora;
            }

            $d['ventana_inicio'] = $inicio;
            $d['fallos']         = $fallos;
            $d['bloqueos']       = $bloqueos;
            $d['visto']          = $ahora;

            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, (string) json_encode($d));
            fflush($fh);

            return $bloqueoSeg;
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /** Login correcto: borra el rastro de la IP y purga de vez en cuando los viejos. */
    public static function limpiar(string $ip): void
    {
        $r = self::ruta($ip);
        if ($r && is_file($r)) @unlink($r);
        self::purgaOcasional();
    }

    /** Aproximadamente 1 de cada 50 llamadas, borra archivos de IPs ya olvidadas. */
    private static function purgaOcasional(): void
    {
        if ((int) (microtime(true) * 1000) % 50 !== 0) return;
        $dir = self::dir();
        if (!is_dir($dir)) return;
        $limite = time() - self::BLOQUEO_MAX - self::VENTANA - 3600;
        foreach (glob($dir . '/*.json') ?: [] as $f) {
            if ((int) @filemtime($f) < $limite) @unlink($f);
        }
    }
}
