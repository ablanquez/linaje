// ─── Diálogo de confirmación compartido ───────────────────────────────────
// Pieza única y reutilizable para TODAS las confirmaciones (borrar persona,
// papelera, y el futuro panel de administración). Resuelve dos problemas:
//   · APILAMIENTO: solo puede haber UN diálogo abierto a la vez. Si ya hay uno,
//     un segundo intento se ignora (no se crean capas encimadas).
//   · DOBLE DISPARO: al confirmar, ambos botones se BLOQUEAN mientras la acción
//     se procesa (onConfirmar puede ser asíncrona), así una misma acción no se
//     dispara dos veces por pulsar rápido.
// El z-index de .dlg-overlay va por ENCIMA de los demás modales (ver estilos.css),
// para que la confirmación se vea siempre limpia sobre lo que la abrió.

// Icono de alerta (triángulo) usado por los diálogos y por el aviso de descendencia.
const ICO_ALERTA = '<svg viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>';

// esc() vive en util.js (único, endurecido: CAL-06).

// ¿Hay ya un diálogo de confirmación abierto? (para no apilar).
function hayDialogoAbierto() { return !!document.querySelector('.dlg-overlay'); }

// Abre un diálogo de confirmación.
//   titulo, textoBoton  → texto plano (se escapa).
//   textoHTML           → cuerpo (HTML ya confiable/escapado por quien llama).
//   avisoHTML           → bloque de aviso opcional (HTML), p. ej. descendencia.
//   onConfirmar         → función (puede devolver promesa). El diálogo se cierra
//                         cuando termina; los botones quedan bloqueados mientras.
// Devuelve el overlay creado, o null si ya había un diálogo abierto.
function confirmarDialogo({ titulo, textoHTML, avisoHTML = '', textoBoton, onConfirmar }) {
  if (hayDialogoAbierto()) return null;   // ← un solo diálogo a la vez: no apilar

  const ov = document.createElement('div');
  ov.className = 'dlg-overlay';
  ov.innerHTML =
    `<div class="dlg-box" role="alertdialog" aria-modal="true">
       <div class="dlg-icono">${ICO_ALERTA}</div>
       <h2 class="dlg-titulo">${esc(titulo)}</h2>
       <p class="dlg-texto">${textoHTML}</p>
       ${avisoHTML}
       <div class="dlg-botones">
         <button type="button" class="dlg-cancelar">Cancelar</button>
         <button type="button" class="dlg-eliminar">${esc(textoBoton)}</button>
       </div>
     </div>`;
  document.body.appendChild(ov);
  requestAnimationFrame(() => ov.classList.add('abierto'));

  const btnCancelar = ov.querySelector('.dlg-cancelar');
  const btnOk = ov.querySelector('.dlg-eliminar');
  let procesando = false;

  const cerrar = () => {
    if (procesando) return;                 // no cerrar mientras se procesa
    ov.remove();
    document.removeEventListener('keydown', onKey);
  };
  const onKey = e => { if (e.key === 'Escape') cerrar(); };
  document.addEventListener('keydown', onKey);
  ov.addEventListener('click', e => { if (e.target === ov) cerrar(); });   // clic en el fondo = cancelar
  btnCancelar.addEventListener('click', cerrar);

  btnOk.addEventListener('click', async () => {
    if (procesando) return;                 // ← anti-doble-disparo
    procesando = true;
    btnOk.disabled = true;
    btnCancelar.disabled = true;
    btnOk.classList.add('procesando');
    try {
      await onConfirmar();
    } finally {
      procesando = false;
      ov.remove();
      document.removeEventListener('keydown', onKey);
    }
  });

  return ov;
}
