<?php
declare(strict_types=1);

/**
 * bootstrap.php — arranque común del backend.
 * -------------------------------------------
 * Lo incluye TODO punto de entrada PHP (la página de salud, y en el futuro los
 * endpoints de api/). Se encarga de:
 *   1. Definir las rutas base del proyecto.
 *   2. Cargar la configuración real (config/config.php), fuera del repo.
 *   3. Dejar preparada la conexión a la BD (Db), sin abrirla todavía.
 *
 * No abre la conexión aquí: se abre de forma perezosa la primera vez que se
 * llama a bd() / Db::pdo(). Así incluir bootstrap nunca falla por la BD.
 */

// ── Rutas base ──────────────────────────────────────────────────────────────
// bootstrap.php vive en src/, así que la raíz del proyecto es su carpeta padre.
define('RAIZ', dirname(__DIR__));
define('RUTA_CONFIG', RAIZ . '/config/config.php');

// ── Configuración ───────────────────────────────────────────────────────────
if (!is_file(RUTA_CONFIG)) {
    // Se lanza excepción (no exit) para que quien incluya bootstrap pueda
    // capturarla y mostrar un mensaje claro (p.ej. la página de salud).
    throw new RuntimeException(
        'Falta config/config.php. Copia config/config.example.php a '
        . 'config/config.php y rellena tus credenciales de conexión.'
    );
}

/** @var array $config Configuración del proyecto (ver config.example.php). */
$config = require RUTA_CONFIG;

// ── Errores (SEC-05): en PRODUCCIÓN nunca se muestran al cliente ──────────────
// Por defecto display_errors=Off (seguro): el detalle de un error se registra en
// el log del servidor, al usuario le llega un mensaje genérico. Solo se activa la
// visualización si config.php declara 'debug' => true (entorno LOCAL de pruebas).
$debug = !empty($config['debug']);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Cabeceras de seguridad + registro de errores (disponibles en todo el backend).
require_once RAIZ . '/src/Seguridad.php';

// ── Base de datos ────────────────────────────────────────────────────────────
require_once RAIZ . '/src/Db.php';
Db::configurar($config);

/**
 * Atajo cómodo para obtener la conexión PDO desde cualquier sitio.
 * La conexión se crea la primera vez que se llama.
 */
function bd(): PDO
{
    return Db::pdo();
}
