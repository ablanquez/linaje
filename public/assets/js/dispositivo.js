// ─── Modo lectura / edición ──────────────────────────────────────────────
// Vía oficial: f3Edit.setNoEdit() (lectura) / setEdit() (edición). En lectura,
// la librería abre la ficha en SOLO LECTURA y oculta añadir familiar, editar,
// guardar y eliminar. La botonera Exportar/Importar solo se ve en edición.
let modoEdicion = false;
const btnEditar = document.getElementById('btnEditar');
function aplicarModo() {
  document.body.classList.toggle('editando', modoEdicion);
  btnEditar.classList.toggle('activo', modoEdicion);
  // Botón solo-icono (lápiz): el estado activo se ve por el fondo de acento; el
  // nombre va en el tooltip, que alterna entre "Editar árbol" y "Finalizar edición".
  const etiquetaEditar = modoEdicion ? 'Finalizar edición' : 'Editar árbol';
  btnEditar.title = etiquetaEditar;
  btnEditar.setAttribute('aria-label', etiquetaEditar);
  if (modoEdicion) f3Edit.setEdit(); else f3Edit.setNoEdit();
  if (!modoEdicion) cerrarEdicionTitulo();   // al salir de edición, cerrar edición del título
  // Si estaba activo "añadir familiar", cerrarlo por la vía oficial (limpia los
  // huecos madre/padre/…) para que no queden colgando al cambiar de modo.
  try { if (typeof cancelarAnadirFamiliar === 'function') cancelarAnadirFamiliar(false); } catch (_) {}
  try { f3Edit.closeForm(); } catch (_) {}   // cerrar la ficha al cambiar de modo
  f3Chart.updateTree();
}
// ─── F3-A: móvil/tablet = SOLO LECTURA ───────────────────────────────────
// Criterio: puntero táctil (coarse) O pantalla estrecha (≤1024px, cubre tablet
// en horizontal). Se reevalúa al rotar o redimensionar, así que un mismo aparato
// que gira sigue bien clasificado. En estos dispositivos NO se puede editar de
// ninguna forma: se oculta "Editar árbol" y, si se estaba editando (p.ej. al
// encoger una ventana de escritorio), se fuerza el modo lectura.
const mqSoloLectura = window.matchMedia('(pointer: coarse), (max-width: 1024px)');
// Solo-lectura si el dispositivo lo pide (táctil/estrecho) O si la sesión entró
// con rol de LECTURA (PASO 8). Así el rol reutiliza toda la UX de solo-lectura.
function dispositivoSoloLectura() { return mqSoloLectura.matches || window.rolLectura === true; }
function evaluarDispositivo() {
  document.body.classList.toggle('dispositivo-lectura', dispositivoSoloLectura());
  if (dispositivoSoloLectura() && modoEdicion) { modoEdicion = false; aplicarModo(); }
}
if (mqSoloLectura.addEventListener) mqSoloLectura.addEventListener('change', evaluarDispositivo);
window.addEventListener('orientationchange', evaluarDispositivo);
window.addEventListener('resize', evaluarDispositivo);

btnEditar.addEventListener('click', () => {
  if (dispositivoSoloLectura()) return;   // móvil/tablet: nunca se entra en edición
  modoEdicion = !modoEdicion; aplicarModo();
});
evaluarDispositivo();   // clasificar el dispositivo antes del primer render de modo
aplicarModo();          // arranca en modo LECTURA

