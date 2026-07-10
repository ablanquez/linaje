<?php
declare(strict_types=1);

/**
 * Auth — sesión, roles y guardas de acceso (PASO 8, ampliado en PASO 12).
 * -----------------------------------------------------------------------
 * Sesión PHP nativa. Guarda quién eres (persona_id) y tu rol ('lectura' o
 * 'edicion'). Las guardas (exigirSesion / exigirEdicion / exigirCsrf) son el
 * ÚNICO sitio que decide el acceso, y todas consultan controlActivo().
 *
 * INTERRUPTOR CON/SIN CONTROL DE ACCESO (PASO 12): controlActivo() lee ahora el
 * ajuste `acceso_activo` de la BD (vía Acceso), en vez de estar fijo. Si el
 * árbol es abierto (acceso_activo=0), las guardas dejan pasar sin romper nada.
 * El instalador y el futuro panel escriben ese ajuste por el mismo punto (Acceso).
 */
require_once __DIR__ . '/Acceso.php';
require_once __DIR__ . '/Seguridad.php';

final class Auth
{
    /** Caché del interruptor durante la petición (evita releer la BD varias veces). */
    private static ?bool $controlActivoCache = null;

    /**
     * ¿Está activo el control de acceso? Lee `acceso_activo` de la BD (Acceso).
     * A prueba de fallos: si la BD no responde, devuelve TRUE (se protege el árbol).
     * El resultado se cachea durante la petición.
     */
    public static function controlActivo(): bool
    {
        if (self::$controlActivoCache !== null) return self::$controlActivoCache;
        try {
            self::$controlActivoCache = Acceso::controlActivo(bd());
        } catch (Throwable $e) {
            self::$controlActivoCache = true;   // ante cualquier problema, se protege
        }
        return self::$controlActivoCache;
    }

    /** Arranca la sesión PHP con cookies endurecidas (una sola vez). */
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        // SEC-20: cookie Secure también cuando el TLS lo TERMINA UN PROXY (Hostinger),
        // donde $_SERVER['HTTPS'] puede venir vacío pero el proxy marca el esquema.
        $https = Seguridad::esHttps();
        @ini_set('session.use_strict_mode', '1');   // rechaza ids de sesión no generados por el servidor
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,        // la cookie no es accesible por JS
            'secure'   => $https,      // bajo HTTPS (incluso tras proxy TLS) solo por canal seguro
            'samesite' => 'Lax',       // mitiga CSRF de navegación
        ]);
        session_name('genealogia');
        session_start();
    }

    /** Devuelve ['persona_id'=>int, 'rol'=>string] o null si no hay sesión. */
    public static function usuario(): ?array
    {
        self::iniciar();
        if (empty($_SESSION['persona_id']) || empty($_SESSION['rol'])) return null;
        return ['persona_id' => (int) $_SESSION['persona_id'], 'rol' => (string) $_SESSION['rol']];
    }

    /** Registra la sesión tras un login correcto (regenera el id: anti-fijación). */
    public static function login(int $personaId, string $rol): void
    {
        self::iniciar();
        session_regenerate_id(true);
        $_SESSION['persona_id'] = $personaId;
        $_SESSION['rol']        = $rol;
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    /** Cierra la sesión por completo. */
    public static function logout(): void
    {
        self::iniciar();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /** Token CSRF de la sesión (se crea si no existe). */
    public static function csrf(): string
    {
        self::iniciar();
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf'];
    }

    // ── Guardas ──────────────────────────────────────────────────────────────

    /**
     * Exige poder LEER. Con control activo: sesión obligatoria (cualquier rol).
     * En árbol ABIERTO (acceso_activo=0): lectura PÚBLICA (identidad anónima de
     * solo lectura). Editar/administrar NUNCA pasa por aquí: usa exigirEdicion().
     */
    public static function exigirSesion(): array
    {
        $u = self::usuario();
        if ($u) return $u;
        if (!self::controlActivo()) return ['persona_id' => 0, 'rol' => 'lectura']; // abierto = ver sin login
        self::cortar(401, 'No has iniciado sesión.');
    }

    /**
     * Exige rol de EDICIÓN con SESIÓN REAL, SIEMPRE — también en árbol abierto.
     * El modo abierto es para LECTURA pública, NO para que cualquiera administre:
     * editar, borrar, restaurar, cambiar claves o tocar el interruptor exigen
     * iniciar sesión con la clave de edición. (Cierra el secuestro anónimo SEC-02.)
     */
    public static function exigirEdicion(): array
    {
        $u = self::usuario();
        if (!$u) self::cortar(401, 'No has iniciado sesión.');
        if ($u['rol'] !== 'edicion') self::cortar(403, 'No tienes permiso de edición.');
        return $u;
    }

    /**
     * Exige un token CSRF válido (cabecera X-CSRF-Token) en TODA escritura. Como
     * las escrituras exigen ya sesión de edición (exigirEdicion), aquí SIEMPRE hay
     * sesión y token: no se salta ni en árbol abierto.
     */
    public static function exigirCsrf(): void
    {
        self::iniciar();
        $tok = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (empty($_SESSION['csrf']) || !is_string($tok) || !hash_equals($_SESSION['csrf'], $tok)) {
            self::cortar(403, 'Token de seguridad (CSRF) inválido o ausente.');
        }
    }

    /** Responde un error JSON y termina. */
    private static function cortar(int $code, string $msg): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $msg, 'necesita_login' => ($code === 401)], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
