<?php
declare(strict_types=1);

/**
 * POST /api/persona.php  —  crear / editar / borrar (soft) una persona.
 * ---------------------------------------------------------------------
 * Cuerpo JSON:
 *   crear : { "accion":"crear",  "datos": { ...campos family-chart... } }
 *           → responde { ok:true, id:"<nuevo id>" }
 *   editar: { "accion":"editar", "id":<int>, "datos": { ... } }  → { ok:true }
 *   borrar: { "accion":"borrar", "id":<int> }                    → { ok:true }
 *
 * Solo lectura/escritura de datos de la persona; los vínculos van en relacion.php.
 * SIN control de acceso todavía (login = PASO 8): en local la edición está abierta.
 */

require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/http.php';
require __DIR__ . '/../../src/Auth.php';
require __DIR__ . '/../../src/Fotos.php';       // limpieza del archivo antiguo al cambiar avatar
require __DIR__ . '/../../src/Personas.php';

exigirMetodo('POST');
Auth::exigirEdicion();   // escribir exige rol edición (401/403). El servidor manda.
Auth::exigirCsrf();      // y token CSRF válido.

$in = leerEntradaJsonOResponder();

$accion = $in['accion'] ?? '';

ejecutarEscritura(bd(), function (PDO $pdo) use ($in, $accion) {
    switch ($accion) {
        case 'crear':
            $id = Personas::crear($pdo, (array) ($in['datos'] ?? []));
            return ['id' => (string) $id];          // el front adopta este id

        case 'editar':
            Personas::editar($pdo, (int) ($in['id'] ?? 0), (array) ($in['datos'] ?? []));
            return null;

        case 'borrar':
            Personas::borrar($pdo, (int) ($in['id'] ?? 0));
            return null;

        default:
            throw new InvalidArgumentException('Acción no reconocida: ' . $accion);
    }
});
