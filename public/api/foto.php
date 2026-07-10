<?php
declare(strict_types=1);

/**
 * POST /api/foto.php  —  subir una imagen (multipart/form-data).
 * -------------------------------------------------------------
 * Campo de archivo: "imagen".
 * Valida que sea una imagen real, la redimensiona con GD y la guarda como JPEG
 * en almacen/fotos/. Responde { ok:true, avatar:"<nombre>.jpg" }.
 *
 * NO asocia la foto a ninguna persona: el nombre devuelto se guarda en el campo
 * `avatar` de la persona por el flujo normal de guardado (api/persona.php). La
 * limpieza del archivo antiguo al reemplazar/quitar la hace Personas al escribir.
 *
 * SIN control de acceso todavía (login = PASO 8): en local está abierto.
 */

require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/http.php';
require __DIR__ . '/../../src/Auth.php';
require __DIR__ . '/../../src/Fotos.php';

exigirMetodo('POST');
Auth::exigirEdicion();   // subir foto exige rol edición (401/403)
Auth::exigirCsrf();      // y token CSRF válido

try {
    if (!isset($_FILES['imagen'])) {
        throw new InvalidArgumentException('No se recibió ninguna imagen (campo "imagen").');
    }
    $f = $_FILES['imagen'];

    // Errores de subida de PHP (tamaño, subida parcial…).
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $msg = ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE)
            ? 'La imagen supera el tamaño permitido por el servidor.'
            : 'No se pudo subir la imagen (código ' . $f['error'] . ').';
        throw new InvalidArgumentException($msg);
    }
    // Debe venir realmente de una subida HTTP (seguridad).
    if (!is_uploaded_file($f['tmp_name'])) {
        throw new InvalidArgumentException('Origen de archivo no válido.');
    }

    $nombre = Fotos::guardarDesdeArchivo($f['tmp_name']);
    responder(['ok' => true, 'avatar' => $nombre]);
} catch (InvalidArgumentException $e) {
    responder(['ok' => false, 'error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    $ref = Seguridad::registrarError($e, 'foto-subir');   // SEC-05
    responder(['ok' => false, 'error' => 'No se pudo procesar la imagen.', 'ref' => $ref], 500);
}
