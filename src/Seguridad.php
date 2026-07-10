<?php
declare(strict_types=1);

/**
 * Seguridad — cabeceras HTTP de blindaje y registro de errores (TANDA H1).
 * -----------------------------------------------------------------------
 * Dos cometidos, ambos transversales a toda la app:
 *
 *   1) CABECERAS de seguridad (SEC-09, SEC-20): X-Content-Type-Options,
 *      X-Frame-Options, Referrer-Policy, HSTS (bajo HTTPS, también tras un proxy
 *      TLS) y una CSP estricta para las páginas HTML. El JS es TODO local (sin
 *      CDN externos), así que script-src puede ser 'self' sin 'unsafe-inline':
 *      esa es la defensa real contra XSS. Los estilos sí llevan 'unsafe-inline'
 *      porque hay atributos style="" en el HTML y la librería del árbol maneja
 *      estilos en línea; un ataque vía estilos es de impacto muy menor.
 *
 *   2) REGISTRO de errores (SEC-05): el detalle de una excepción (SQL, rutas,
 *      estructura) NO se devuelve nunca al cliente; se anota en el log del
 *      servidor (almacen/logs/) y al cliente le llega un mensaje genérico con un
 *      código de referencia corto para poder cruzarlo con el log.
 *
 * Clase autónoma: NO depende de bootstrap ni de la BD (así la puede usar también
 * el instalador, que corre antes de existir config.php).
 */
final class Seguridad
{
    private static function raiz(): string
    {
        return defined('RAIZ') ? RAIZ : dirname(__DIR__);
    }

    /**
     * ¿La petición llega por HTTPS? Contempla el TLS TERMINADO EN UN PROXY
     * (habitual en hosting compartido / Hostinger): entonces $_SERVER['HTTPS']
     * puede venir vacío pero el proxy marca X-Forwarded-Proto/SSL o el puerto 443.
     */
    public static function esHttps(): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off') return true;
        if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') return true;
        if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on') return true;
        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) return true;
        return false;
    }

    /**
     * Cabeceras comunes (sin CSP). Válidas para HTML, JSON e imágenes. La CSP no
     * va aquí porque es específica de las páginas HTML (donde se ejecuta script).
     */
    public static function cabecerasBase(): void
    {
        if (headers_sent()) return;
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        if (self::esHttps()) {
            // HSTS: fuerza HTTPS en visitas futuras. Solo se emite bajo HTTPS real.
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Cabeceras completas para una página HTML de la app: base + CSP estricta.
     * $nonce (opcional) autoriza UN bloque <script> en línea concreto (lo usa el
     * instalador para su pequeño script de mejora progresiva); la app principal no
     * lo necesita porque no tiene scripts en línea.
     */
    public static function cabecerasHtml(?string $nonce = null): void
    {
        self::cabecerasBase();
        if (headers_sent()) return;
        $script = "'self'";
        if ($nonce !== null && $nonce !== '') $script .= " 'nonce-{$nonce}'";
        $csp = "default-src 'self'; "
             . "script-src {$script}; "
             . "style-src 'self' 'unsafe-inline'; "
             . "img-src 'self' data: blob:; "         // fotos propias (foto.php) + vista previa (blob:) + data:
             . "font-src 'self' data:; "
             . "connect-src 'self'; "                 // fetch a api/ (mismo origen)
             . "media-src 'self'; "
             . "object-src 'none'; "
             . "base-uri 'self'; "
             . "form-action 'self'; "
             . "frame-ancestors 'none'";              // equivalente moderno de X-Frame-Options: DENY
        header('Content-Security-Policy: ' . $csp);
    }

    /** Genera un nonce aleatorio para autorizar un <script> en línea puntual. */
    public static function nonce(): string
    {
        return base64_encode(random_bytes(16));
    }

    /**
     * Registra el detalle de un error en el log del servidor y devuelve un CÓDIGO
     * de referencia corto (para dárselo al cliente sin filtrar nada). Nunca lanza:
     * un fallo al escribir el log jamás debe tumbar la respuesta.
     */
    public static function registrarError(Throwable $e, string $contexto = ''): string
    {
        $ref = substr(bin2hex(random_bytes(4)), 0, 8);
        $linea = sprintf(
            '[%s] ref=%s %s%s: %s in %s:%d',
            date('Y-m-d H:i:s'),
            $ref,
            $contexto !== '' ? "({$contexto}) " : '',
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );
        try {
            $dir = self::raiz() . '/almacen/logs';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            if (is_dir($dir) && is_writable($dir)) {
                @file_put_contents($dir . '/error.log', $linea . PHP_EOL, FILE_APPEND | LOCK_EX);
            }
        } catch (Throwable $x) {
            /* nunca romper la respuesta por culpa del log */
        }
        @error_log('genealogia ' . $linea);
        return $ref;
    }
}
