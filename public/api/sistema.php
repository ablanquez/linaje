<?php
declare(strict_types=1);

/**
 * /api/sistema.php  —  información del sistema para el panel (bloque «Sistema»).
 * ----------------------------------------------------------------------------
 * SOLO LECTURA y SOLO EDICIÓN. Reúne, en JSON, lo mismo que muestra salud.php
 * pero para pintarlo dentro del panel: versión del esquema, estado de la
 * instalación, comprobaciones del entorno (PHP, extensiones, permisos) y un
 * chequeo real de la conexión a la base de datos.
 *
 * No modifica nada. Reutiliza Instalador::requisitos()/estado() (las mismas
 * piezas del asistente), así el panel y el instalador dicen lo mismo.
 */

require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/http.php';
require __DIR__ . '/../../src/Auth.php';
require __DIR__ . '/../../src/Instalador.php';

Auth::exigirEdicion();

$pdo = bd();

// Versión del esquema guardada (marca de instalación en arb_ajustes).
$versionEsquema = null;
try {
    $v = $pdo->query("SELECT valor FROM arb_ajustes WHERE clave = 'version_esquema'")->fetchColumn();
    $versionEsquema = $v === false ? null : (string) $v;
} catch (Throwable $e) { /* la tabla existe seguro si estamos aquí; por prudencia */ }

// Comprobación real de la conexión (versión del motor + nombre de la BD).
$bd = ['ok' => false, 'detalle' => ''];
try {
    $ver    = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    $nombre = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $bd = ['ok' => true, 'detalle' => 'Conectado a «' . ($nombre !== '' ? $nombre : '(sin seleccionar)') . '» — ' . $ver];
} catch (Throwable $e) {
    // SEC-05: incluso siendo un endpoint de admin, el detalle va al log, no a la respuesta.
    Seguridad::registrarError($e, 'sistema');
    $bd = ['ok' => false, 'detalle' => 'No se pudo consultar la base de datos.'];
}

responder([
    'ok'              => true,
    'version_app'     => Instalador::VERSION_ESQUEMA,   // versión del esquema que instala esta app
    'version_esquema' => $versionEsquema,               // versión realmente instalada en la BD
    'php'             => PHP_VERSION,
    'requisitos'      => Instalador::requisitos(),       // [{ok, critico, titulo, detalle}, ...]
    'bd'              => $bd,
    'instalacion'     => Instalador::estado(),           // {config, conecta, tablas, instalado}
]);
