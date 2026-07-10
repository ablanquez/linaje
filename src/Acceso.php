<?php
declare(strict_types=1);

/**
 * Acceso — control de acceso y claves del árbol, guardados en la BD (PASO 12).
 * ---------------------------------------------------------------------------
 * ANTES (PASO 8) el interruptor de login vivía fijo en Auth::controlActivo() y
 * las claves (hashes) en config/config.php. Eso impedía cambiarlos desde la app
 * y, con el auto-deploy de GitHub, config.php se resubía pisando cualquier
 * cambio. Por eso el PASO 12 los mueve a la tabla `arb_ajustes` (clave/valor):
 *
 *   acceso_activo       → '1' (login obligatorio) | '0' (árbol abierto)
 *   clave_edicion_hash  → hash password_hash() de la clave de EDICIÓN (admin)
 *   clave_lectura_hash  → hash password_hash() de la clave de LECTURA
 *
 * Las credenciales de CONEXIÓN a la BD siguen en config.php (como wp-config.php):
 * son el "cómo conecto", no pueden vivir dentro de la propia BD.
 *
 * Este es el ÚNICO punto que lee/escribe estos ajustes. Lo usan hoy el login
 * (api/login.php), las guardas (Auth::controlActivo) y el instalador
 * (src/Instalador.php); mañana lo usará igual el panel de administración, sin
 * reescribir nada. Las claves NUNCA se guardan ni viajan en claro: solo el hash.
 */
final class Acceso
{
    /** Claves de ajuste usadas por esta clase (para no repetir literales sueltos). */
    public const K_CONTROL  = 'acceso_activo';
    public const K_EDICION  = 'clave_edicion_hash';
    public const K_LECTURA  = 'clave_lectura_hash';

    /**
     * ¿Está activo el control de acceso (login obligatorio)?
     * Por defecto TRUE si el ajuste no existe: ante la duda, se protege el árbol.
     */
    public static function controlActivo(PDO $pdo): bool
    {
        $v = self::leer($pdo, self::K_CONTROL);
        if ($v === null) return true;          // sin ajuste → protegido por defecto
        return $v !== '0';                      // '0' = abierto; cualquier otra cosa = protegido
    }

    /**
     * Comprueba una clave en claro contra los hashes guardados y devuelve el ROL
     * que concede ('edicion' | 'lectura') o null si no coincide con ninguna.
     * La de edición tiene prioridad (si alguien pusiera la misma en ambas).
     */
    public static function verificarClave(PDO $pdo, string $claveEnClaro): ?string
    {
        if ($claveEnClaro === '') return null;
        $hEd = self::leer($pdo, self::K_EDICION);
        if ($hEd && password_verify($claveEnClaro, $hEd)) return 'edicion';
        $hLe = self::leer($pdo, self::K_LECTURA);
        if ($hLe && password_verify($claveEnClaro, $hLe)) return 'lectura';
        return null;
    }

    // ── Escritura (instalador y futuro panel) ────────────────────────────────

    /** Activa o desactiva el control de acceso. */
    public static function establecerControl(PDO $pdo, bool $activo): void
    {
        self::escribir($pdo, self::K_CONTROL, $activo ? '1' : '0');
    }

    /**
     * Fija la clave de un rol ('edicion' | 'lectura') a partir de la clave EN CLARO.
     * Guarda solo el hash (password_hash, coste por defecto). Cadena vacía = error.
     */
    public static function establecerClave(PDO $pdo, string $rol, string $claveEnClaro): void
    {
        $clave = self::claveAjuste($rol);
        $claveEnClaro = trim($claveEnClaro);
        if ($claveEnClaro === '') {
            throw new InvalidArgumentException('La clave no puede estar vacía.');
        }
        if (mb_strlen($claveEnClaro) < 8) {
            throw new InvalidArgumentException('La clave es demasiado corta (mínimo 8 caracteres).');
        }
        self::escribir($pdo, $clave, password_hash($claveEnClaro, PASSWORD_DEFAULT));
    }

    /** ¿Hay al menos la clave de edición configurada? (útil para diagnósticos). */
    public static function hayClaveEdicion(PDO $pdo): bool
    {
        return self::leer($pdo, self::K_EDICION) !== null;
    }

    /** ¿Hay clave de lectura configurada? */
    public static function hayClaveLectura(PDO $pdo): bool
    {
        return self::leer($pdo, self::K_LECTURA) !== null;
    }

    /**
     * REAUTENTICACIÓN para cambios sensibles (cambiar claves, tocar el interruptor).
     * Exige SIEMPRE que $claveActual sea la clave de EDICIÓN vigente —también en
     * árbol ABIERTO—: administrar no es una acción pública, y así, aunque alguien
     * encuentre una sesión abierta, no puede cambiar claves sin conocer la actual.
     * Si no hay clave de edición configurada, no hay forma de reautenticar y el
     * cambio no se permite (secuestro anónimo SEC-02 cerrado por diseño).
     */
    public static function reautenticar(PDO $pdo, string $claveActual): void
    {
        if (self::verificarClave($pdo, $claveActual) !== 'edicion') {
            throw new InvalidArgumentException('La clave de edición actual no es correcta.');
        }
    }

    // ── Interno ──────────────────────────────────────────────────────────────

    /** Traduce el rol a la clave de ajuste correspondiente. */
    private static function claveAjuste(string $rol): string
    {
        if ($rol === 'edicion') return self::K_EDICION;
        if ($rol === 'lectura') return self::K_LECTURA;
        throw new InvalidArgumentException("Rol desconocido: '$rol'.");
    }

    /** Lee un valor de arb_ajustes o null si no existe. */
    private static function leer(PDO $pdo, string $clave): ?string
    {
        $st = $pdo->prepare('SELECT valor FROM arb_ajustes WHERE clave = :c');
        $st->execute(['c' => $clave]);
        $v = $st->fetchColumn();
        return $v === false ? null : (string) $v;
    }

    /** Upsert de un valor en arb_ajustes. */
    private static function escribir(PDO $pdo, string $clave, string $valor): void
    {
        $st = $pdo->prepare(
            'INSERT INTO arb_ajustes (clave, valor) VALUES (:c, :v)
             ON DUPLICATE KEY UPDATE valor = :v2'
        );
        $st->execute(['c' => $clave, 'v' => $valor, 'v2' => $valor]);
    }
}
