<?php
declare(strict_types=1);

/**
 * /api/seguridad.php  —  claves de acceso e interruptor de control. SOLO EDICIÓN.
 * ------------------------------------------------------------------------------
 * Cierra el hueco funcional del panel: hasta ahora las claves y el interruptor
 * solo se tocaban en la instalación o directamente en la BD; aquí se gestionan
 * desde la app, EN CALIENTE (los cambios surten efecto de inmediato) y de forma
 * SEGURA (se pide la clave de edición ACTUAL para cambios sensibles).
 *
 *   GET                                                   → estado del acceso
 *   POST { accion:'cambiar_clave', rol, clave_actual, clave_nueva }
 *   POST { accion:'establecer_control', activo, clave_actual, clave_edicion?, clave_lectura? }
 *
 * Las claves NUNCA se guardan ni viajan en claro: solo su hash (vía Acceso). Un
 * cambio de clave NO cierra la sesión actual (la sesión va por cookie, no por la
 * clave); los PRÓXIMOS inicios de sesión usarán ya la clave nueva.
 */

require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/http.php';
require __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Acceso.php';   // Auth.php ya lo carga (require_once evita redeclararlo)

Auth::exigirEdicion();   // toda gestión de seguridad es cosa de admin (401/403)

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ── Estado (para pintar el panel): NUNCA devuelve hashes, solo si existen ─────
if ($metodo === 'GET') {
    $pdo = bd();
    responder([
        'ok'             => true,
        'control_activo' => Acceso::controlActivo($pdo),
        'hay_edicion'    => Acceso::hayClaveEdicion($pdo),
        'hay_lectura'    => Acceso::hayClaveLectura($pdo),
    ]);
}

if ($metodo !== 'POST') {
    responder(['ok' => false, 'error' => 'Método no permitido.'], 405);
}

Auth::exigirCsrf();

$in = leerEntradaJsonOResponder();
$accion = $in['accion'] ?? '';

ejecutarEscritura(bd(), function (PDO $pdo) use ($in, $accion) {
    switch ($accion) {

        // ── Cambiar una clave (edición o lectura) ────────────────────────────
        // Reautenticación con la clave de edición ACTUAL. La clave nueva la valida
        // Acceso (longitud mínima). En caliente: el próximo login usa la nueva.
        case 'cambiar_clave': {
            $rol = (string) ($in['rol'] ?? '');
            if ($rol !== 'edicion' && $rol !== 'lectura') {
                throw new InvalidArgumentException('Rol no válido (edicion o lectura).');
            }
            Acceso::reautenticar($pdo, (string) ($in['clave_actual'] ?? ''));
            Acceso::establecerClave($pdo, $rol, (string) ($in['clave_nueva'] ?? ''));
            return null;
        }

        // ── Activar / desactivar el control de acceso ────────────────────────
        // · Desactivar (árbol abierto): exige la clave de edición actual.
        // · Activar desde abierto: si aún no hay claves, se deben aportar las dos;
        //   si ya existen (p.ej. tras desactivar), basta con reactivar.
        case 'establecer_control': {
            $activo = !empty($in['activo']);

            if (!$activo) {
                Acceso::reautenticar($pdo, (string) ($in['clave_actual'] ?? ''));
                Acceso::establecerControl($pdo, false);
                return ['control_activo' => false];
            }

            // Activar. Si el control ya estaba activo, reautenticamos por coherencia.
            if (Acceso::controlActivo($pdo)) {
                Acceso::reautenticar($pdo, (string) ($in['clave_actual'] ?? ''));
            }
            // Deben existir ambas claves para poder iniciar sesión. Si faltan, se
            // exigen en la petición (caso: activar un árbol que estaba abierto).
            $claveEd = trim((string) ($in['clave_edicion'] ?? ''));
            $claveLe = trim((string) ($in['clave_lectura'] ?? ''));
            if (!Acceso::hayClaveEdicion($pdo)) {
                if ($claveEd === '') throw new InvalidArgumentException('Para activar el acceso hace falta definir la clave de edición.');
                Acceso::establecerClave($pdo, 'edicion', $claveEd);
            } elseif ($claveEd !== '') {
                Acceso::establecerClave($pdo, 'edicion', $claveEd);
            }
            if (!Acceso::hayClaveLectura($pdo)) {
                if ($claveLe === '') throw new InvalidArgumentException('Para activar el acceso hace falta definir la clave de lectura.');
                Acceso::establecerClave($pdo, 'lectura', $claveLe);
            } elseif ($claveLe !== '') {
                Acceso::establecerClave($pdo, 'lectura', $claveLe);
            }
            Acceso::establecerControl($pdo, true);
            return ['control_activo' => true];
        }

        default:
            throw new InvalidArgumentException('Acción no reconocida: ' . $accion);
    }
});
