<?php
declare(strict_types=1);

/**
 * POST /api/relacion.php  —  añadir / quitar un vínculo (arista).
 * --------------------------------------------------------------
 * Cuerpo JSON:
 *   filiación: { "tipo":"filiacion", "operacion":"anadir"|"quitar",
 *                "progenitor_id":<int>, "hijo_id":<int> }
 *   pareja   : { "tipo":"pareja",    "operacion":"anadir"|"quitar",
 *                "persona_a_id":<int>, "persona_b_id":<int> }
 * Responde { ok:true }.
 *
 * Idempotente (añadir dos veces no duplica; quitar algo inexistente no falla).
 * SIN control de acceso todavía (login = PASO 8): en local la edición está abierta.
 */

require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/http.php';
require __DIR__ . '/../../src/Auth.php';
require __DIR__ . '/../../src/Relaciones.php';

exigirMetodo('POST');
Auth::exigirEdicion();   // escribir exige rol edición (401/403)
Auth::exigirCsrf();      // y token CSRF válido

$in = leerEntradaJsonOResponder();

$tipo = $in['tipo'] ?? '';
$op   = $in['operacion'] ?? '';

ejecutarEscritura(bd(), function (PDO $pdo) use ($in, $tipo, $op) {
    if ($tipo === 'filiacion') {
        $p = (int) ($in['progenitor_id'] ?? 0);
        $h = (int) ($in['hijo_id'] ?? 0);
        if ($op === 'anadir')      Relaciones::anadirFiliacion($pdo, $p, $h);
        elseif ($op === 'quitar')  Relaciones::quitarFiliacion($pdo, $p, $h);
        else throw new InvalidArgumentException('Operación no reconocida: ' . $op);
    } elseif ($tipo === 'pareja') {
        $a = (int) ($in['persona_a_id'] ?? 0);
        $b = (int) ($in['persona_b_id'] ?? 0);
        if ($op === 'anadir')      Relaciones::anadirPareja($pdo, $a, $b);
        elseif ($op === 'quitar')  Relaciones::quitarPareja($pdo, $a, $b);
        else throw new InvalidArgumentException('Operación no reconocida: ' . $op);
    } else {
        throw new InvalidArgumentException('Tipo de vínculo no reconocido: ' . $tipo);
    }
    return null;
});
