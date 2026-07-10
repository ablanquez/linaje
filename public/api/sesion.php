<?php
declare(strict_types=1);

/**
 * GET /api/sesion.php  —  estado de la sesión (para el frontend).
 * --------------------------------------------------------------
 * Devuelve si el árbol está INSTALADO, si hay sesión, el rol, la persona y el
 * token CSRF (para las escrituras). Lo usa el frontend al cargar para decidir
 * entre: pantalla de instalación · pantalla de login · app.
 *
 * RESILIENTE: si el árbol NO está instalado todavía (sin config.php o sin la
 * marca en la BD), NO intenta arrancar el backend normal (que requiere config):
 * responde {instalado:false} para que el frontend mande al instalador.
 */
require __DIR__ . '/../../src/http.php';
require __DIR__ . '/../../src/Instalador.php';

// ¿Instalado? (config + BD + marca). A prueba de fallos: si algo falla → false.
if (!Instalador::estaInstalado()) {
    responder([
        'ok'             => true,
        'instalado'      => false,
        'autenticado'    => false,
        'rol'            => null,
        'persona_id'     => null,
        'csrf'           => null,
        'control_activo' => true,
    ]);
}

// Instalado: ya podemos arrancar el backend normal (config existe).
require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/Auth.php';

$u = Auth::usuario();
responder([
    'ok'             => true,
    'instalado'      => true,
    'autenticado'    => $u !== null,
    'rol'            => $u['rol'] ?? null,
    'persona_id'     => $u['persona_id'] ?? null,
    'csrf'           => $u !== null ? Auth::csrf() : null,
    'control_activo' => Auth::controlActivo(),
]);
