// ─── F2-M: buscador de personas por nombre/apellidos ────────────────────────
// Busca en el store (única fuente de verdad). Al elegir una persona, la fija
// como principal y centra la vista en ella por la vía oficial:
// updateMainId(id) + updateTree({ tree_position: 'main_to_middle' }), y resalta
// su tarjeta un instante. Funciona en lectura y en edición.
(function () {
  const cont = document.getElementById('buscador');
  const btn = document.getElementById('btnBuscar');
  const input = document.getElementById('buscadorInput');
  const limpiar = document.getElementById('btnBuscarLimpiar');
  const lista = document.getElementById('buscadorResultados');
  const MAX = 8;
  let idsActuales = [], idxActivo = -1;

  // Normaliza para comparar sin distinguir mayúsculas ni acentos.
  const norm = s => (s || '').toString().normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
  // Nombre completo (sin fallback → '' si no hay nombre, para filtrar) desde util.js.
  const nombreLargo = p => nombreDe(p);
  // rangoAnios()/anioDe() viven en util.js (JS-4).
  const aniosVida = p => rangoAnios(p.birthday, p.death, 'vida');

  function abrir() { cont.classList.add('abierto'); input.focus(); }
  function cerrar() { cont.classList.remove('abierto'); ocultarLista(); }
  function ocultarLista() { lista.classList.remove('visible'); lista.innerHTML = ''; idsActuales = []; idxActivo = -1; }

  // Motivo de coincidencia al buscar por año (se muestra en la línea de datos).
  function motivoAnio(nacio, fallecio, anio) {
    if (nacio && fallecio) return `Nació y falleció en ${anio}`;
    if (nacio) return `Nació en ${anio}`;
    return `Falleció en ${anio}`;
  }

  // Cada resultado es { datum, sub }: sub = texto de la línea de datos (años de
  // vida en la búsqueda por nombre; motivo de coincidencia en la de año).
  function buscar(q) {
    const raw = (q || '').trim();
    const nq = norm(q);
    if (!nq) { ocultarLista(); return; }
    const data = f3Chart.store.getData().filter(d => !d.unknown && nombreLargo(d.data));
    let res;
    if (/^\d{4}$/.test(raw)) {
      // Año (4 dígitos): nacidos O fallecidos ese año. anioDe() saca el año
      // tanto de "AAAA" como de "AAAA-MM-DD", así que compara bien en ambos.
      res = [];
      for (const d of data) {
        const nacio = anioDe(d.data.birthday) === raw;
        const fallecio = anioDe(d.data.death) === raw;
        if (nacio || fallecio) res.push({ datum: d, sub: motivoAnio(nacio, fallecio, raw) });
        if (res.length >= MAX) break;
      }
    } else {
      // Texto: nombre y apellidos (insensible a mayúsculas/acentos).
      res = data.filter(d => norm(nombreLargo(d.data)).includes(nq))
        .slice(0, MAX)
        .map(d => ({ datum: d, sub: aniosVida(d.data) }));
    }
    render(res);
  }

  function render(res) {
    idsActuales = res.map(r => r.datum.id); idxActivo = -1;
    if (!res.length) {
      lista.innerHTML = '<li class="buscador-vacio">Sin coincidencias</li>';
      lista.classList.add('visible');
      return;
    }
    lista.innerHTML = res.map(({ datum: d, sub }) => {
      // CAL-02: la foto se resuelve con urlFoto() como en el resto de la app: una
      // foto SUBIDA (archivo NUESTRO) se sirve por foto.php?persona=<id>; usar
      // p.avatar crudo daba imagen rota para esas fotos.
      const p = d.data, mujer = p.gender === 'F', foto = urlFoto(p.avatar, d.id);
      const circ = foto
        ? `<span class="br-circle"><img src="${esc(foto)}" alt=""></span>`
        : `<span class="br-circle">${ICONO_PERSONA}</span>`;
      return `<li class="buscador-res ${mujer ? 'female' : 'male'}" data-id="${esc(d.id)}">
        ${circ}
        <span class="br-texto">
          <span class="br-nombre">${esc(nombreLargo(p))}</span>
          ${sub ? `<span class="br-datos">${esc(sub)}</span>` : ''}
        </span></li>`;
    }).join('');
    lista.classList.add('visible');
    lista.querySelectorAll('.buscador-res').forEach(li =>
      li.addEventListener('click', () => irAPersona(li.dataset.id)));
  }

  function irAPersona(id) {
    const datum = f3Chart.store.getDatum(id);
    if (!datum) return;
    // Vía oficial: fijar como principal y centrar la vista en esa persona.
    // Recentrado TEMPORAL: ya no se persiste la persona central (PASO 13). El
    // "centro por defecto" es estable y se cambia solo desde el panel de admin.
    f3Chart.store.updateMainId(id);
    f3Chart.updateTree({ tree_position: 'main_to_middle' });
    resaltarTarjeta(id);
    input.value = ''; cerrar();
  }

  // Resalta la tarjeta localizada (se recrea en updateTree; esperamos un tick).
  function resaltarTarjeta(id) {
    setTimeout(() => {
      document.querySelectorAll('#FamilyChart .card.localizada').forEach(c => c.classList.remove('localizada'));
      const cards = document.querySelectorAll(`#FamilyChart .card[data-person-id="${CSS.escape(id)}"]`);
      cards.forEach(c => c.classList.add('localizada'));
      setTimeout(() => cards.forEach(c => c.classList.remove('localizada')), 2800);
    }, 150);
  }

  function marcarActivo(items) {
    items.forEach((it, i) => it.classList.toggle('activo', i === idxActivo));
    if (items[idxActivo]) items[idxActivo].scrollIntoView({ block: 'nearest' });
  }

  btn.addEventListener('click', () => cont.classList.contains('abierto') ? cerrar() : abrir());
  input.addEventListener('input', () => buscar(input.value));
  limpiar.addEventListener('click', () => { input.value = ''; buscar(''); input.focus(); });
  input.addEventListener('keydown', e => {
    const items = lista.querySelectorAll('.buscador-res');
    if (e.key === 'Escape') cerrar();
    else if (e.key === 'ArrowDown' && items.length) { e.preventDefault(); idxActivo = Math.min(idxActivo + 1, items.length - 1); marcarActivo(items); }
    else if (e.key === 'ArrowUp' && items.length) { e.preventDefault(); idxActivo = Math.max(idxActivo - 1, 0); marcarActivo(items); }
    else if (e.key === 'Enter') {
      if (idxActivo >= 0 && items[idxActivo]) irAPersona(items[idxActivo].dataset.id);
      else if (idsActuales.length) irAPersona(idsActuales[0]);
    }
  });
  // Cerrar al pulsar fuera del buscador.
  document.addEventListener('click', e => {
    if (cont.classList.contains('abierto') && !cont.contains(e.target)) cerrar();
  });
})();

