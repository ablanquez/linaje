<?php
declare(strict_types=1);

/**
 * http.php — ayudantes comunes para los endpoints de la API (JSON).
 * ----------------------------------------------------------------
 * Centraliza: leer el cuerpo JSON de la petición, responder JSON, y exigir
 * método HTTP. Así los endpoints quedan finos y homogéneos.
 *
 * Marcador de validación: los endpoints y las clases de dominio lanzan
 * InvalidArgumentException cuando la ENTRADA es incorrecta (→ HTTP 400).
 * Cualquier otro Throwable se trata como error del servidor (→ HTTP 500).
 */

// Lee y decodifica el cuerpo JSON de la petición. Lanza si no es un objeto JSON.
function leerEntradaJson(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        throw new InvalidArgumentException('Falta el cuerpo de la petición.');
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new InvalidArgumentException('El cuerpo no es JSON válido.');
    }
    return $data;
}

// Igual que leerEntradaJson() pero, si la entrada es inválida, RESPONDE 400 y
// termina (en vez de lanzar). Centraliza el try/catch que se repetía idéntico al
// inicio de cada endpoint de escritura (PHP-1).
function leerEntradaJsonOResponder(): array
{
    try {
        return leerEntradaJson();
    } catch (InvalidArgumentException $e) {
        responder(['ok' => false, 'error' => $e->getMessage()], 400);   // responder() hace exit
    }
}

// Envía una respuesta JSON y termina.
function responder($data, int $code = 200): void
{
    if (class_exists('Seguridad')) Seguridad::cabecerasBase();   // SEC-09: nosniff, X-Frame-Options, HSTS
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Exige que la petición use un método concreto (p.ej. POST para escritura).
function exigirMetodo(string $metodo): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $metodo) {
        responder(['ok' => false, 'error' => 'Método no permitido; usa ' . $metodo . '.'], 405);
    }
}

/**
 * Ejecuta $fn (que hace la escritura) envuelto en una TRANSACCIÓN y responde:
 *   · éxito           → { ok:true, ...lo que devuelva $fn }
 *   · entrada inválida → HTTP 400 { ok:false, error }
 *   · error servidor   → HTTP 500 { ok:false, error, detalle }
 * $fn recibe el PDO y devuelve un array (datos extra para la respuesta) o null.
 */
function ejecutarEscritura(PDO $pdo, callable $fn): void
{
    try {
        $pdo->beginTransaction();
        $extra = $fn($pdo);
        $pdo->commit();
        // INT-06: los efectos de FICHERO diferidos (borrar fotos antiguas) se aplican
        // SOLO ahora, tras un commit correcto → nunca queda una referencia colgante.
        if (class_exists('Fotos')) Fotos::purgarProgramados();
        responder(['ok' => true] + (is_array($extra) ? $extra : []));
    } catch (InvalidArgumentException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (class_exists('Fotos')) Fotos::descartarProgramados();   // rollback → no se borra nada
        responder(['ok' => false, 'error' => $e->getMessage()], 400);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (class_exists('Fotos')) Fotos::descartarProgramados();
        // SEC-05: el detalle (SQL, rutas, estructura) va al LOG del servidor, nunca al
        // cliente. Se le devuelve un mensaje genérico + un código de referencia corto.
        $ref = Seguridad::registrarError($e, 'escritura');
        responder(['ok' => false, 'error' => 'No se pudo guardar en la base de datos.', 'ref' => $ref], 500);
    }
}
