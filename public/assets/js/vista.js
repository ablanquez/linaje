// ─── Controles rápidos de VISTA (personales y temporales) ──────────────────
// Popover junto al buscador. Cada usuario ajusta SU vista del árbol —orientación
// (vertical/horizontal) y profundidad de generaciones hacia arriba/abajo— SIN
// afectar a los demás ni tocar la base de datos (igual que el botón de tema). El
// panel de administración fija el valor "por defecto"; esto permite desviarse
// durante la sesión. Al recargar la página, vuelve al defecto guardado.
//
// Se apoya en la vía OFICIAL: aplicarVisualizacion() (arbol.js) llama a
// setOrientation*/setAncestryDepth/setProgenyDepth, que solo tocan store.state;
// aquí NO se llama a apiGuardarAjustes, por eso es temporal.
(function () {
  const btn = document.getElementById('btnVista');
  const pop = document.getElementById('vistaPopover');
  if (!btn || !pop) return;

  const segOrient = pop.querySelector('#vistaOrient');
  const selArriba = pop.querySelector('#vistaArriba');
  const selAbajo  = pop.querySelector('#vistaAbajo');
  const btnReset  = pop.querySelector('#vistaReset');

  // Opciones de profundidad (helper único en util.js: CAL-08).
  [selArriba, selAbajo].forEach(sel => { sel.innerHTML = opcionesProfundidadHTML(); });

  // Aplica una vista al árbol SIN guardar en la BD (reutiliza el helper oficial).
  function aplicar(orient, pa, pb) {
    if (typeof aplicarVisualizacion === 'function') {
      aplicarVisualizacion({ orientacion: orient, prof_arriba: pa, prof_abajo: pb });
    }
    f3Chart.updateTree();
  }

  // Sincroniza los controles con el estado VIVO del árbol (lo que se ve ahora).
  function sincronizar() {
    const st = f3Chart.store.state || {};
    const orient = st.is_horizontal ? 'horizontal' : 'vertical';
    segOrient.querySelectorAll('button').forEach(b => b.classList.toggle('activo', b.dataset.v === orient));
    selArriba.value = String(st.ancestry_depth != null ? st.ancestry_depth : 100);
    selAbajo.value  = String(st.progeny_depth  != null ? st.progeny_depth  : 100);
  }

  function orientActual() {
    const b = segOrient.querySelector('button.activo');
    return b ? b.dataset.v : 'vertical';
  }
  function aplicarDesdeControles() {
    aplicar(orientActual(), parseInt(selArriba.value, 10), parseInt(selAbajo.value, 10));
  }

  // Abrir / cerrar el popover.
  function abrir()  { sincronizar(); pop.hidden = false; btn.classList.add('activo'); }
  function cerrar() { pop.hidden = true;  btn.classList.remove('activo'); }
  btn.addEventListener('click', e => { e.stopPropagation(); pop.hidden ? abrir() : cerrar(); });
  pop.addEventListener('click', e => e.stopPropagation());   // clic dentro no cierra
  document.addEventListener('click', () => { if (!pop.hidden) cerrar(); });   // clic fuera cierra

  // Orientación (control segmentado Vertical / Horizontal).
  segOrient.querySelectorAll('button').forEach(b => b.addEventListener('click', () => {
    segOrient.querySelectorAll('button').forEach(x => x.classList.toggle('activo', x === b));
    aplicarDesdeControles();
  }));
  selArriba.addEventListener('change', aplicarDesdeControles);
  selAbajo.addEventListener('change', aplicarDesdeControles);

  // Restablecer: vuelve al DEFECTO que fija el panel (window.ajustesArbol) o, si no
  // hay nada guardado, a vertical + todas las generaciones.
  btnReset.addEventListener('click', () => {
    const a = window.ajustesArbol || {};
    const orient = a.orientacion === 'horizontal' ? 'horizontal' : 'vertical';
    const pa = a.prof_arriba != null ? parseInt(a.prof_arriba, 10) : 100;
    const pb = a.prof_abajo  != null ? parseInt(a.prof_abajo,  10) : 100;
    aplicar(orient, pa, pb);
    sincronizar();
  });
})();
