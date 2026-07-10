<?php
declare(strict_types=1);

/**
 * POST /api/guardar.php  —  guardar un LOTE de cambios de forma ATÓMICA (INT-03).
 * -----------------------------------------------------------------------------
 * El editor calcula la diferencia entre el árbol y lo guardado y la manda ENTERA
 * aquí. Todo se aplica dentro de UNA sola transacción (ejecutarEscritura): si algo
 * falla, se revierte TODO → nunca quedan estados a medias (p.ej. una persona creada
 * cuya filiación fue rechazada). Antes, cada operación era un endpoint/ transacción
 * aparte y un fallo intermedio dejaba restos.
 *
 * Cuerpo JSON (ids de persona: entero real, o UUID temporal de una creación de ESTE
 * lote, que el servidor resuelve):
 *   {
 *     "creaciones": [ { "temp": "<uuid>", "datos": { ...family-chart... } }, ... ],
 *     "ediciones":  [ { "id": <id>, "datos": { ... } }, ... ],
 *     "filAdd":     [ { "progenitor": <id>, "hijo": <id> }, ... ],
 *     "parAdd":     [ { "a": <id>, "b": <id> }, ... ],
 *     "filDel":     [ { "progenitor": <id>, "hijo": <id> }, ... ],
 *     "parDel":     [ { "a": <id>, "b": <id> }, ... ],
 *     "borrados":   [ <id>, ... ]
 *   }
 * Responde { ok:true, ids: { "<uuid>": "<idReal>", ... } } para que el editor
 * resuelva los ids nuevos en cambios encolados posteriores.
 */

require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/http.php';
require __DIR__ . '/../../src/Auth.php';
require __DIR__ . '/../../src/Fotos.php';        // borrado diferido de fotos al editar
// require_once: Personas ya carga Relaciones y Arbol (evita redeclarar la clase).
require_once __DIR__ . '/../../src/Arbol.php';
require_once __DIR__ . '/../../src/Personas.php';
require_once __DIR__ . '/../../src/Relaciones.php';

exigirMetodo('POST');
Auth::exigirEdicion();   // escribir exige rol edición (401/403)
Auth::exigirCsrf();      // y token CSRF válido

$in = leerEntradaJsonOResponder();

// Tope defensivo de tamaño del lote (evita abusos). Un guardado real es pequeño.
$MAX_OPS = 5000;
$total = 0;
foreach (['creaciones', 'restauraciones', 'ediciones', 'filAdd', 'parAdd', 'filDel', 'parDel', 'borrados'] as $k) {
    $total += is_array($in[$k] ?? null) ? count($in[$k]) : 0;
}
if ($total > $MAX_OPS) {
    responder(['ok' => false, 'error' => 'El lote de cambios es demasiado grande.'], 400);
}

ejecutarEscritura(bd(), function (PDO $pdo) use ($in) {
    // CONC-02: cerrojo de árbol como PRIMERA sentencia → todo el lote es atómico y
    // coherente frente a otras escrituras estructurales simultáneas.
    Arbol::bloquear($pdo);

    $lista = static fn($k) => is_array($in[$k] ?? null) ? $in[$k] : [];

    // 1) Crear personas nuevas; mapear su id TEMPORAL (uuid del editor) → id real.
    $mapa = [];   // "<uuid>" => idReal (int)
    foreach ($lista('creaciones') as $c) {
        $temp = (string) ($c['temp'] ?? '');
        if ($temp === '') throw new InvalidArgumentException('Creación sin identificador temporal.');
        $mapa[$temp] = Personas::crear($pdo, (array) ($c['datos'] ?? []));
    }

    // Resuelve un id referenciado: si es un temporal de este lote → su id real; si ya
    // es un entero → tal cual; cualquier otra cosa (uuid no creado) → error claro.
    $res = static function ($id) use ($mapa): int {
        $s = (string) $id;
        if (array_key_exists($s, $mapa)) return $mapa[$s];
        if (ctype_digit($s) && (int) $s > 0) return (int) $s;
        throw new InvalidArgumentException('Referencia a una persona no resuelta: ' . $s);
    };

    // 1b) RESTAURAR personas creadas esta sesión que un DESHACER envió a la papelera
    //     y que un REHACER reintroduce (se reutiliza su identidad en vez de duplicarla).
    //     Va ANTES de sus vínculos: al reactivarlas, sus aristas "dormidas" vuelven a
    //     estar activas, y el filAdd de esas mismas aristas resulta idempotente.
    foreach ($lista('restauraciones') as $idr) {
        Personas::restaurar($pdo, $res($idr));
    }

    // 2) Editar personas existentes.
    foreach ($lista('ediciones') as $e) {
        Personas::editar($pdo, $res($e['id'] ?? 0), (array) ($e['datos'] ?? []));
    }
    // 3) Vínculos añadidos (filiación y pareja).
    foreach ($lista('filAdd') as $f) {
        Relaciones::anadirFiliacion($pdo, $res($f['progenitor'] ?? 0), $res($f['hijo'] ?? 0));
    }
    foreach ($lista('parAdd') as $p) {
        Relaciones::anadirPareja($pdo, $res($p['a'] ?? 0), $res($p['b'] ?? 0));
    }
    // 4) Vínculos quitados.
    foreach ($lista('filDel') as $f) {
        Relaciones::quitarFiliacion($pdo, $res($f['progenitor'] ?? 0), $res($f['hijo'] ?? 0));
    }
    foreach ($lista('parDel') as $p) {
        Relaciones::quitarPareja($pdo, $res($p['a'] ?? 0), $res($p['b'] ?? 0));
    }
    // 5) Personas borradas (soft-delete → papelera), al final.
    foreach ($lista('borrados') as $id) {
        Personas::borrar($pdo, $res($id));
    }

    // Devolver el mapa temporal→real (como cadenas) para el editor.
    return ['ids' => array_map('strval', $mapa)];
});
