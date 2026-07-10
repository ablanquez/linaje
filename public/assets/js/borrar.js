// ─── F1-J: confirmación antes de borrar una persona ──────────────────────────
// La librería expone el hook oficial setOnDelete(datum, borrar, postSubmit):
// si NO llamamos a borrar()/postSubmit(), no pasa nada (cancelar limpio); si los
// llamamos, completa el borrado y redibuja. Interceptamos ahí para mostrar
// nuestro diálogo en lugar de eliminar directamente.
//
// Comportamiento REAL de la librería al borrar (verificado en el navegador):
// nunca borra descendientes en cascada. Según la topología ocurre una de dos:
//  · si al quitar a la persona nadie queda desconectado del árbol → la elimina
//    de verdad (los hijos siguen colgando de su otro progenitor).
//  · si la persona es el ÚNICO nexo que sostiene una rama → NO la borra: la deja
//    como tarjeta anónima ("desconocido", sin nombre) para no soltar la rama.
// f3.handlers.checkIfRelativesConnectedWithoutPerson(datum, data) predice cuál.

// ICO_ALERTA y el diálogo de confirmación viven ahora en dialogo.js (pieza
// compartida): confirmarDialogo() garantiza un solo diálogo a la vez y bloquea
// el botón al confirmar (anti-doble-disparo).

// ── Regla: solo se puede eliminar si el borrado acaba LIMPIO ────────────────
// El predictor oficial checkIfRelativesConnectedWithoutPerson(datum, data)
// devuelve true si, al quitar a la persona, sus familiares siguen conectados al
// árbol → la librería la elimina de verdad y nosotros la mandamos a la papelera
// (recuperable). Si devuelve false, la persona es el único nexo de una rama: la
// librería NO la borraría, la dejaría como tarjeta anónima (sin nombre) y NO iría
// a la papelera. En ese caso NO permitimos eliminar (botón deshabilitado).
function puedeEliminarseLimpio(datum) {
  try {
    return !!f3.handlers.checkIfRelativesConnectedWithoutPerson(datum, f3Chart.store.getData());
  } catch (e) {
    return true;   // si el predictor fallara, no bloqueamos (no romper el borrado normal)
  }
}

// Devuelve el conjunto de ids de TODOS los descendientes (hijos, nietos...).
function descendientesDe(id) {
  const store = f3Chart.store;
  const acc = new Set();
  (function rec(pid) {
    const d = store.getDatum(pid);
    ((d && d.rels.children) || []).forEach(cid => { if (!acc.has(cid)) { acc.add(cid); rec(cid); } });
  })(id);
  return acc;
}

function abrirDialogoEliminar(datum, onConfirmar) {
  const nombre = nombreCorto(datum);
  const hijos = (datum.rels && datum.rels.children ? datum.rels.children : []).length;
  const total = descendientesDe(datum.id).size;

  // Aquí solo llegamos si la persona SÍ se puede eliminar limpiamente (irá a la
  // papelera); el caso de "quedaría anónima" está bloqueado antes (botón
  // deshabilitado). Si tiene descendencia, avisamos de que no se borra en cascada.
  let aviso = '';
  if (total > 0) {
    const nHijos = hijos === 1 ? '1 hijo/a' : hijos + ' hijos';
    const desc = (hijos === total) ? nHijos : `${nHijos} y ${total} descendientes en total`;
    aviso = `<div class="dlg-aviso">${ICO_ALERTA}<div>Tiene descendencia (${desc}). Sus descendientes <strong>no se borrarán</strong>: seguirán en el árbol a través de su otro progenitor, pero dejarán de estar vinculados a esta persona.</div></div>`;
  }

  // Diálogo compartido: una sola instancia y con anti-doble-disparo.
  confirmarDialogo({
    titulo: 'Eliminar persona',
    textoHTML: `¿Seguro que quieres eliminar a <span class="dlg-nombre">${esc(nombre)}</span> del árbol?`,
    avisoHTML: aviso,
    textoBoton: 'Eliminar',
    onConfirmar
  });
}

// Vía OFICIAL: setCanDelete(fn) hace que la librería pinte el botón "Eliminar"
// con el atributo disabled cuando fn devuelve false. Así el botón queda gris y no
// pulsable en TODAS las casuísticas en las que la persona quedaría anónima.
f3Edit.setCanDelete(puedeEliminarseLimpio);

// Registrar el hook. Solo se dispara al pulsar "Eliminar" (existe en edición).
// Al confirmar: borrar() aplica el borrado, postSubmit() redibuja y registra el
// cambio en el historial (deshacible con la flecha). El formulario de la librería
// es "fixed" (no se autocierra tras borrar), así que lo cerramos con closeForm().
f3Edit.setOnDelete((datum, borrar, postSubmit) => {
  if (!puedeEliminarseLimpio(datum)) return;   // salvaguarda (además del botón deshabilitado)
  abrirDialogoEliminar(datum, () => { borrar(); postSubmit(); f3Edit.closeForm(); });
});

// Traducir los textos internos que la librería pinta en inglés.
