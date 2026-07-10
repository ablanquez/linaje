<?php
declare(strict_types=1);

/**
 * GET /api/arbol.php  —  Lectura del árbol completo desde la base de datos.
 * ----------------------------------------------------------------------
 * Devuelve un JSON con los ajustes del árbol y TODAS las personas activas
 * (no borradas), en el formato que family-chart consume:
 *
 *   {
 *     "ajustes": { "main_id": "5", "titulo": "...", "subtitulo": "..." },
 *     "personas": [
 *       { "id": "1",
 *         "data": { "first name": "...", "last name": "...", ... },
 *         "rels": { "parents": [".."], "spouses": [".."], "children": [".."] } },
 *       ...
 *     ]
 *   }
 *
 * IDEA: en la BD las relaciones son "aristas" (filiación y pareja); aquí las
 * reconstruimos en las listas parents/spouses/children por persona. Los ajustes
 * (main_id = persona central, título, subtítulo) llegan en la misma petición.
 *
 * SOLO LECTURA: no modifica nada. La escritura llega en el PASO 6.
 * Fechas: se entregan TAL CUAL están guardadas ("AAAA" o "AAAA-MM-DD"), sin
 * convertir (el frontend ya las muestra como DD/MM/AAAA).
 * IDs: enteros en la BD, se envían como TEXTO (family-chart usa ids de cadena).
 */

require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/Auth.php';
require __DIR__ . '/../../src/Ajustes.php';

Auth::exigirSesion();   // leer el árbol exige sesión (cualquier rol). Sin ella → 401.

Seguridad::cabecerasBase();   // SEC-09: cabeceras de seguridad también en la lectura
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = bd();

    // ── 1) Personas activas (borrado_en IS NULL) ────────────────────────────
    // Mapa id => nodo family-chart, con rels vacías que iremos rellenando.
    $sqlPersonas = 'SELECT id, nombre, apellido1, apellido2, sexo, nacimiento,
                           fallecimiento, lugar, ocupacion, notas, avatar
                    FROM arb_personas
                    WHERE borrado_en IS NULL
                    ORDER BY id';
    $personas = $pdo->query($sqlPersonas)->fetchAll();

    $nodos = [];          // id (string) => nodo
    $activos = [];        // conjunto de ids activos, para filtrar aristas
    foreach ($personas as $p) {
        $id = (string) $p['id'];
        $activos[$id] = true;
        $nodos[$id] = [
            'id'   => $id,
            'data' => [
                'first name'  => $p['nombre'],
                'last name'   => $p['apellido1'],
                'last name 2' => $p['apellido2'],
                'gender'      => $p['sexo'],            // 'M' | 'F' | null
                'birthday'    => $p['nacimiento'],      // cadena tal cual
                'death'       => $p['fallecimiento'],   // cadena tal cual
                'place'       => $p['lugar'],
                'occupation'  => $p['ocupacion'],
                'notes'       => $p['notas'] ?? '',
                'avatar'      => $p['avatar'],          // nombre de archivo o null
            ],
            'rels' => [
                'parents'  => [],
                'spouses'  => [],
                'children' => [],
            ],
        ];
    }

    // Añade un id a una lista de rels sin duplicar y solo si el otro está activo.
    $vincular = static function (string $de, string $lista, string $a) use (&$nodos, $activos): void {
        if (!isset($nodos[$de]) || !isset($activos[$a])) {
            return;   // ignora aristas que apunten a personas inexistentes/borradas
        }
        if (!in_array($a, $nodos[$de]['rels'][$lista], true)) {
            $nodos[$de]['rels'][$lista][] = $a;
        }
    };

    // ── 2) Filiación (progenitor → hijo) ────────────────────────────────────
    // Para el hijo: el progenitor es un "parent". Para el progenitor: el hijo
    // es un "child". Una sola arista alimenta ambas listas.
    $filiacion = $pdo->query('SELECT progenitor_id, hijo_id FROM arb_filiacion')->fetchAll();
    foreach ($filiacion as $f) {
        $prog = (string) $f['progenitor_id'];
        $hijo = (string) $f['hijo_id'];
        $vincular($hijo, 'parents', $prog);
        $vincular($prog, 'children', $hijo);
    }

    // ── 3) Pareja (cónyuges) ────────────────────────────────────────────────
    // La arista es simétrica: cada uno es "spouse" del otro.
    $parejas = $pdo->query('SELECT persona_a_id, persona_b_id FROM arb_pareja')->fetchAll();
    foreach ($parejas as $par) {
        $a = (string) $par['persona_a_id'];
        $b = (string) $par['persona_b_id'];
        $vincular($a, 'spouses', $b);
        $vincular($b, 'spouses', $a);
    }

    // ── 4) Ajustes del árbol (clave/valor → objeto) ─────────────────────────
    // Se exponen SOLO los ajustes de pantalla (persona central, título/subtítulo,
    // orientación/profundidad, tema por defecto). Los ajustes SENSIBLES —los hashes
    // de las claves (clave_edicion_hash / clave_lectura_hash), el interruptor de
    // acceso, la marca de instalación…— NO se envían nunca al navegador: se leen y
    // escriben solo en el servidor (vía Acceso / Instalador).
    $permitidos = [
        'main_id', 'titulo', 'subtitulo',
        Ajustes::K_ORIENTACION, Ajustes::K_PROF_ARRIBA, Ajustes::K_PROF_ABAJO, Ajustes::K_TEMA,
    ];
    $ajustes = [];
    foreach ($pdo->query('SELECT clave, valor FROM arb_ajustes')->fetchAll() as $a) {
        if (in_array($a['clave'], $permitidos, true)) {
            $ajustes[$a['clave']] = $a['valor'];
        }
    }
    // INT-10: no entregar main_id si apunta a una persona que ya NO está activa
    // (pudo irse a la papelera después de fijarla como central). El servidor es la
    // autoridad: si el centro no es válido, se omite y el cliente cae al primer nodo.
    if (isset($ajustes['main_id']) && !isset($activos[(string) $ajustes['main_id']])) {
        unset($ajustes['main_id']);
    }

    // ── 5) Salida: { ajustes, personas } ────────────────────────────────────
    // "personas" es una LISTA de nodos (family-chart espera una lista, no un mapa).
    $salida = [
        'ajustes'  => (object) $ajustes,          // objeto aunque esté vacío
        'personas' => array_values($nodos),
    ];

    echo json_encode($salida, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    // SEC-05: el detalle se registra en el log del servidor; al cliente solo un
    // mensaje genérico + código de referencia (nunca SQL, rutas ni estructura).
    $ref = Seguridad::registrarError($e, 'arbol');
    http_response_code(500);
    echo json_encode(
        ['error' => 'No se pudo leer el árbol.', 'ref' => $ref],
        JSON_UNESCAPED_UNICODE
    );
}
