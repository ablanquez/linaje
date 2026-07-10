<?php
declare(strict_types=1);

/**
 * POST /api/logout.php  —  cerrar sesión.
 */
require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/http.php';
require __DIR__ . '/../../src/Auth.php';

exigirMetodo('POST');
// SEC-20: cerrar sesión es una escritura de estado → exige token CSRF válido, para
// que un tercero no pueda forzar el cierre de sesión con una petición cruzada.
Auth::exigirCsrf();
Auth::logout();
responder(['ok' => true]);
