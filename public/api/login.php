<?php
declare(strict_types=1);

/**
 * POST /api/login.php  —  iniciar sesión (Forma 1: dos claves).
 * ------------------------------------------------------------
 * Cuerpo JSON: { nombre, apellido, nacimiento, clave }
 *   · nombre + apellido (primer apellido) + nacimiento IDENTIFICAN a una persona
 *     del árbol (nombre/apellido normalizados; fecha por día exacto o por año).
 *   · clave decide el ROL: coincide con la de EDICIÓN → 'edicion'; con la de
 *     VISUALIZACIÓN → 'lectura'; ninguna → no entra.
 * Responde { ok:true, rol, persona_id, csrf } o error (401/409).
 *
 * Seguridad: las claves están HASHEADAS en la BD (arb_ajustes, vía Acceso; PASO
 * 12) y se comparan con password_verify (nunca viajan al navegador). Antes del
 * PASO 12 vivían en config.php; ahora las escribe el instalador y las podrá
 * cambiar el panel. Consultas preparadas.
 */

require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/http.php';
require __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Acceso.php';
require_once __DIR__ . '/../../src/LimiteAcceso.php';

exigirMetodo('POST');

// Normaliza como el buscador: minúsculas, sin acentos, espacios colapsados.
function normaliza(string $s): string
{
    $s = mb_strtolower(trim($s), 'UTF-8');
    $map = [
        'á'=>'a','à'=>'a','ä'=>'a','â'=>'a','é'=>'e','è'=>'e','ë'=>'e','ê'=>'e',
        'í'=>'i','ì'=>'i','ï'=>'i','î'=>'i','ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o',
        'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u','ñ'=>'n','ç'=>'c',
    ];
    $s = strtr($s, $map);
    return preg_replace('/\s+/', ' ', $s);
}

// Coincidencia de fecha: si AMBAS son exactas (AAAA-MM-DD) → día exacto; si
// alguna es solo año → basta con que coincida el año. Coherente con el guardado.
function fechaCoincide(string $personaNac, string $entrada): bool
{
    $personaNac = trim($personaNac);
    $entrada = trim($entrada);
    if ($personaNac === '' || $entrada === '') return false;
    $exactaP = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $personaNac);
    $exactaE = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $entrada);
    if ($exactaP && $exactaE) return $personaNac === $entrada;
    return substr($personaNac, 0, 4) === substr($entrada, 0, 4);
}

// Anti-fuerza-bruta ROBUSTO (SEC-04): freno por IP y PERSISTENTE en disco, que NO
// depende de la sesión ni de ninguna cookie. Aunque el atacante no envíe cookie
// (lo normal en un ataque automatizado), sus fallos se acumulan por dirección IP.
$ip = LimiteAcceso::ip();
$espera = LimiteAcceso::segundosBloqueo($ip);
if ($espera > 0) {
    responder(['ok' => false, 'error' => 'Demasiados intentos. Espera ' . $espera . ' s e inténtalo de nuevo.'], 429);
}
function rechazarCredenciales(array $resp): void
{
    global $ip;
    $bloqueo = LimiteAcceso::registrarFallo($ip);   // cuenta el fallo por IP (persistente)
    usleep(300000);                                 // 0,3 s: frena el machaqueo también en el acto
    if ($bloqueo > 0) {
        responder(['ok' => false, 'error' => 'Demasiados intentos. Espera ' . $bloqueo . ' s e inténtalo de nuevo.'], 429);
    }
    responder($resp, 401);
}

$in = leerEntradaJsonOResponder();

$nombre   = (string) ($in['nombre'] ?? '');
$apellido = (string) ($in['apellido'] ?? '');
$nac      = (string) ($in['nacimiento'] ?? '');
$clave    = (string) ($in['clave'] ?? '');

// Mensaje genérico (no revela si falló la persona o la clave: evita enumerar).
$generico = ['ok' => false, 'error' => 'Nombre, apellido, fecha o clave incorrectos.'];

if ($nombre === '' || $apellido === '' || $nac === '' || $clave === '') {
    rechazarCredenciales($generico);
}

// 1) Identificar a la persona por nombre + primer apellido + fecha.
$objN = normaliza($nombre);
$objA = normaliza($apellido);
$rows = bd()->query('SELECT id, nombre, apellido1, nacimiento FROM arb_personas WHERE borrado_en IS NULL')->fetchAll();

$candidatos = [];
foreach ($rows as $r) {
    if (normaliza((string) $r['nombre']) === $objN
        && normaliza((string) $r['apellido1']) === $objA
        && fechaCoincide((string) $r['nacimiento'], $nac)) {
        $candidatos[] = $r;
    }
}

if (count($candidatos) > 1) {
    // Caso raro: varias personas con el mismo nombre+apellido+fecha.
    responder(['ok' => false, 'error' => 'Hay varias personas con esos datos; contacta con el administrador del árbol.'], 409);
}
if (count($candidatos) === 0) {
    rechazarCredenciales($generico);
}
$persona = $candidatos[0];

// 2) La clave decide el rol (comparación con los hashes guardados en la BD).
$rol = Acceso::verificarClave(bd(), $clave);
if ($rol === null) {
    rechazarCredenciales($generico);
}

// 3) Sesión iniciada (y se limpia el rastro anti-fuerza-bruta de esta IP).
LimiteAcceso::limpiar($ip);
Auth::login((int) $persona['id'], $rol);
responder([
    'ok'         => true,
    'rol'        => $rol,
    'persona_id' => (int) $persona['id'],
    'csrf'       => Auth::csrf(),
]);
