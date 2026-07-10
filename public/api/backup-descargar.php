<?php
declare(strict_types=1);

/**
 * /api/backup-descargar.php — descarga una copia guardada. SOLO rol EDICIÓN.
 * -------------------------------------------------------------------------
 *   GET ?archivo=<nombre>  → descarga el archivo JSON de la copia.
 *
 * Es un "portero": exige sesión de edición y sirve el archivo desde
 * almacen/backups/ (fuera de public/, no accesible por URL directa).
 */

require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/Auth.php';
require __DIR__ . '/../../src/Backup.php';

Auth::exigirEdicion();   // descargar copias es cosa de admin (401/403)

$archivo = (string) ($_GET['archivo'] ?? '');
$ruta = Backup::ruta($archivo);
if (!$ruta || !is_file($ruta)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Esa copia no existe.'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . basename($ruta) . '"');
header('Content-Length: ' . (string) (filesize($ruta) ?: 0));
header('X-Content-Type-Options: nosniff');
readfile($ruta);
exit;
