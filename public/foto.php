<?php
declare(strict_types=1);

/**
 * GET /foto.php?persona=<id>  —  "portero" de fotos.
 * --------------------------------------------------
 * Sirve la imagen de una persona LEYENDO su archivo desde almacen/fotos/ (que
 * está fuera de public/ y NO es accesible por URL directa). Así la única vía de
 * acceder a las fotos es a través de este PHP.
 *
 * Respuestas: la imagen (JPEG) si existe; 404 si la persona no existe, no tiene
 * foto, o el archivo falta; 400 si falta el parámetro.
 *
 * ───────────────────────────────────────────────────────────────────────────
 * PASO 8 (login): aquí irá el candado de sesión. Basta con, tras cargar el
 * bootstrap, comprobar que hay sesión válida y, si no, responder 403/redirigir.
 * Se deja este punto marcado para añadirlo sin tocar el resto:
 *     // if (!haySesion()) { http_response_code(403); exit; }
 * De momento (local) va abierto.
 * ───────────────────────────────────────────────────────────────────────────
 */

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/Fotos.php';
require __DIR__ . '/../src/Auth.php';

Seguridad::cabecerasBase();

// Candado activado (PASO 8): ver una foto exige sesión (cualquier rol). Sin ella → 401.
$usuario = Auth::exigirSesion();

$id = (int) ($_GET['persona'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Falta el parámetro persona.';
    exit;
}

// SEC-14 (IDOR): solo la EDICIÓN puede ver fotos de personas en la PAPELERA (donde
// vive el gestor de papelera). Un rol de LECTURA, iterando ?persona=N, no debe poder
// acceder a las fotos de personas borradas: para lectura se filtra borrado_en IS NULL.
$puedeVerPapelera = (($usuario['rol'] ?? '') === 'edicion');
$sql = $puedeVerPapelera
    ? 'SELECT avatar FROM arb_personas WHERE id = :id'
    : 'SELECT avatar FROM arb_personas WHERE id = :id AND borrado_en IS NULL';
$st = bd()->prepare($sql);
$st->execute(['id' => $id]);
$avatar = $st->fetchColumn();

$ruta = $avatar ? Fotos::ruta((string) $avatar) : null;
if (!$ruta || !is_file($ruta)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Sin foto.';
    exit;
}

// Caché con revalidación: el navegador guarda la imagen pero comprueba por ETag
// (el nombre de archivo cambia al reemplazar la foto, así que nunca sirve una vieja).
$etag = '"' . md5((string) $avatar) . '"';
header('Cache-Control: private, max-age=0, must-revalidate');
header('ETag: ' . $etag);
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($ruta));
readfile($ruta);
