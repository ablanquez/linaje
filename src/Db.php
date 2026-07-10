<?php
declare(strict_types=1);

/**
 * Db — conexión única (singleton) a la base de datos vía PDO.
 * -----------------------------------------------------------
 * Objetivo: que TODO el backend hable con la BD por un solo sitio, con la
 * configuración correcta desde el principio:
 *   - charset utf8mb4 (acentos y emojis sin sorpresas),
 *   - ERRMODE_EXCEPTION (los errores lanzan excepción, no pasan desapercibidos),
 *   - EMULATE_PREPARES=false (consultas preparadas de verdad en el servidor),
 *   - FETCH_ASSOC por defecto (arrays asociativos).
 *
 * Uso:
 *   Db::configurar($config);   // lo hace src/bootstrap.php al arrancar
 *   $pdo = Db::pdo();          // conexión lista; se crea una sola vez
 *
 * Todas las consultas del proyecto deben usar SIEMPRE sentencias preparadas
 * ($pdo->prepare(...) con marcadores), nunca concatenar valores en el SQL.
 */
final class Db
{
    /** Configuración recibida de bootstrap (array con la clave 'db'). */
    private static ?array $config = null;

    /** Conexión PDO única, creada de forma perezosa la primera vez. */
    private static ?PDO $pdo = null;

    /** Guarda la configuración. La conexión aún NO se abre aquí. */
    public static function configurar(array $config): void
    {
        self::$config = $config;
    }

    /** Devuelve la conexión PDO, creándola la primera vez que se pide. */
    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        if (self::$config === null || !isset(self::$config['db'])) {
            throw new RuntimeException(
                'Db sin configurar: llama a Db::configurar($config) antes de Db::pdo() '
                . '(normalmente lo hace src/bootstrap.php).'
            );
        }

        $db  = self::$config['db'];
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $db['host'],
            (int) $db['puerto'],
            $db['nombre'],
            $db['charset']
        );

        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        self::$pdo = new PDO($dsn, $db['usuario'], $db['clave'], $opciones);
        return self::$pdo;
    }
    // CAL-09: se retiró Db::prefijo() (sin uso). El prefijo 'arb_' es fijo en el
    // esquema y en las constantes de cada clase; no se concatena desde configuración.
    // Si algún día se hiciera, debe validarse contra una lista blanca (no interpolar
    // valor de config directamente en SQL).
}
