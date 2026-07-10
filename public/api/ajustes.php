<?php
declare(strict_types=1);

/**
 * POST /api/ajustes.php  —  guarda ajustes del árbol (título/subtítulo/main_id).
 * ----------------------------------------------------------------------------
 * Cuerpo JSON con el subconjunto a guardar, p.ej.:
 *   { "titulo":"...", "subtitulo":"..." }
 *   { "main_id": 5 }
 * Responde { ok:true }.
 *
 * SIN control de acceso todavía (login = PASO 8): en local está abierto.
 */

require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/http.php';
require __DIR__ . '/../../src/Auth.php';
require __DIR__ . '/../../src/Ajustes.php';

exigirMetodo('POST');
Auth::exigirEdicion();   // escribir exige rol edición (401/403)
Auth::exigirCsrf();      // y token CSRF válido

$in = leerEntradaJsonOResponder();

ejecutarEscritura(bd(), function (PDO $pdo) use ($in) {
    Ajustes::guardar($pdo, $in);
    return null;
});
