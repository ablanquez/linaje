<?php
declare(strict_types=1);

/**
 * salud.php — página de diagnóstico del backend.
 * ----------------------------------------------
 * Comprueba, con luz verde/roja, que el entorno está listo:
 *   - versión de PHP,
 *   - extensiones pdo_mysql y gd,
 *   - conexión REAL a la base de datos vía PDO (usando bootstrap + Db).
 *
 * No crea tablas ni toca datos: solo verifica la "tubería" de conexión.
 * Es la versión definitiva que sustituye a la temporal prueba-php.php.
 *
 * SEC-06: esta página expone versiones, extensiones y el nombre de la BD, así que
 * es SOLO para administración: exige sesión de EDICIÓN. Sin ella (o sin poder
 * autorizar por falta de config/BD) responde 401/403 sin filtrar nada. El mismo
 * diagnóstico está dentro del panel (api/sistema.php) y en el instalador.
 */

require __DIR__ . '/../src/Seguridad.php';
Seguridad::cabecerasHtml();   // SEC-09: es una página HTML (con estilos en línea)
try {
    require __DIR__ . '/../src/bootstrap.php';   // config + Db (perezosa)
    require __DIR__ . '/../src/Auth.php';
} catch (Throwable $e) {
    // Sin configuración no hay forma de autorizar: no se expone el diagnóstico.
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Diagnóstico no disponible.';
    exit;
}
Auth::exigirEdicion();   // corta con 401/403 si no hay sesión de administración

// Recogemos los resultados en una lista de comprobaciones {ok, titulo, detalle}.
$checks = [];

// 1) Versión de PHP (pedimos 8.0 o superior, como en el plan).
$phpOk = PHP_VERSION_ID >= 80000;
$checks[] = [
    'ok'      => $phpOk,
    'titulo'  => 'Versión de PHP',
    'detalle' => 'PHP ' . PHP_VERSION . ($phpOk ? ' (≥ 8.0)' : ' — se recomienda 8.0 o superior'),
];

// 2) Extensión pdo_mysql (necesaria para hablar con MySQL/MariaDB por PDO).
$pdoOk = extension_loaded('pdo_mysql');
$checks[] = [
    'ok'      => $pdoOk,
    'titulo'  => 'Extensión pdo_mysql',
    'detalle' => $pdoOk ? 'Cargada' : 'NO cargada — actívala en php.ini',
];

// 3) Extensión gd (para redimensionar fotos en un paso posterior).
$gdOk = extension_loaded('gd');
$checks[] = [
    'ok'      => $gdOk,
    'titulo'  => 'Extensión gd',
    'detalle' => $gdOk ? 'Cargada' : 'NO cargada — actívala en php.ini',
];

// 3b) Copias de seguridad (PASO 11): NO usan ZipArchive (una copia es un único
//     JSON con las fotos en base64, PHP puro), así que ZipArchive es solo
//     informativo. Lo que SÍ hace falta es que la carpeta de copias sea escribible.
$zip = class_exists('ZipArchive');
$checks[] = [
    'ok'      => true,   // informativo: no bloquea (las copias no dependen de ZipArchive)
    'titulo'  => 'Extensión zip (ZipArchive) — informativo',
    'detalle' => $zip
        ? 'Presente (no es necesaria: las copias de seguridad usan JSON puro).'
        : 'No cargada — no pasa nada: las copias de seguridad usan JSON puro, sin ZipArchive.',
];
$dirBackups = dirname(__DIR__) . '/almacen/backups';
$backupsOk = is_dir($dirBackups) ? is_writable($dirBackups)
    : (@mkdir($dirBackups, 0775, true) || is_dir($dirBackups)) && is_writable($dirBackups);
$checks[] = [
    'ok'      => $backupsOk,
    'titulo'  => 'Carpeta de copias de seguridad',
    'detalle' => $backupsOk ? 'almacen/backups/ existe y es escribible'
        : 'almacen/backups/ no existe o no es escribible',
];

// 3c) Estado de instalación (PASO 12): informativo. Si no está instalado, se
//     indica que hay que pasar por el asistente (public/instalar/).
require_once __DIR__ . '/../src/Instalador.php';
$instalado = Instalador::estaInstalado();
$checks[] = [
    'ok'      => true,   // informativo: no bloquea (sin instalar es un estado válido)
    'titulo'  => 'Instalación',
    'detalle' => $instalado
        ? 'El árbol está instalado (marca en la BD).'
        : 'Aún no instalado — abre public/instalar/ para poner en marcha el árbol.',
];

// 4) Conexión real a la base de datos, a través de bootstrap + Db (PDO).
$bdOk = false;
$bdDetalle = '';
try {
    $pdo = bd();                                 // abre la conexión (perezosa; bootstrap ya cargado)
    // Consulta inofensiva para confirmar que responde de verdad.
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    $nombreBd = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $bdOk = true;
    $bdDetalle = 'Conectado a la BD «' . ($nombreBd !== '' ? $nombreBd : '(sin seleccionar)')
        . '» — servidor ' . $version;
} catch (Throwable $e) {
    $bdOk = false;
    $bdDetalle = 'No se pudo conectar: ' . $e->getMessage();
}
$checks[] = [
    'ok'      => $bdOk,
    'titulo'  => 'Conexión PDO a la base de datos',
    'detalle' => $bdDetalle,
];

// ¿Todo en verde?
$todoOk = array_reduce($checks, static fn($acc, $c) => $acc && $c['ok'], true);

// Pequeña ayuda para escapar texto en el HTML.
$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Salud del backend — Árbol genealógico</title>
<style>
  :root { color-scheme: light dark; }
  body {
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    margin: 0; padding: 2.5rem 1rem; background: #f4f2ec; color: #23262b;
    display: flex; justify-content: center;
  }
  @media (prefers-color-scheme: dark) {
    body { background: #1f2024; color: #e7e7ea; }
    .card { background: #2a2b2f !important; box-shadow: none !important; }
    .check { border-color: #3a3b40 !important; }
  }
  .card {
    width: 100%; max-width: 640px; background: #fff; border-radius: 16px;
    padding: 1.75rem 1.75rem 2rem; box-shadow: 0 8px 30px rgba(0,0,0,.08);
  }
  h1 { font-size: 1.4rem; margin: 0 0 .35rem; }
  .sub { margin: 0 0 1.5rem; opacity: .7; font-size: .95rem; }
  .resumen {
    display: flex; align-items: center; gap: .6rem; font-weight: 600;
    padding: .8rem 1rem; border-radius: 12px; margin-bottom: 1.25rem;
  }
  .resumen.ok  { background: rgba(46,160,67,.14); color: #1a7f37; }
  .resumen.mal { background: rgba(207,34,46,.14); color: #b42318; }
  .check {
    display: flex; align-items: flex-start; gap: .8rem;
    padding: .85rem .25rem; border-top: 1px solid #eceae4;
  }
  .check:first-of-type { border-top: none; }
  .icono { font-size: 1.15rem; line-height: 1.4; flex: 0 0 auto; }
  .icono.ok  { color: #2ea043; }
  .icono.mal { color: #cf222e; }
  .txt strong { display: block; font-size: .98rem; }
  .txt span { font-size: .88rem; opacity: .75; word-break: break-word; }
  .pie { margin-top: 1.5rem; font-size: .8rem; opacity: .6; text-align: center; }
</style>
</head>
<body>
  <main class="card">
    <h1>Salud del backend</h1>
    <p class="sub">Árbol genealógico · comprobación de PHP y conexión a la base de datos</p>

    <div class="resumen <?= $todoOk ? 'ok' : 'mal' ?>">
      <span><?= $todoOk ? '✔' : '✕' ?></span>
      <span><?= $todoOk ? 'Todo correcto: el backend puede hablar con la base de datos.'
                        : 'Hay algo que revisar (ver detalle abajo).' ?></span>
    </div>

    <?php foreach ($checks as $c): ?>
      <div class="check">
        <span class="icono <?= $c['ok'] ? 'ok' : 'mal' ?>"><?= $c['ok'] ? '✔' : '✕' ?></span>
        <span class="txt">
          <strong><?= $h($c['titulo']) ?></strong>
          <span><?= $h($c['detalle']) ?></span>
        </span>
      </div>
    <?php endforeach; ?>

    <p class="pie">PASO 3 del backend · esta página no crea tablas ni modifica datos.</p>
  </main>
</body>
</html>
