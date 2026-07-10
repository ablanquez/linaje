<?php
declare(strict_types=1);

/**
 * /api/mantenimiento.php  —  herramientas de datos del panel. SOLO EDICIÓN.
 * ------------------------------------------------------------------------
 * Por ahora: «Personas sin nombre» (restos con el nombre vacío), que se pueden
 * renombrar o mandar a la papelera desde el panel de administración.
 *
 *   GET                                         → { ok, personas:[...] }
 *   POST { accion:'renombrar', id, nombre, apellido1, apellido2? } → { ok }
 *   POST { accion:'papelera',  id }             → { ok }   (soft-delete)
 *
 * Lectura y escritura exigen rol edición; las escrituras además CSRF.
 */

require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/http.php';
require __DIR__ . '/../../src/Auth.php';
require __DIR__ . '/../../src/Fotos.php';
require __DIR__ . '/../../src/Personas.php';

Auth::exigirEdicion();

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodo === 'GET') {
    // Recuento informativo de fotos huérfanas (subidas y nunca asociadas a nadie),
    // para que el panel pueda ofrecer la limpieza (SEC-15). Solo cuenta las que ya
    // superan el periodo de gracia (no se contabilizan subidas muy recientes).
    $huerfanas = 0;
    try {
        $archivos = Fotos::listarArchivos();
        if ($archivos) {
            $refs = [];
            foreach (bd()->query('SELECT DISTINCT avatar FROM arb_personas WHERE avatar IS NOT NULL')
                         ->fetchAll(PDO::FETCH_COLUMN) as $a) {
                $refs[(string) $a] = true;
            }
            $limite = time() - 86400;
            foreach ($archivos as $nombre => $mtime) {
                if (!isset($refs[$nombre]) && $mtime <= $limite) $huerfanas++;
            }
        }
    } catch (Throwable $e) { /* informativo: si falla, se reporta 0 */ }
    responder(['ok' => true, 'personas' => Personas::sinNombre(bd()), 'fotos_huerfanas' => $huerfanas]);
}

if ($metodo !== 'POST') {
    responder(['ok' => false, 'error' => 'Método no permitido.'], 405);
}

Auth::exigirCsrf();

$in = leerEntradaJsonOResponder();
$accion = $in['accion'] ?? '';

ejecutarEscritura(bd(), function (PDO $pdo) use ($in, $accion) {
    switch ($accion) {
        case 'renombrar':
            Personas::renombrar(
                $pdo,
                (int) ($in['id'] ?? 0),
                (string) ($in['nombre'] ?? ''),
                (string) ($in['apellido1'] ?? ''),
                (string) ($in['apellido2'] ?? '')
            );
            return null;
        case 'papelera':
            Personas::borrar($pdo, (int) ($in['id'] ?? 0));
            return null;
        case 'limpiar_fotos':
            // SEC-15: borra las fotos huérfanas con más de 24 h de antigüedad.
            return Fotos::purgarHuerfanas($pdo);   // { revisadas, borradas, archivos }
        default:
            throw new InvalidArgumentException('Acción no reconocida: ' . $accion);
    }
});
