<?php
declare(strict_types=1);

/**
 * /api/backup.php — copias de seguridad (JSON). SOLO rol EDICIÓN.
 * --------------------------------------------------------------
 *   GET                               → { ok, copias:[{archivo,fecha,bytes,recuentos}] }
 *   POST { accion:'generar' }         → { ok, archivo, manifest }   (crea + guarda + retención)
 *   POST { accion:'eliminar', archivo}→ { ok }
 *   POST { accion:'restaurar', archivo}→ { ok, restaurado } (DESTRUCTIVO; hace copia previa)
 *
 * Escrituras: rol edición + CSRF. La restauración es destructiva: antes de tocar
 * nada genera una copia automática del estado actual ('previo') y restaura la BD
 * en transacción (reversión si falla → árbol intacto).
 */

require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/http.php';
require __DIR__ . '/../../src/Auth.php';
require __DIR__ . '/../../src/Fotos.php';
require __DIR__ . '/../../src/Backup.php';

Auth::exigirEdicion();

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodo === 'GET') {
    responder(['ok' => true, 'copias' => Backup::listar()]);
}
if ($metodo !== 'POST') {
    responder(['ok' => false, 'error' => 'Método no permitido.'], 405);
}

Auth::exigirCsrf();

try {
    $in = leerEntradaJson();
    $accion = $in['accion'] ?? '';

    switch ($accion) {
        case 'generar':
            responder(['ok' => true] + Backup::generar(bd()));
            break;

        case 'eliminar':
            Backup::eliminar((string) ($in['archivo'] ?? ''));
            responder(['ok' => true]);
            break;

        case 'restaurar':
            $archivo = (string) ($in['archivo'] ?? '');
            $ruta = Backup::ruta($archivo);
            if (!$ruta || !is_file($ruta)) {
                throw new InvalidArgumentException('Esa copia no existe.');
            }
            $copia = Backup::desdeCadena((string) file_get_contents($ruta));   // decodifica + valida
            Backup::generar(bd(), 'previo');                                   // red de seguridad
            Backup::restaurar(bd(), $copia);                                   // destructivo, transaccional
            responder(['ok' => true, 'restaurado' => $copia['manifest']['recuentos']]);
            break;

        default:
            throw new InvalidArgumentException('Acción no reconocida: ' . $accion);
    }
} catch (InvalidArgumentException $e) {
    responder(['ok' => false, 'error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    $ref = Seguridad::registrarError($e, 'backup');   // SEC-05: detalle al log, no al cliente
    responder(['ok' => false, 'error' => 'No se pudo completar la operación.', 'ref' => $ref], 500);
}
