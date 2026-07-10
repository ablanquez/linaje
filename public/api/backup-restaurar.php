<?php
declare(strict_types=1);

/**
 * /api/backup-restaurar.php — RESTAURAR desde un archivo SUBIDO. SOLO rol EDICIÓN.
 * ------------------------------------------------------------------------------
 *   POST multipart, campo "copia" = archivo .json de una copia de seguridad.
 *   → { ok, restaurado } | error.
 *
 * DESTRUCTIVO: valida el archivo, genera una copia automática del estado actual
 * ('previo') como red de seguridad, y restaura (BD en transacción con reversión;
 * fotos por carpeta temporal + swap). CSRF por cabecera X-CSRF-Token.
 */

require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/http.php';
require __DIR__ . '/../../src/Auth.php';
require __DIR__ . '/../../src/Fotos.php';
require __DIR__ . '/../../src/Backup.php';

Auth::exigirEdicion();
exigirMetodo('POST');
Auth::exigirCsrf();

try {
    if (!isset($_FILES['copia']) || !is_array($_FILES['copia'])) {
        throw new InvalidArgumentException('No se recibió ningún archivo de copia.');
    }
    $f = $_FILES['copia'];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('La subida del archivo falló (código ' . ($f['error'] ?? '?') . ').');
    }
    if (!is_uploaded_file($f['tmp_name'])) {
        throw new InvalidArgumentException('Archivo de subida no válido.');
    }
    $contenido = file_get_contents($f['tmp_name']);
    if ($contenido === false) {
        throw new InvalidArgumentException('No se pudo leer el archivo subido.');
    }

    $copia = Backup::desdeCadena($contenido);      // decodifica + valida (tipo, versión, integridad, tamaño)
    Backup::generar(bd(), 'previo');               // red de seguridad ANTES de tocar nada
    Backup::restaurar(bd(), $copia);               // destructivo, transaccional

    responder(['ok' => true, 'restaurado' => $copia['manifest']['recuentos']]);
} catch (InvalidArgumentException $e) {
    responder(['ok' => false, 'error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    $ref = Seguridad::registrarError($e, 'backup-restaurar');   // SEC-05
    responder(['ok' => false, 'error' => 'No se pudo restaurar la copia.', 'ref' => $ref], 500);
}
