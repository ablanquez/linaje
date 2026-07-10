// ─── Tema claro/oscuro ───────────────────────────────────────────────────
// Alternar el tema es solo cambiar una clase: fondo, líneas y formulario están
// enganchados a variables CSS, así que se recolorea todo sin redibujar el árbol.
const btnTema = document.getElementById('btnTema');
// Iconos SVG (siguen el tema vía fill: currentColor). El botón ofrece el tema
// CONTRARIO al actual: en claro muestra la luna (ir a oscuro), en oscuro el sol.
const SVG_LUNA = '<svg viewBox="0 0 24 24"><path d="M9.37 5.51A7.35 7.35 0 0 0 9.1 7.5c0 4.08 3.32 7.4 7.4 7.4.68 0 1.35-.09 1.99-.27A7.014 7.014 0 0 1 12 19c-3.86 0-7-3.14-7-7 0-2.93 1.81-5.45 4.37-6.49z"/></svg>';
const SVG_SOL = '<svg viewBox="0 0 24 24"><path d="M12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zM2 13h2a1 1 0 0 0 0-2H2a1 1 0 0 0 0 2zm18 0h2a1 1 0 0 0 0-2h-2a1 1 0 0 0 0 2zM11 2v2a1 1 0 0 0 2 0V2a1 1 0 0 0-2 0zm0 18v2a1 1 0 0 0 2 0v-2a1 1 0 0 0-2 0zM5.99 4.58a1 1 0 0 0-1.41 1.41l1.06 1.06a1 1 0 0 0 1.41-1.41L5.99 4.58zm12.37 12.37a1 1 0 0 0-1.41 1.41l1.06 1.06a1 1 0 0 0 1.41-1.41l-1.06-1.06zm1.06-10.96a1 1 0 0 0-1.41-1.41l-1.06 1.06a1 1 0 0 0 1.41 1.41l1.06-1.06zM7.05 18.36a1 1 0 0 0-1.41-1.41l-1.06 1.06a1 1 0 0 0 1.41 1.41l1.06-1.06z"/></svg>';
function pintarBotonTema() {
  const oscuro = document.body.classList.contains('dark');
  const icono = btnTema.querySelector('.icono');
  if (icono) icono.innerHTML = oscuro ? SVG_SOL : SVG_LUNA;
  const etiqueta = oscuro ? 'Tema claro' : 'Tema oscuro';
  btnTema.title = etiqueta;
  btnTema.setAttribute('aria-label', etiqueta);
}
btnTema.addEventListener('click', () => {
  document.body.classList.toggle('dark');
  pintarBotonTema();
});
pintarBotonTema();

// ─── Título / subtítulo editables (metadatos del árbol) ──────────────────
// Se guardan en memoria (sesión) y se incluyen al Exportar. La tarjeta tiene
// una vista (rótulo) y una edición (campos) que se alternan; el lápiz solo
// aparece en modo edición (clase .solo-edicion).
let arbolMeta = { titulo: 'Nuestro árbol', subtitulo: 'Pulsa una persona para ver sus datos' };
const tcView = document.getElementById('tcView');
const tcEdit = document.getElementById('tcEdit');
const tcTitulo = document.getElementById('tcTitulo');
const tcSubtitulo = document.getElementById('tcSubtitulo');
const tcTituloInput = document.getElementById('tcTituloInput');
const tcSubtituloInput = document.getElementById('tcSubtituloInput');

function pintarTitulo() {
  tcTitulo.textContent = arbolMeta.titulo;
  tcSubtitulo.textContent = arbolMeta.subtitulo;
}
function abrirEdicionTitulo() {
  tcTituloInput.value = arbolMeta.titulo;
  tcSubtituloInput.value = arbolMeta.subtitulo;
  tcView.style.display = 'none';
  tcEdit.style.display = 'block';
  tcTituloInput.focus();
}
function cerrarEdicionTitulo() {
  tcEdit.style.display = 'none';
  tcView.style.display = 'block';
}
function guardarTitulo() {
  const tituloAnterior = arbolMeta.titulo, subtituloAnterior = arbolMeta.subtitulo;
  arbolMeta.titulo = tcTituloInput.value.trim() || 'Nuestro árbol';
  arbolMeta.subtitulo = tcSubtituloInput.value.trim();
  pintarTitulo();
  cerrarEdicionTitulo();
  // PASO 6: persistir en la BD; si falla, guardarTituloEnBD revierte lo mostrado y avisa.
  guardarTituloEnBD(tituloAnterior, subtituloAnterior);
}
document.getElementById('btnEditarTitulo').addEventListener('click', abrirEdicionTitulo);
document.getElementById('tcGuardar').addEventListener('click', guardarTitulo);
document.getElementById('tcCancelar').addEventListener('click', cerrarEdicionTitulo);
[tcTituloInput, tcSubtituloInput].forEach(inp => inp.addEventListener('keydown', e => {
  if (e.key === 'Enter') guardarTitulo();
  else if (e.key === 'Escape') cerrarEdicionTitulo();
}));
pintarTitulo();

