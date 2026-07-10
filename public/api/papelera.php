<?php
declare(strict_types=1);

/**
 * /api/papelera.php  —  ver y gestionar la papelera. SOLO rol EDICIÓN.
 * -------------------------------------------------------------------
 *   GET                                   → { ok, personas:[...] }  (listar)
 *   POST { accion:'restaurar', id }       → { ok }
 *   POST { accion:'eliminar',  id }       → { ok }   (borrado físico + foto)
 *   POST { accion:'vaciar' }              → { ok, eliminadas:N }
 *
 * Escrituras: exigen rol edición + CSRF. Lectura (GET): exige rol edición.
 */

require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/http.php';
require __DIR__ . '/../../src/Auth.php';
require __DIR__ . '/../../src/Fotos.php';
require __DIR__ . '/../../src/Papelera.php';

Auth::exigirEdicion();   // toda la papelera es cosa de admin (401/403)

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodo === 'GET') {
    responder(['ok' => true, 'personas' => Papelera::listar(bd())]);
}

if ($metodo !== 'POST') {
    responder(['ok' => false, 'error' => 'Método no permitido.'], 405);
}

Auth::exigirCsrf();

$in = leerEntradaJsonOResponder();
$accion = $in['accion'] ?? '';

ejecutarEscritura(bd(), function (PDO $pdo) use ($in, $accion) {
    switch ($accion) {
        case 'restaurar':
            Papelera::restaurar($pdo, (int) ($in['id'] ?? 0));
            return null;
        case 'eliminar':
            Papelera::eliminarDefinitivo($pdo, (int) ($in['id'] ?? 0));
            return null;
        case 'vaciar':
            return ['eliminadas' => Papelera::vaciar($pdo)];
        default:
            throw new InvalidArgumentException('Acción no reconocida: ' . $accion);
    }
});
