<?php
/**
 * Punto de entrada de la app (antes index.html). Ahora es PHP solo para el
 * CACHE-BUSTING: cada asset (CSS/JS propio y de vendor) se sirve con ?v=<filemtime>.
 * Cuando un archivo cambia, su fecha de modificación cambia → la URL cambia → el
 * navegador descarga la versión nueva SÍ O SÍ (nunca sirve código antiguo
 * cacheado, que fue lo que causó un susto de integridad de datos). filemtime es
 * barato (una llamada al sistema de archivos) y robusto: no hay que mantener
 * números de versión a mano.
 */
declare(strict_types=1);

// SEC-09/SEC-20: cabeceras de seguridad + CSP estricta ANTES de emitir HTML. Todo
// el JS es local (sin CDN), así que script-src 'self' (sin 'unsafe-inline') no rompe
// nada y es la defensa real contra XSS. Se comprueba en el navegador que el árbol,
// las fotos, el panel y la exportación siguen funcionando con la CSP puesta.
require __DIR__ . '/../src/Seguridad.php';
Seguridad::cabecerasHtml();

/** Devuelve la ruta del asset con ?v=<fecha_modificación> para romper la caché. */
function asset(string $rel): string
{
    $abs = __DIR__ . '/' . $rel;
    $v = @filemtime($abs);
    return htmlspecialchars($rel, ENT_QUOTES) . ($v ? '?v=' . $v : '');
}

/**
 * URL absoluta del sitio (esquema + host) para los metadatos Open Graph. Se deriva
 * del host de la petición, así que se adapta sola a cualquier dominio donde se
 * despliegue. El host se escapa (no se confía en él más allá de la previsualización).
 */
function sitioBase(): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scheme = (class_exists('Seguridad') && Seguridad::esHttps()) ? 'https' : 'http';
    return $scheme . '://' . htmlspecialchars($host, ENT_QUOTES);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Linaje · Tu árbol genealógico</title>
<meta name="description" content="Crea, explora y comparte tu árbol genealógico con Linaje: personas, fotos, fechas y parentescos, en tu propio servidor y con tus datos siempre bajo tu control.">
<?php $base = sitioBase(); $ogImg = $base . '/' . asset('assets/img/og.png'); ?>
<!-- Open Graph / Twitter Card: previsualización cuidada al compartir el enlace
     (redes, WhatsApp…). Muestra la marca del SOFTWARE (Linaje) y una descripción
     genérica, NUNCA los datos del árbol del usuario (privacidad). URLs absolutas
     derivadas del host de la petición: se adaptan al dominio real. -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="Linaje">
<meta property="og:title" content="Linaje · Tu árbol genealógico">
<meta property="og:description" content="Crea, explora y comparte tu árbol genealógico: personas, fotos y parentescos, en tu propio servidor.">
<meta property="og:url" content="<?= $base ?>/">
<meta property="og:image" content="<?= $ogImg ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Linaje — Tu árbol genealógico">
<meta property="og:locale" content="es_ES">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Linaje · Tu árbol genealógico">
<meta name="twitter:description" content="Crea, explora y comparte tu árbol genealógico: personas, fotos y parentescos, en tu propio servidor.">
<meta name="twitter:image" content="<?= $ogImg ?>">
<!-- Favicon propio (logo de la marca: árbol de nodos conectados, teal). SVG para
     navegadores modernos, .ico multitamaño (16/32/48) de reserva, y apple-touch
     para iOS. Mismo origen → cumple la CSP (img-src 'self'). -->
<link rel="icon" href="<?= asset('favicon.svg') ?>" type="image/svg+xml">
<link rel="icon" href="<?= asset('favicon.ico') ?>" sizes="any">
<link rel="apple-touch-icon" href="<?= asset('apple-touch-icon.png') ?>">
<link rel="stylesheet" href="<?= asset('assets/vendor/family-chart.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/estilos.css') ?>">
</head>
<body>

<!-- Tarjeta de título (arriba a la izquierda), al contraste del fondo.
     Editable solo en modo edición (lápiz). Título/subtítulo = metadatos del árbol. -->
<div class="titlecard">
  <button class="tc-editar solo-edicion" id="btnEditarTitulo" title="Editar título" aria-label="Editar título">&#9998;</button>
  <svg class="leaf" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Árbol genealógico"><line x1="32" y1="10.5" x2="18" y2="31.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><line x1="32" y1="10.5" x2="46" y2="31.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><line x1="18" y1="31.5" x2="9.5" y2="53.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><line x1="18" y1="31.5" x2="24.5" y2="53.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><line x1="46" y1="31.5" x2="39.5" y2="53.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><line x1="46" y1="31.5" x2="54.5" y2="53.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><circle cx="18" cy="31.5" r="5.6" fill="#0f857d"/><circle cx="46" cy="31.5" r="5.6" fill="#0f857d"/><circle cx="9.5" cy="53.5" r="5" fill="#16a093"/><circle cx="24.5" cy="53.5" r="5" fill="#16a093"/><circle cx="39.5" cy="53.5" r="5" fill="#16a093"/><circle cx="54.5" cy="53.5" r="5" fill="#16a093"/><circle cx="32" cy="10.5" r="6" fill="#0b6b64"/></svg>
  <div class="tc-view" id="tcView">
    <h1 id="tcTitulo">Nuestro árbol</h1>
    <p id="tcSubtitulo">Pulsa una persona para ver sus datos</p>
  </div>
  <div class="tc-edit" id="tcEdit" style="display:none">
    <input id="tcTituloInput" type="text" maxlength="60" placeholder="Título">
    <input id="tcSubtituloInput" type="text" maxlength="90" placeholder="Subtítulo">
    <div class="tc-edit-btns">
      <button id="tcGuardar">Guardar</button>
      <button id="tcCancelar">Cancelar</button>
    </div>
  </div>
</div>

<!-- Barra de controles globales (abajo a la izquierda), en fila. De uso CONSTANTE
     y a la vista: Tema · Editar árbol · Exportar árbol (imagen/PDF, también en
     lectura) · Administración (⚙, solo edición) · Salir. Las tareas puntuales de
     administración (exportar/importar datos, papelera, copias, seguridad, ajustes,
     apariencia, sistema) viven ahora DENTRO del panel de Administración. -->
<div class="toolbar">
  <!-- Botones cuadrados solo-icono (mismo lenguaje que los de arriba: lupa y
       "Volver al inicio"). El nombre de cada uno se muestra como tooltip (title)
       y queda accesible como aria-label. -->
  <button class="fab" id="btnTema" title="Tema oscuro" aria-label="Tema oscuro">
    <span class="icono"><svg viewBox="0 0 24 24"><path d="M9.37 5.51A7.35 7.35 0 0 0 9.1 7.5c0 4.08 3.32 7.4 7.4 7.4.68 0 1.35-.09 1.99-.27A7.014 7.014 0 0 1 12 19c-3.86 0-7-3.14-7-7 0-2.93 1.81-5.45 4.37-6.49z"/></svg></span>
  </button>
  <button class="fab" id="btnEditar" title="Editar árbol" aria-label="Editar árbol">
    <span class="icono"><svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34a.9959.9959 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg></span>
  </button>
  <!-- ÁRBOL ABIERTO (acceso_activo=0): en el MISMO hueco que el lápiz de editar, el
       visitante sin sesión ve la LLAVE para acceder como administrador. El lápiz es
       "editar" (con sesión); la llave es "acceder para poder editar" (sin sesión):
       misma función según el estado, mismo sitio. Relleno TEAL para destacar como
       puerta de entrada. Solo visible en body.modo-abierto-anon (lo controla el CSS,
       que a la vez oculta el lápiz por ser rol de lectura). -->
  <button type="button" class="fab fab-acceder" id="btnAccederAdmin" title="Acceder para administrar" aria-label="Acceder para administrar">
    <span class="icono"><svg viewBox="0 0 24 24"><path d="M12.65 10A5.99 5.99 0 0 0 7 6c-3.31 0-6 2.69-6 6s2.69 6 6 6a5.99 5.99 0 0 0 5.65-4H17v4h4v-4h2v-4H12.65zM7 14c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/></svg></span>
  </button>
  <!-- Exportar el árbol como imagen o PDF (disponible en lectura y edición) -->
  <div class="fab-exportar-wrap">
    <button class="fab" id="btnExportarArbol" title="Exportar árbol (imagen o PDF)" aria-label="Exportar árbol (imagen o PDF)">
      <span class="icono"><svg viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg></span>
    </button>
    <div class="exportar-menu" id="exportarMenu">
      <button type="button" id="btnExpPNG">Imagen (PNG)</button>
      <button type="button" id="btnExpPDF">Documento (PDF)</button>
    </div>
  </div>
  <button class="fab solo-edicion" id="btnAdmin" title="Administración" aria-label="Administración">
    <span class="icono"><svg viewBox="0 0 24 24"><path d="M19.43 12.98c.04-.32.07-.64.07-.98s-.03-.66-.07-.98l2.11-1.65c.19-.15.24-.42.12-.64l-2-3.46c-.12-.22-.39-.3-.61-.22l-2.49 1c-.52-.4-1.08-.73-1.69-.98l-.38-2.65C14.46 2.18 14.25 2 14 2h-4c-.25 0-.46.18-.49.42l-.38 2.65c-.61.25-1.17.59-1.69.98l-2.49-1c-.23-.09-.49 0-.61.22l-2 3.46c-.13.22-.07.49.12.64l2.11 1.65c-.04.32-.07.65-.07.98s.03.66.07.98l-2.11 1.65c-.19.15-.24.42-.12.64l2 3.46c.12.22.39.3.61.22l2.49-1c.52.4 1.08.73 1.69.98l.38 2.65c.03.24.24.42.49.42h4c.25 0 .46-.18.49-.42l.38-2.65c.61-.25 1.17-.59 1.69-.98l2.49 1c.23.09.49 0 .61-.22l2-3.46c.12-.22.07-.49-.12-.64l-2.11-1.65zM12 15.5c-1.93 0-3.5-1.57-3.5-3.5s1.57-3.5 3.5-3.5 3.5 1.57 3.5 3.5-1.57 3.5-3.5 3.5z"/></svg></span>
  </button>
  <button class="fab" id="btnSalir" title="Salir (cerrar sesión)" aria-label="Salir (cerrar sesión)">
    <span class="icono"><svg viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg></span>
  </button>
</div>

<!-- Buscador de personas (arriba, centrado): lupa que despliega el campo.
     Disponible en modo lectura y edición. -->
<div class="buscador" id="buscador">
  <div class="buscador-controles">
    <!-- Volver al inicio: recentra en la persona principal y encuadra la vista completa -->
    <button class="buscador-vertodo" id="btnVerTodo" title="Volver al inicio" aria-label="Volver al inicio">
      <svg viewBox="0 0 24 24"><path d="M3 3h7v2H5v5H3V3zm11 0h7v7h-2V5h-5V3zM3 14h2v5h5v2H3v-7zm16 0h2v7h-7v-2h5v-5z"/></svg>
    </button>
    <!-- Vista del árbol: controles PERSONALES y TEMPORALES (orientación y profundidad).
         Cada usuario ajusta SU vista sin afectar a los demás ni tocar la BD; el panel
         de administración fija el "por defecto". Disponible en lectura y edición. -->
    <div class="buscador-vista-wrap">
      <button class="buscador-vertodo" id="btnVista" title="Vista del árbol" aria-label="Vista del árbol">
        <svg viewBox="0 0 24 24"><path d="M3 17v2h6v-2H3zM3 5v2h10V5H3zm10 16v-2h8v-2h-8v-2h-2v6h2zM7 9v2H3v2h4v2h2V9H7zm14 4v-2H11v2h10zm-6-4h2V7h4V5h-4V3h-2v6z"/></svg>
      </button>
      <div class="vista-popover" id="vistaPopover" hidden>
        <div class="vista-grupo">
          <span class="vista-lbl">Orientación</span>
          <div class="vista-seg" id="vistaOrient">
            <button type="button" data-v="vertical">Vertical</button>
            <button type="button" data-v="horizontal">Horizontal</button>
          </div>
        </div>
        <div class="vista-grupo">
          <span class="vista-lbl">Antepasados (hacia arriba)</span>
          <select id="vistaArriba"></select>
        </div>
        <div class="vista-grupo">
          <span class="vista-lbl">Descendientes (hacia abajo)</span>
          <select id="vistaAbajo"></select>
        </div>
        <button type="button" class="vista-reset" id="vistaReset">Restablecer al valor por defecto</button>
      </div>
    </div>
    <div class="buscador-caja">
      <button class="buscador-lupa" id="btnBuscar" title="Buscar persona" aria-label="Buscar persona">
        <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27a6.5 6.5 0 1 0-.7.7l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z"/></svg>
      </button>
      <input class="buscador-input" id="buscadorInput" type="text" placeholder="Buscar por nombre o apellido…" autocomplete="off" spellcheck="false">
      <button class="buscador-limpiar" id="btnBuscarLimpiar" title="Limpiar" aria-label="Limpiar">&times;</button>
    </div>
  </div>
  <ul class="buscador-resultados" id="buscadorResultados"></ul>
</div>

<!-- Velo mostrado mientras se genera la imagen/PDF del árbol. -->
<div class="export-overlay" id="exportOverlay">
  <div class="export-caja"><span class="export-spin"></span><span id="exportMsg">Generando imagen…</span></div>
</div>

<!-- Contenedor del árbol. La librería dibuja aquí dentro. -->
<div id="FamilyChart" class="f3"></div>

<!-- Pantalla de ÁRBOL NO INSTALADO. Si el backend aún no está instalado, se
     invita a ir al asistente en vez de mostrar un error. -->
<div id="noInstalado" class="login-overlay" hidden>
  <div class="login-caja">
    <svg class="login-leaf" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Árbol genealógico"><line x1="32" y1="10.5" x2="18" y2="31.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><line x1="32" y1="10.5" x2="46" y2="31.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><line x1="18" y1="31.5" x2="9.5" y2="53.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><line x1="18" y1="31.5" x2="24.5" y2="53.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><line x1="46" y1="31.5" x2="39.5" y2="53.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><line x1="46" y1="31.5" x2="54.5" y2="53.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><circle cx="18" cy="31.5" r="5.6" fill="#0f857d"/><circle cx="46" cy="31.5" r="5.6" fill="#0f857d"/><circle cx="9.5" cy="53.5" r="5" fill="#16a093"/><circle cx="24.5" cy="53.5" r="5" fill="#16a093"/><circle cx="39.5" cy="53.5" r="5" fill="#16a093"/><circle cx="54.5" cy="53.5" r="5" fill="#16a093"/><circle cx="32" cy="10.5" r="6" fill="#0b6b64"/></svg>
    <h1>Aún no está instalado</h1>
    <p class="login-sub">Este árbol genealógico todavía no se ha configurado. El asistente lo pone en marcha en un par de minutos.</p>
    <a class="btn-instalar" href="instalar/">Ir al instalador &rarr;</a>
  </div>
</div>

<!-- Pantalla de ERROR DE CARGA (CAL-01). Si el árbol no se puede cargar (red caída,
     error 500 del servidor…), en vez de dejar la pantalla en blanco se avisa y se
     ofrece reintentar. No se usa para el 401 (sesión), que lleva al login. -->
<div id="errorCarga" class="login-overlay" hidden>
  <div class="login-caja">
    <svg class="login-leaf" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Árbol genealógico"><line x1="32" y1="10.5" x2="18" y2="31.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><line x1="32" y1="10.5" x2="46" y2="31.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><line x1="18" y1="31.5" x2="9.5" y2="53.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><line x1="18" y1="31.5" x2="24.5" y2="53.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><line x1="46" y1="31.5" x2="39.5" y2="53.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><line x1="46" y1="31.5" x2="54.5" y2="53.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><circle cx="18" cy="31.5" r="5.6" fill="#0f857d"/><circle cx="46" cy="31.5" r="5.6" fill="#0f857d"/><circle cx="9.5" cy="53.5" r="5" fill="#16a093"/><circle cx="24.5" cy="53.5" r="5" fill="#16a093"/><circle cx="39.5" cy="53.5" r="5" fill="#16a093"/><circle cx="54.5" cy="53.5" r="5" fill="#16a093"/><circle cx="32" cy="10.5" r="6" fill="#0b6b64"/></svg>
    <h1>No se pudo cargar el árbol</h1>
    <p class="login-sub" id="errorCargaDetalle">Ha ocurrido un problema al cargar el árbol desde el servidor. Comprueba tu conexión e inténtalo de nuevo.</p>
    <button type="button" class="btn-instalar" id="errorCargaReintentar">Reintentar</button>
  </div>
</div>

<!-- Pantalla de ACCESO (login). Cubre todo hasta iniciar sesión. -->
<div id="loginPantalla" class="login-overlay" hidden>
  <form id="loginForm" class="login-caja" autocomplete="off">
    <svg class="login-leaf" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Árbol genealógico"><line x1="32" y1="10.5" x2="18" y2="31.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><line x1="32" y1="10.5" x2="46" y2="31.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><line x1="18" y1="31.5" x2="9.5" y2="53.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><line x1="18" y1="31.5" x2="24.5" y2="53.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><line x1="46" y1="31.5" x2="39.5" y2="53.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><line x1="46" y1="31.5" x2="54.5" y2="53.5" stroke="#0f857d" stroke-width="3.1" stroke-linecap="round" opacity="0.62"/><circle cx="18" cy="31.5" r="5.6" fill="#0f857d"/><circle cx="46" cy="31.5" r="5.6" fill="#0f857d"/><circle cx="9.5" cy="53.5" r="5" fill="#16a093"/><circle cx="24.5" cy="53.5" r="5" fill="#16a093"/><circle cx="39.5" cy="53.5" r="5" fill="#16a093"/><circle cx="54.5" cy="53.5" r="5" fill="#16a093"/><circle cx="32" cy="10.5" r="6" fill="#0b6b64"/></svg>
    <h1>Nuestro árbol</h1>
    <p class="login-sub">Identifícate para acceder</p>
    <label class="login-campo">Nombre
      <input id="loginNombre" type="text" required>
    </label>
    <label class="login-campo">Primer apellido
      <input id="loginApellido" type="text" required>
    </label>
    <label class="login-campo" id="loginFechaCampo">Fecha de nacimiento
      <input id="loginFecha" type="date">
    </label>
    <label class="login-anio-check"><input id="loginSoloAnio" type="checkbox"> Solo sé el año</label>
    <label class="login-campo" id="loginAnioCampo" style="display:none">Año de nacimiento
      <input id="loginAnio" type="number" min="1" max="2999" placeholder="p. ej. 1950">
    </label>
    <label class="login-campo">Clave
      <input id="loginClave" type="password" required>
    </label>
    <div id="loginError" class="login-error" role="alert"></div>
    <button type="submit" id="loginEntrar">Entrar</button>
    <!-- Solo en árbol ABIERTO: permite cerrar el login y seguir viendo sin acceder. -->
    <button type="button" id="loginCancelar" class="login-cancelar" hidden>Seguir viendo sin acceder</button>
  </form>
</div>

<!-- Librerías (locales, ya no desde CDN). Con cache-busting por fecha de archivo. -->
<script src="<?= asset('assets/vendor/d3.min.js') ?>"></script>
<script src="<?= asset('assets/vendor/family-chart.min.js') ?>"></script>
<script src="<?= asset('assets/vendor/html-to-image.js') ?>"></script>
<script src="<?= asset('assets/vendor/jspdf.umd.min.js') ?>"></script>
<!-- Frontend propio, separado por responsabilidad. Scripts clásicos cargados EN ORDEN:
     comparten ámbito global y se ejecutan en la misma secuencia que el index monolítico,
     por lo que el comportamiento es idéntico. Cada uno con ?v=<filemtime>. -->
<script src="<?= asset('assets/js/util.js') ?>"></script>
<script src="<?= asset('assets/js/api.js') ?>"></script>
<script src="<?= asset('assets/js/arbol.js') ?>"></script>
<script src="<?= asset('assets/js/dialogo.js') ?>"></script>
<script src="<?= asset('assets/js/borrar.js') ?>"></script>
<script src="<?= asset('assets/js/formulario.js') ?>"></script>
<script src="<?= asset('assets/js/ficha.js') ?>"></script>
<script src="<?= asset('assets/js/tema.js') ?>"></script>
<script src="<?= asset('assets/js/dispositivo.js') ?>"></script>
<script src="<?= asset('assets/js/datos.js') ?>"></script>
<script src="<?= asset('assets/js/buscador.js') ?>"></script>
<script src="<?= asset('assets/js/vista.js') ?>"></script>
<script src="<?= asset('assets/js/exportar.js') ?>"></script>
<script src="<?= asset('assets/js/persistir.js') ?>"></script>
<script src="<?= asset('assets/js/papelera.js') ?>"></script>
<script src="<?= asset('assets/js/backup.js') ?>"></script>
<script src="<?= asset('assets/js/admin.js') ?>"></script>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
