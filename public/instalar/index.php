<?php
declare(strict_types=1);

/**
 * Asistente de instalación (PASO 12) — estilo WordPress, en español.
 * ------------------------------------------------------------------
 * Pantallas: Requisitos → Conexión → Estructura → Datos (persona+acceso+
 * identidad) → Listo. La LÓGICA vive en src/Instalador.php; aquí solo va la
 * interfaz y el control de flujo.
 *
 * SEGURIDAD:
 *  · CERROJO en cada petición: si el árbol YA está instalado (config + BD +
 *    marca instalado=1), no se muestra ningún formulario y no se ejecuta ninguna
 *    acción (ni aunque se llame directamente al endpoint). Se basa en estado en
 *    TIEMPO DE EJECUCIÓN (no en borrar archivos), porque el auto-deploy resube
 *    el instalador en cada cambio.
 *  · Token CSRF propio del instalador en cada formulario.
 *  · Reanuda instalaciones a medias: calcula en qué paso retomar según el estado
 *    real (config escrito, tablas creadas, marca de instalado).
 */

// SEC-11/12: en el instalador tampoco se muestran detalles de error al cliente
// (podrían revelar la estructura de la BD o servir de oráculo de conectividad).
@ini_set('display_errors', '0');
error_reporting(E_ALL);

require __DIR__ . '/../../src/Instalador.php';
require __DIR__ . '/../../src/Seguridad.php';

// SEC-09: cabeceras de seguridad + CSP para el instalador. Su único <script> en
// línea (mejora progresiva de los campos) se autoriza con un nonce concreto.
define('CSP_NONCE', Seguridad::nonce());
Seguridad::cabecerasHtml(CSP_NONCE);

session_name('genealogia_instalar');
session_start();
if (empty($_SESSION['csrf_inst'])) $_SESSION['csrf_inst'] = bin2hex(random_bytes(16));
$CSRF = (string) $_SESSION['csrf_inst'];

$h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$PASOS = [
    'requisitos' => 'Requisitos',
    'conexion'   => 'Conexión',
    'estructura' => 'Estructura',
    'datos'      => 'Tus datos',
    'fin'        => 'Listo',
];

// ── Vistas (definidas al final) ─────────────────────────────────────────────
// Se usan tras el control de flujo. Todas comparten cabecera/pie.

// ── CERROJO GLOBAL ──────────────────────────────────────────────────────────
// Si ya está instalado: nada de formularios ni acciones. Fin de la historia.
if (Instalador::estaInstalado()) {
    vistaYaInstalado($h, $PASOS);
    exit;
}

$error = '';
$contenidoConfigManual = '';
$valores = [];                 // para repoblar formularios tras un error
$paso = (string) ($_REQUEST['paso'] ?? '');

// ── Procesar acciones (POST) ────────────────────────────────────────────────
if ($metodo === 'POST') {
    $accion = (string) ($_POST['accion'] ?? '');
    // CSRF en cada submit.
    if (!hash_equals($CSRF, (string) ($_POST['csrf'] ?? ''))) {
        $error = 'Token de seguridad caducado. Recarga la página e inténtalo de nuevo.';
        $paso = $paso ?: 'requisitos';
    } else {
        try {
            switch ($accion) {

                case 'conexion':
                    $db = [
                        'host'    => trim((string) ($_POST['host'] ?? '')),
                        'puerto'  => (int) ($_POST['puerto'] ?? 3306),
                        'nombre'  => trim((string) ($_POST['nombre'] ?? '')),
                        'usuario' => trim((string) ($_POST['usuario'] ?? '')),
                        'clave'   => (string) ($_POST['clave'] ?? ''),
                    ];
                    $valores = $db;
                    $prueba = Instalador::probarConexion($db);
                    if (!$prueba['ok']) {
                        // SEC-11: el mensaje CRUDO del driver PDO puede servir de oráculo
                        // (host alcanzable, BD existente…) o de vector SSRF. Se registra en
                        // el log del servidor y al usuario se le da una guía genérica.
                        @error_log('genealogia (instalar/conexion) ' . (string) ($prueba['error'] ?? ''));
                        $error = 'No se pudo conectar con la base de datos. Revisa el host, el puerto, el nombre de la base de datos, el usuario y la contraseña.';
                        $paso = 'conexion';
                        break;
                    }
                    $esc = Instalador::escribirConfig($db);
                    if ($esc['escrito']) {
                        header('Location: ?paso=estructura');
                        exit;
                    }
                    // No se pudo escribir config.php: se muestra para pegarlo a mano. SEC-13:
                    // NO se guarda en $_SESSION (el fichero de sesión, legible por otros usuarios
                    // en hosting compartido, no debe contener las credenciales de la BD); viaja
                    // en un campo oculto del propio formulario de este paso.
                    $contenidoConfigManual = $esc['contenido'];
                    $paso = 'conexion_manual';
                    break;

                case 'conexion_manual':
                    // El usuario dice haber pegado config.php a mano: comprobamos.
                    if (Instalador::configExiste() && Instalador::pdoDesdeConfig() instanceof PDO) {
                        header('Location: ?paso=estructura');
                        exit;
                    }
                    $error = 'Todavía no encuentro un config.php válido. Pega el archivo y vuelve a pulsar «Continuar».';
                    $contenidoConfigManual = (string) ($_POST['contenido_manual'] ?? '');
                    $paso = 'conexion_manual';
                    break;

                case 'estructura':
                    $pdo = Instalador::pdoDesdeConfig();
                    if (!$pdo) {
                        $error = 'No puedo conectar con la base de datos. Revisa config.php.';
                        $paso = 'conexion';
                        break;
                    }
                    Instalador::crearEstructura($pdo);
                    header('Location: ?paso=datos');
                    exit;

                case 'finalizar':
                    $pdo = Instalador::pdoDesdeConfig();
                    if (!$pdo) {
                        $error = 'No puedo conectar con la base de datos. Revisa config.php.';
                        $paso = 'conexion';
                        break;
                    }
                    $soloAnio = !empty($_POST['solo_anio']);
                    $nac = $soloAnio ? trim((string) ($_POST['nac_anio'] ?? ''))
                                     : trim((string) ($_POST['nac_fecha'] ?? ''));
                    $persona = [
                        'nombre'     => trim((string) ($_POST['nombre'] ?? '')),
                        'apellido1'  => trim((string) ($_POST['apellido1'] ?? '')),
                        'apellido2'  => trim((string) ($_POST['apellido2'] ?? '')),
                        'sexo'       => (string) ($_POST['sexo'] ?? ''),
                        'nacimiento' => $nac,
                    ];
                    $activo = !empty($_POST['proteger']);
                    $acceso = [
                        'activo'        => $activo,
                        'clave_edicion' => (string) ($_POST['clave_edicion'] ?? ''),
                        'clave_lectura' => (string) ($_POST['clave_lectura'] ?? ''),
                    ];
                    $identidad = [
                        'titulo'    => trim((string) ($_POST['titulo'] ?? '')),
                        'subtitulo' => trim((string) ($_POST['subtitulo'] ?? '')),
                    ];
                    $valores = ['persona' => $persona, 'acceso' => $acceso, 'identidad' => $identidad, 'solo_anio' => $soloAnio];

                    Instalador::finalizar($pdo, $persona, $acceso, $identidad);
                    // Éxito: mostramos la pantalla final AQUÍ MISMO (a partir de ahora
                    // el cerrojo ya está echado y una recarga mostrará «ya instalado»).
                    vistaFin($h, $PASOS, $persona, $acceso, $identidad);
                    exit;

                default:
                    $paso = $paso ?: 'requisitos';
            }
        } catch (InvalidArgumentException $e) {
            // Los errores de VALIDACIÓN de entrada son seguros y útiles (p.ej. "El
            // nombre es obligatorio"): se muestran tal cual.
            $error = $e->getMessage();
            if ($paso === '') $paso = ($accion === 'finalizar') ? 'datos' : 'conexion';
        } catch (Throwable $e) {
            // SEC-12: cualquier otro error puede llevar detalle sensible (SQL, rutas):
            // se registra en el log del servidor y al usuario le llega algo genérico.
            Seguridad::registrarError($e, 'instalar');
            $error = 'Ha ocurrido un problema al procesar la instalación. Inténtalo de nuevo.';
            if ($paso === '') $paso = ($accion === 'finalizar') ? 'datos' : 'conexion';
        }
    }
}

// ── Resolver el paso a mostrar (GET o tras error) ───────────────────────────
$estado = Instalador::estado();
$sugerido = !$estado['config'] ? 'conexion'
          : (!$estado['tablas'] ? 'estructura'
          : (!$estado['instalado'] ? 'datos' : 'fin'));

$permitidos = ['requisitos', 'conexion', 'conexion_manual', 'estructura', 'datos', 'fin'];
if (!in_array($paso, $permitidos, true)) {
    // Entrada normal: empezamos por requisitos, salvo que haya instalación a medias.
    $paso = ($estado['config'] || $estado['tablas']) ? $sugerido : 'requisitos';
}
// SEC-13: el contenido de config.php (con credenciales) NO se guarda en la sesión.
// Si llegamos al pegado manual sin contenido (p.ej. una recarga directa por GET),
// se vuelve al paso de conexión, que lo regenera.
if ($paso === 'conexion_manual' && $contenidoConfigManual === '') {
    $paso = 'conexion';
}

// ── Pintar la pantalla correspondiente ──────────────────────────────────────
switch ($paso) {
    case 'requisitos':       vistaRequisitos($h, $PASOS, $sugerido); break;
    case 'conexion':         vistaConexion($h, $PASOS, $CSRF, $error, $valores); break;
    case 'conexion_manual':  vistaConexionManual($h, $PASOS, $CSRF, $error, $contenidoConfigManual); break;
    case 'estructura':       vistaEstructura($h, $PASOS, $CSRF, $error); break;
    case 'datos':            vistaDatos($h, $PASOS, $CSRF, $error, $valores); break;
    case 'fin':              vistaYaInstalado($h, $PASOS); break;
    default:                 vistaRequisitos($h, $PASOS, $sugerido);
}


// ════════════════════════════════════════════════════════════════════════════
//  VISTAS
// ════════════════════════════════════════════════════════════════════════════

function cabecera(callable $h, array $PASOS, string $pasoActual, string $titulo): void
{
    $orden = array_keys($PASOS);
    // El paso de pegado manual pertenece visualmente a "Conexión".
    $activoVisual = $pasoActual === 'conexion_manual' ? 'conexion' : $pasoActual;
    $idxActivo = array_search($activoVisual, $orden, true);
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalación · Árbol genealógico</title>
<!-- Favicon propio (el logo vive en public/, un nivel por encima). -->
<link rel="icon" href="../favicon.svg" type="image/svg+xml">
<link rel="icon" href="../favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="../apple-touch-icon.png">
<style>
  :root { color-scheme: light dark; --bg:#f4f2ec; --fg:#23262b; --card:#fff; --bd:#e7e4dc;
          --mut:#6b6f76; --acc:#0f857d; --acc2:#0b6b64; --err:#b42318; --errbg:rgba(207,34,46,.12);
          --okbg:rgba(46,160,67,.14); --okfg:#1a7f37; --inp:#fff; --inpbd:#cfccc4; }
  @media (prefers-color-scheme: dark) {
    :root { --bg:#1f2024; --fg:#e7e7ea; --card:#2a2b2f; --bd:#3a3b40; --mut:#a2a5ab;
            --acc:#148f84; --acc2:#18a99c; --err:#ff7a70; --errbg:rgba(207,34,46,.18);
            --okbg:rgba(46,160,67,.16); --okfg:#5fce86; --inp:#212227; --inpbd:#44464c; }
  }
  * { box-sizing: border-box; }
  body { font-family: system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; margin:0;
         padding:2.5rem 1rem 3rem; background:var(--bg); color:var(--fg); display:flex; justify-content:center; }
  .wrap { width:100%; max-width:640px; }
  .marca { display:flex; align-items:center; gap:.5rem; margin:0 0 1.2rem; font-weight:700; font-size:1.05rem; }
  .marca .leaf { font-size:1.4rem; }
  .card { background:var(--card); border:1px solid var(--bd); border-radius:16px; padding:1.6rem 1.7rem 1.9rem;
          box-shadow:0 8px 30px rgba(0,0,0,.06); }
  h1 { font-size:1.35rem; margin:0 0 .3rem; }
  .sub { margin:0 0 1.3rem; color:var(--mut); font-size:.95rem; }
  /* Pasos */
  .pasos { display:flex; gap:.35rem; list-style:none; padding:0; margin:0 0 1.5rem; flex-wrap:wrap; }
  .pasos li { display:flex; align-items:center; gap:.4rem; font-size:.82rem; color:var(--mut);
              padding:.3rem .6rem; border-radius:999px; border:1px solid var(--bd); }
  .pasos li .n { display:inline-flex; width:1.25rem; height:1.25rem; border-radius:50%; background:var(--bd);
                 color:var(--fg); align-items:center; justify-content:center; font-size:.72rem; font-weight:700; }
  .pasos li.on { color:var(--fg); border-color:var(--acc); }
  .pasos li.on .n { background:var(--acc); color:#fff; }
  .pasos li.done .n { background:var(--acc); color:#fff; }
  /* Comprobaciones */
  .check { display:flex; align-items:flex-start; gap:.7rem; padding:.7rem .1rem; border-top:1px solid var(--bd); }
  .check:first-of-type { border-top:none; }
  .ic { font-size:1.05rem; flex:0 0 auto; line-height:1.4; }
  .ic.ok { color:var(--acc); } .ic.no { color:var(--err); }
  .check .t strong { display:block; font-size:.95rem; }
  .check .t span { font-size:.85rem; color:var(--mut); }
  /* Formularios */
  label.f { display:block; margin:.9rem 0 0; font-size:.9rem; font-weight:600; }
  label.f small { font-weight:400; color:var(--mut); }
  .oblig { color:var(--err); font-weight:700; }
  input[type=text], input[type=password], input[type=number], input[type=date], select {
    width:100%; margin-top:.35rem; padding:.6rem .7rem; font-size:.95rem; color:var(--fg);
    background:var(--inp); border:1px solid var(--inpbd); border-radius:10px; }
  input:focus, select:focus { outline:2px solid var(--acc); border-color:var(--acc); }
  .fila { display:flex; gap:.8rem; } .fila > * { flex:1; }
  .chk { display:flex; align-items:center; gap:.5rem; margin:.9rem 0 0; font-size:.9rem; font-weight:600; }
  .chk input { width:auto; margin:0; }
  .grupo { border:1px solid var(--bd); border-radius:12px; padding:.4rem 1rem 1rem; margin-top:1.1rem; }
  .grupo h3 { font-size:.95rem; margin:1rem 0 .2rem; }
  .grupo .hint { font-size:.82rem; color:var(--mut); margin:.1rem 0 .4rem; }
  .btns { display:flex; gap:.6rem; margin-top:1.5rem; align-items:center; flex-wrap:wrap; }
  button, .btn { font:inherit; font-weight:600; padding:.65rem 1.2rem; border-radius:10px; border:1px solid transparent;
                 background:var(--acc); color:#fff; cursor:pointer; text-decoration:none; display:inline-block; }
  button:hover, .btn:hover { background:var(--acc2); }
  .btn.sec { background:transparent; color:var(--fg); border-color:var(--inpbd); }
  .btn.sec:hover { background:rgba(127,127,127,.12); }
  .aviso { padding:.8rem 1rem; border-radius:10px; font-size:.9rem; margin:0 0 1rem; }
  .aviso.err { background:var(--errbg); color:var(--err); }
  .aviso.ok  { background:var(--okbg); color:var(--okfg); }
  .nota { font-size:.82rem; color:var(--mut); margin-top:1.1rem; }
  code, pre { font-family:ui-monospace,Consolas,monospace; }
  pre { background:var(--inp); border:1px solid var(--inpbd); border-radius:10px; padding:.9rem;
        font-size:.8rem; overflow:auto; max-height:260px; }
  .pie { margin-top:1.3rem; text-align:center; color:var(--mut); font-size:.78rem; }
</style>
</head>
<body>
<div class="wrap">
  <div class="marca"><svg class="leaf" width="24" height="24" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Árbol genealógico"><line x1="32" y1="10.5" x2="18" y2="31.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><line x1="32" y1="10.5" x2="46" y2="31.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><line x1="18" y1="31.5" x2="9.5" y2="53.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><line x1="18" y1="31.5" x2="24.5" y2="53.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><line x1="46" y1="31.5" x2="39.5" y2="53.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><line x1="46" y1="31.5" x2="54.5" y2="53.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><circle cx="18" cy="31.5" r="5.6" fill="#0f857d"/><circle cx="46" cy="31.5" r="5.6" fill="#0f857d"/><circle cx="9.5" cy="53.5" r="5" fill="#16a093"/><circle cx="24.5" cy="53.5" r="5" fill="#16a093"/><circle cx="39.5" cy="53.5" r="5" fill="#16a093"/><circle cx="54.5" cy="53.5" r="5" fill="#16a093"/><circle cx="32" cy="10.5" r="6" fill="#0b6b64"/></svg> Árbol genealógico · Instalación</div>
  <div class="card">
    <ul class="pasos">
      <?php $i = 0; foreach ($PASOS as $k => $nombre): $cls = ''; if ($k === $activoVisual) $cls = 'on'; elseif ($idxActivo !== false && $i < $idxActivo) $cls = 'done'; ?>
        <li class="<?= $cls ?>"><span class="n"><?= $i + 1 ?></span><?= $h($nombre) ?></li>
      <?php $i++; endforeach; ?>
    </ul>
    <h1><?= $h($titulo) ?></h1>
<?php
}

function pie(): void
{
    ?>
  </div>
  <div class="pie">Asistente de instalación · Árbol genealógico</div>
</div>
</body>
</html>
<?php
}

function vistaRequisitos(callable $h, array $PASOS, string $sugerido): void
{
    $reqs = Instalador::requisitos();
    $bloquean = Instalador::requisitosBloquean($reqs);
    cabecera($h, $PASOS, 'requisitos', 'Bienvenido');
    ?>
    <p class="sub">Este asistente pondrá en marcha tu árbol genealógico: creará las tablas, la primera persona (que será el administrador) y las claves de acceso. Primero, comprobemos que el servidor cumple los requisitos.</p>
    <?php foreach ($reqs as $c): ?>
      <div class="check">
        <span class="ic <?= $c['ok'] ? 'ok' : 'no' ?>"><?= $c['ok'] ? '✔' : '✕' ?></span>
        <span class="t"><strong><?= $h($c['titulo']) ?><?= empty($c['critico']) ? '' : ' <small style="color:var(--mut);font-weight:400">(imprescindible)</small>' ?></strong><span><?= $h($c['detalle']) ?></span></span>
      </div>
    <?php endforeach; ?>
    <?php if ($bloquean): ?>
      <div class="aviso err" style="margin-top:1.2rem">Falta algún requisito imprescindible. Corrígelo (normalmente en <code>php.ini</code>) y recarga esta página.</div>
      <div class="btns"><a class="btn sec" href="?paso=requisitos">Volver a comprobar</a></div>
    <?php else: ?>
      <div class="btns"><a class="btn" href="?paso=<?= $h($sugerido) ?>">Empezar la instalación →</a></div>
    <?php endif; ?>
    <?php
    pie();
}

function vistaConexion(callable $h, array $PASOS, string $csrf, string $error, array $v): void
{
    cabecera($h, $PASOS, 'conexion', 'Conexión a la base de datos');
    ?>
    <p class="sub">Introduce los datos de tu base de datos MySQL/MariaDB. Los comprobaremos y, si todo va bien, guardaremos <code>config/config.php</code> automáticamente.</p>
    <?php if ($error): ?><div class="aviso err"><?= $h($error) ?></div><?php endif; ?>
    <form method="post" action="?paso=conexion">
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <input type="hidden" name="accion" value="conexion">
      <div class="fila">
        <label class="f">Servidor (host)<input type="text" name="host" value="<?= $h($v['host'] ?? 'localhost') ?>" required></label>
        <label class="f" style="max-width:9rem">Puerto<input type="number" name="puerto" value="<?= $h($v['puerto'] ?? 3306) ?>"></label>
      </div>
      <label class="f">Nombre de la base de datos<input type="text" name="nombre" value="<?= $h($v['nombre'] ?? '') ?>" required placeholder="p. ej. genealogia"></label>
      <label class="f">Usuario<input type="text" name="usuario" value="<?= $h($v['usuario'] ?? '') ?>" required></label>
      <label class="f">Contraseña <small>(vacía si tu usuario no tiene)</small><input type="password" name="clave" value=""></label>
      <p class="nota">La base de datos debe existir ya (créala vacía en tu panel de hosting). El prefijo de tablas es <code>arb_</code>.</p>
      <div class="btns"><button type="submit">Comprobar y continuar →</button></div>
    </form>
    <?php
    pie();
}

function vistaConexionManual(callable $h, array $PASOS, string $csrf, string $error, string $contenido): void
{
    cabecera($h, $PASOS, 'conexion_manual', 'Crea config.php a mano');
    ?>
    <p class="sub">La conexión funciona, pero no tengo permiso para escribir <code>config/config.php</code>. Crea ese archivo con este contenido exacto y pulsa «Continuar».</p>
    <?php if ($error): ?><div class="aviso err"><?= $h($error) ?></div><?php endif; ?>
    <pre><?= $h($contenido) ?></pre>
    <form method="post" action="?paso=conexion_manual">
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <input type="hidden" name="accion" value="conexion_manual">
      <!-- SEC-13: el contenido viaja en el formulario, NO en el fichero de sesión. -->
      <input type="hidden" name="contenido_manual" value="<?= $h($contenido) ?>">
      <div class="btns">
        <button type="submit">Ya lo he pegado, continuar →</button>
        <a class="btn sec" href="?paso=conexion">Volver</a>
      </div>
    </form>
    <?php
    pie();
}

function vistaEstructura(callable $h, array $PASOS, string $csrf, string $error): void
{
    cabecera($h, $PASOS, 'estructura', 'Crear las tablas');
    ?>
    <p class="sub">La conexión está lista. Ahora crearé las tablas del árbol en tu base de datos. Es seguro repetirlo: si ya existieran, no se tocan.</p>
    <?php if ($error): ?><div class="aviso err"><?= $h($error) ?></div><?php endif; ?>
    <div class="check"><span class="ic ok">🗄️</span><span class="t"><strong>6 tablas</strong><span>arb_personas · arb_filiacion · arb_pareja · arb_usuarios · arb_ajustes · arb_arboles</span></span></div>
    <form method="post" action="?paso=estructura">
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <input type="hidden" name="accion" value="estructura">
      <div class="btns"><button type="submit">Crear las tablas →</button></div>
    </form>
    <?php
    pie();
}

function vistaDatos(callable $h, array $PASOS, string $csrf, string $error, array $v): void
{
    $p = $v['persona'] ?? [];
    $a = $v['acceso'] ?? ['activo' => true];
    $id = $v['identidad'] ?? [];
    $soloAnio = !empty($v['solo_anio']);
    $proteger = !isset($a['activo']) || !empty($a['activo']);
    cabecera($h, $PASOS, 'datos', 'Tu árbol y tu acceso');
    ?>
    <p class="sub">Último paso. Crea la <strong>primera persona</strong> (será el punto de partida del árbol y el administrador), elige cómo se protege el acceso y ponle nombre al árbol.</p>
    <?php if ($error): ?><div class="aviso err"><?= $h($error) ?></div><?php endif; ?>
    <form method="post" action="?paso=datos" id="formDatos">
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <input type="hidden" name="accion" value="finalizar">

      <div class="grupo">
        <h3>Primera persona (administrador)</h3>
        <p class="hint">Con estos datos + la clave de edición entrarás a administrar el árbol.</p>
        <div class="fila">
          <label class="f">Nombre <span class="oblig">*</span><input type="text" name="nombre" value="<?= $h($p['nombre'] ?? '') ?>" required></label>
          <label class="f">Sexo <span class="oblig">*</span>
            <select name="sexo" required>
              <option value="" disabled <?= (($p['sexo'] ?? '') === '') ? 'selected' : '' ?>>— elige —</option>
              <option value="M" <?= (($p['sexo'] ?? '') === 'M') ? 'selected' : '' ?>>Hombre</option>
              <option value="F" <?= (($p['sexo'] ?? '') === 'F') ? 'selected' : '' ?>>Mujer</option>
            </select>
          </label>
        </div>
        <div class="fila">
          <label class="f">Primer apellido<input type="text" name="apellido1" value="<?= $h($p['apellido1'] ?? '') ?>" required></label>
          <label class="f">Segundo apellido <small>(opcional)</small><input type="text" name="apellido2" value="<?= $h($p['apellido2'] ?? '') ?>"></label>
        </div>
        <label class="chk"><input type="checkbox" name="solo_anio" id="soloAnio" <?= $soloAnio ? 'checked' : '' ?>> Solo sé el año de nacimiento</label>
        <label class="f" id="campoFecha" style="<?= $soloAnio ? 'display:none' : '' ?>">Fecha de nacimiento<input type="date" name="nac_fecha" value="<?= $h($soloAnio ? '' : ($p['nacimiento'] ?? '')) ?>"></label>
        <label class="f" id="campoAnio" style="<?= $soloAnio ? '' : 'display:none' ?>">Año de nacimiento<input type="number" name="nac_anio" min="1" max="2999" value="<?= $h($soloAnio ? ($p['nacimiento'] ?? '') : '') ?>" placeholder="p. ej. 1950"></label>
      </div>

      <div class="grupo">
        <h3>Acceso</h3>
        <label class="f">Clave de edición (administrador) <span class="oblig">*</span><input type="password" name="clave_edicion" placeholder="mínimo 8 caracteres" required></label>
        <p class="hint">Con esta clave y tus datos de arriba entrarás a <strong>administrar</strong>. Administrar SIEMPRE exige iniciar sesión, también en un árbol abierto.</p>
        <label class="chk"><input type="checkbox" name="proteger" id="proteger" <?= $proteger ? 'checked' : '' ?>> Pedir clave también para VER el árbol (recomendado)</label>
        <p class="hint">Si lo desactivas, el árbol será <strong>abierto</strong>: cualquiera con el enlace podrá <strong>verlo</strong> sin clave (pero no editarlo). Podrás cambiarlo luego desde el panel.</p>
        <div id="camposClaves" style="<?= $proteger ? '' : 'display:none' ?>">
          <label class="f">Clave de lectura (solo ver) <span class="oblig">*</span><input type="password" name="clave_lectura" placeholder="mínimo 8 caracteres"></label>
        </div>
      </div>

      <div class="grupo">
        <h3>El árbol</h3>
        <label class="f">Título<input type="text" name="titulo" value="<?= $h($id['titulo'] ?? '') ?>" placeholder="p. ej. Familia García" maxlength="120"></label>
        <label class="f">Subtítulo <small>(opcional)</small><input type="text" name="subtitulo" value="<?= $h($id['subtitulo'] ?? '') ?>" maxlength="180"></label>
      </div>

      <div class="btns"><button type="submit">Instalar y entrar →</button></div>
    </form>
    <script nonce="<?= $h(CSP_NONCE) ?>">
      // Mejora progresiva: mostrar/ocultar campos según los interruptores.
      var sa = document.getElementById('soloAnio');
      sa && sa.addEventListener('change', function () {
        document.getElementById('campoFecha').style.display = this.checked ? 'none' : '';
        document.getElementById('campoAnio').style.display = this.checked ? '' : 'none';
      });
      var pr = document.getElementById('proteger');
      pr && pr.addEventListener('change', function () {
        document.getElementById('camposClaves').style.display = this.checked ? '' : 'none';
      });
    </script>
    <?php
    pie();
}

function vistaFin(callable $h, array $PASOS, array $persona, array $acceso, array $identidad): void
{
    $titulo = trim((string) ($identidad['titulo'] ?? '')) ?: 'Nuestro árbol';
    $activo = !empty($acceso['activo']);
    cabecera($h, $PASOS, 'fin', '¡Instalación completada!');
    ?>
    <div class="aviso ok">Tu árbol «<?= $h($titulo) ?>» está listo.</div>
    <?php if ($activo): ?>
      <p class="sub">Guarda estos datos: son los que usarás para <strong>iniciar sesión como administrador</strong>.</p>
      <div class="grupo">
        <h3>Cómo entrar</h3>
        <div class="check"><span class="ic ok">👤</span><span class="t"><strong><?= $h(trim(($persona['nombre'] ?? '') . ' ' . ($persona['apellido1'] ?? ''))) ?></strong><span>Nombre y primer apellido</span></span></div>
        <div class="check"><span class="ic ok">📅</span><span class="t"><strong><?= $h($persona['nacimiento'] ?? '') ?></strong><span>Fecha de nacimiento</span></span></div>
        <div class="check"><span class="ic ok">🔑</span><span class="t"><strong>La clave de edición que has elegido</strong><span>La de lectura sirve para quien solo deba ver el árbol</span></span></div>
      </div>
    <?php else: ?>
      <p class="sub">El árbol es <strong>abierto</strong>: cualquiera con el enlace puede <strong>verlo</strong> sin clave. Para <strong>administrar</strong>, inicia sesión con tus datos y tu clave de edición (los de abajo). Puedes activar el control de acceso completo más adelante desde el panel.</p>
      <div class="grupo">
        <h3>Cómo administrar</h3>
        <div class="check"><span class="ic ok">👤</span><span class="t"><strong><?= $h(trim(($persona['nombre'] ?? '') . ' ' . ($persona['apellido1'] ?? ''))) ?></strong><span>Nombre y primer apellido</span></span></div>
        <div class="check"><span class="ic ok">📅</span><span class="t"><strong><?= $h($persona['nacimiento'] ?? '') ?></strong><span>Fecha de nacimiento</span></span></div>
        <div class="check"><span class="ic ok">🔑</span><span class="t"><strong>La clave de edición que has elegido</strong><span>Solo tú, con la clave, puedes editar</span></span></div>
      </div>
    <?php endif; ?>
    <div class="btns"><a class="btn" href="../">Ir al árbol →</a></div>
    <p class="nota">Por seguridad, este asistente ya no volverá a ejecutarse: el árbol está marcado como instalado.</p>
    <?php
    pie();
}

function vistaYaInstalado(callable $h, array $PASOS): void
{
    cabecera($h, $PASOS, 'fin', 'El árbol ya está instalado');
    ?>
    <div class="aviso ok">Este árbol ya está instalado. Por seguridad, el asistente de instalación está desactivado.</div>
    <p class="sub">Si necesitas reinstalar desde cero, primero hay que vaciar la base de datos y quitar <code>config/config.php</code> (una operación deliberada, no accesible desde aquí).</p>
    <div class="btns"><a class="btn" href="../">Ir al árbol →</a></div>
    <?php
    pie();
}
