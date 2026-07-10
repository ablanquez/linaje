// Calcula los parentescos COMPLETOS navegando el store (única fuente de verdad),
// deduciéndolos solo de las aristas base (filiación y pareja):
//   · SANGRE ascendente: padres, abuelos, bisabuelos, tatarabuelos y de ahí un
//     genérico "N.º abuelos" (por generación).
//   · SANGRE descendente: hijos, nietos, bisnietos, tataranietos y "N.º nietos".
//   · SANGRE colateral: hermanos (comparten ≥1 progenitor, incluye medios),
//     tíos (hermanos de los padres), tíos abuelos (hermanos de los abuelos),
//     sobrinos (hijos de hermanos), sobrinos nietos (nietos de hermanos), primos
//     hermanos (hijos de los tíos).
//   · PAREJA: cónyuges (todas, si hay varias).
//   · POLÍTICOS: suegros (padres de la pareja), cuñados (hermanos de la pareja +
//     parejas de los hermanos), yernos/nueras (parejas de los hijos).
// Sin repetir persona: gana la categoría MÁS CERCANA (orden de "claim"). Los
// políticos se separan por sexo (fallback combinado si no se sabe). Devuelve una
// lista de SUBSECCIONES { clave, titulo, tipo:'sangre'|'politica', filas:[[et,nombres]] },
// solo las que tengan contenido.
function parentescos(id0) {
  const store = f3Chart.store;
  const id0s = String(id0);
  const getD = id => store.getDatum(id);
  // CAL-05: guardar el acceso a .rels (no solo el datum) por si faltara.
  const padresDe = id => { const d = getD(id); return d && d.rels ? (d.rels.parents || []).map(String) : []; };
  const hijosDe = id => { const d = getD(id); return d && d.rels ? (d.rels.children || []).map(String) : []; };
  const conyugesDe = id => { const d = getD(id); return d && d.rels ? (d.rels.spouses || []).map(String) : []; };
  const uniq = a => [...new Set(a)];
  const todos = store.getData().map(d => String(d.id));
  const hermanosDe = id => {
    const pars = padresDe(id);
    if (!pars.length) return [];
    return todos.filter(x => x !== String(id) && padresDe(x).some(p => pars.includes(p)));
  };

  // Niveles ascendentes ([0]=padres, [1]=abuelos, [2]=bisabuelos, [3]=tatarabuelos, [4+]=genéricos)
  // y descendentes ([0]=hijos, [1]=nietos, ...). Tope alto (12) por si el árbol es muy profundo.
  const subir = (fn) => { const niv = []; let cur = [id0s]; for (let g = 0; g < 12; g++) { const nx = uniq(cur.flatMap(fn)); if (!nx.length) break; niv.push(nx); cur = nx; } return niv; };
  const nivelUp = subir(padresDe);
  const nivelDown = subir(hijosDe);
  const nu = i => nivelUp[i] || [];
  const nd = i => nivelDown[i] || [];

  const conyuges = conyugesDe(id0s);
  const hermanos = hermanosDe(id0s);
  const tios = uniq(nu(0).flatMap(hermanosDe));
  const tiosAbuelos = uniq(nu(1).flatMap(hermanosDe));
  const sobrinos = uniq(hermanos.flatMap(hijosDe));
  const sobrinosNietos = uniq(sobrinos.flatMap(hijosDe));
  const primosHermanos = uniq(tios.flatMap(hijosDe));
  const suegros = uniq(conyuges.flatMap(padresDe));
  const cunados = uniq([...conyuges.flatMap(hermanosDe), ...hermanos.flatMap(conyugesDe)]);
  const yernosNueras = uniq(hijosDe(id0s).flatMap(conyugesDe));

  // Convierte una lista de ids a "Nombre Apellido, Nombre Apellido…".
  const nombres = ids => ids.map(id => { const d = getD(id); return d ? nombreCorto(d) : ''; }).filter(Boolean).join(', ');

  // "claim": asigna cada persona a la primera categoría (más cercana) que la reclame.
  const vistos = new Set([id0s]);
  const claim = ids => { const out = []; for (const x of ids) { if (vistos.has(x)) continue; vistos.add(x); out.push(x); } return out; };

  // Orden de prioridad para el dedup (más cercano primero):
  const cPareja = claim(conyuges);
  const cPadres = claim(nu(0));
  const cHijos = claim(nd(0));
  const cHermanos = claim(hermanos);
  const cAbuelos = claim(nu(1));
  const cNietos = claim(nd(1));
  const cTios = claim(tios);
  const cSobrinos = claim(sobrinos);
  const cPrimos = claim(primosHermanos);
  const cBisabuelos = claim(nu(2));
  const cBisnietos = claim(nd(2));
  const cTiosAbuelos = claim(tiosAbuelos);
  const cSobrinosNietos = claim(sobrinosNietos);
  const cTatarabuelos = claim(nu(3));
  const cTataranietos = claim(nd(3));
  const ascProfundos = []; for (let g = 4; g < nivelUp.length; g++) { const c = claim(nu(g)); if (c.length) ascProfundos.push([`${g}.º abuelos`, nombres(c)]); }
  const descProfundos = []; for (let g = 4; g < nivelDown.length; g++) { const c = claim(nd(g)); if (c.length) descProfundos.push([`${g}.º nietos`, nombres(c)]); }
  const cSuegros = claim(suegros);
  const cCunados = claim(cunados);
  const cYernosNueras = claim(yernosNueras);

  const fila = (label, ids) => ids.length ? [label, nombres(ids)] : null;
  // Filas de políticos separadas por sexo (fallback combinado "M/F" si se desconoce).
  const sexoDe = id => { const d = getD(id); return d && d.data ? d.data.gender : null; };
  const filasSexo = (ids, labM, labF) => {
    const M = ids.filter(i => sexoDe(i) === 'M'), F = ids.filter(i => sexoDe(i) === 'F'), O = ids.filter(i => sexoDe(i) !== 'M' && sexoDe(i) !== 'F');
    const out = [];
    if (M.length) out.push([labM, nombres(M)]);
    if (F.length) out.push([labF, nombres(F)]);
    if (O.length) out.push([labM + '/' + labF, nombres(O)]);
    return out;
  };

  const directa = [fila('Padres', cPadres), fila('Pareja', cPareja), fila('Hijos', cHijos)].filter(Boolean);
  const ascendientes = [fila('Abuelos', cAbuelos), fila('Bisabuelos', cBisabuelos), fila('Tatarabuelos', cTatarabuelos), ...ascProfundos].filter(Boolean);
  const descendientes = [fila('Nietos', cNietos), fila('Bisnietos', cBisnietos), fila('Tataranietos', cTataranietos), ...descProfundos].filter(Boolean);
  const colaterales = [fila('Hermanos', cHermanos), fila('Tíos', cTios), fila('Tíos abuelos', cTiosAbuelos), fila('Sobrinos', cSobrinos), fila('Sobrinos nietos', cSobrinosNietos), fila('Primos hermanos', cPrimos)].filter(Boolean);
  const politica = [...filasSexo(cSuegros, 'Suegro', 'Suegra'), ...filasSexo(cCunados, 'Cuñado', 'Cuñada'), ...filasSexo(cYernosNueras, 'Yerno', 'Nuera')];

  const subs = [];
  if (directa.length) subs.push({ clave: 'directa', titulo: 'Familia directa', tipo: 'sangre', filas: directa });
  if (ascendientes.length) subs.push({ clave: 'ascendientes', titulo: 'Ascendientes', tipo: 'sangre', filas: ascendientes });
  if (descendientes.length) subs.push({ clave: 'descendientes', titulo: 'Descendientes', tipo: 'sangre', filas: descendientes });
  if (colaterales.length) subs.push({ clave: 'colaterales', titulo: 'Colaterales', tipo: 'sangre', filas: colaterales });
  if (politica.length) subs.push({ clave: 'politica', titulo: 'Política', tipo: 'politica', filas: politica });
  return subs;
}

function fichaLecturaBonita(cont, form_creator) {
  const form = cont.querySelector('.f3-form');
  if (!form || !form.classList.contains('non-editable')) return;   // solo modo lectura
  if (form.dataset.lecturaPuesta) return;
  if (!form_creator || !form_creator.datum_id) return;
  const persona = f3Chart.store.getDatum(form_creator.datum_id);
  if (!persona) return;
  form.dataset.lecturaPuesta = '1';

  // Defensa en profundidad (XSS): la vista de lectura POR DEFECTO de la librería pinta
  // los valores de los campos como HTML CRUDO (.f3-info-field-value). La ocultamos por
  // CSS y mostramos nuestra propia ficha ESCAPADA (abajo), pero esos nodos crudos
  // quedarían en el DOM: un nombre/nota con `<img onerror>` crearía el nodo (la CSP
  // estricta ya impide que el manejador inline se ejecute). Los NEUTRALIZAMOS aquí para
  // no depender solo de la CSP —la info se muestra escapada en `.ficha-lectura`—.
  form.querySelectorAll('.f3-info-field-value').forEach(el => { el.textContent = ''; });

  const p = persona.data || {};
  const mujer = p.gender === 'F';
  const foto = urlFoto(p.avatar, persona.id);   // nombre de archivo → foto.php?persona=id
  const nombreCompleto = nombreDe(persona, 'Sin nombre');   // constructor único (CAL-07)
  const circulo = foto
    ? `<div class="fl-circle"><img class="fl-foto" src="${esc(foto)}" alt=""></div>`
    : `<div class="fl-circle">${ICONO_PERSONA}</div>`;

  // Subtítulo: la edad como dato de cabecera (las fechas van en su grupo).
  const e = calcularEdad(p.birthday, p.death);
  const sub = e ? `<div class="fl-sub">${e.edad} años${e.aprox ? ' (aprox.)' : ''}</div>` : '';

  // Tres bloques con cabecera (icono+título), cada uno solo si tiene contenido:
  const grupos = [
    bloqueLectura('fechas', ICO_FECHAS, 'Nacimiento y fallecimiento', [
      ['Nacimiento', formatearFecha(p.birthday)],
      ['Fallecimiento', formatearFecha(p.death)]
    ]),
    bloqueLectura('datos', ICO_DATOS, 'Datos', [
      ['Lugar', p.place],
      ['Profesión', p.occupation],
      ['Notas', p.notes]
    ]),
    bloqueFamilia(parentescos(persona.id))
  ].join('');

  const bloque = document.createElement('div');
  bloque.className = 'ficha-lectura ' + (mujer ? 'female' : 'male');
  bloque.innerHTML =
    `<div class="fl-header">${circulo}<div class="fl-nombre">${esc(nombreCompleto)}</div>${sub}</div>${grupos}`;

  // ACORDEÓN (escritorio Y móvil, coherente): cada cabecera pliega/despliega su
  // sección. Todas arrancan PLEGADAS; se abren al pulsar. Accesible con teclado
  // (Enter/Espacio) y con aria-expanded sincronizado.
  bloque.querySelectorAll('.fl-grupo-hdr').forEach(hdr => {
    const grupo = hdr.parentElement;
    grupo.classList.add('plegado');                 // todas empiezan plegadas
    hdr.setAttribute('role', 'button');
    hdr.setAttribute('tabindex', '0');
    hdr.setAttribute('aria-expanded', 'false');
    const alternar = () => {
      const abierto = grupo.classList.toggle('plegado') === false;   // toggle→true=plegado
      hdr.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    };
    hdr.addEventListener('click', alternar);
    hdr.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); alternar(); }
    });
  });

  const closeBtn = form.querySelector('.f3-close-btn');
  // Ocultar el div superior vacío que deja la librería (el espacio de los iconos).
  if (closeBtn && closeBtn.nextElementSibling && closeBtn.nextElementSibling.tagName === 'DIV') {
    closeBtn.nextElementSibling.style.display = 'none';
  }
  if (closeBtn && closeBtn.nextSibling) form.insertBefore(bloque, closeBtn.nextSibling);
  else form.appendChild(bloque);

  setupModalLectura();   // observer (clase body.ficha-modal) + cierre al pulsar el fondo
}

// Validación EN VIVO del formulario de persona (solo modo edición):
//   · NOMBRE y SEXO obligatorios (asterisco *, rojo + mensaje si faltan, no guarda).
//   · FECHA de nacimiento coherente con progenitores/hijos: si el año es imposible
//     (mismo o anterior al de un progenitor, o dejaría a un hijo en imposible), el
//     campo se marca en ROJO al instante con el motivo, y no se puede guardar.
// Las fechas de progenitores/hijos se leen del store al abrir (comparación al vuelo).
// El SERVIDOR sigue siendo la autoridad (src/Fechas.php); esto es la capa visual.
function configurarValidacionPersona(cont, form_creator) {
  const form = cont.querySelector('.f3-form');
  if (!form || form.classList.contains('non-editable')) return;   // solo edición
  if (form.dataset.validacionPuesta) return;
  form.dataset.validacionPuesta = '1';

  const anio = s => { const y = anioDe(s); return y ? parseInt(y, 10) : null; };   // anioDe() de util.js (JS-4)
  // Nombre corto para el aviso (nombre+ap1, o '#id' si aún no tiene nombre): util.js (CAL-07).
  const etqNombre = d => nombreCorto(d, '#' + d.id);

  // Restricciones de fecha, tomadas del store AL ABRIR. Recorren TODA la ascendencia
  // y descendencia (no solo padres/hijos directos), en espejo de la regla transitiva
  // del servidor (INT-04): así una cadena con años parciales —A(2000)→B(sin año)→
  // C(1990)— también avisa en vivo. maxParent = antepasado de año más TARDÍO;
  // minChild = descendiente de año más TEMPRANO. El propio nodo se excluye.
  const restr = { maxParent: null, parentName: '', minChild: null, childName: '' };
  const idEd = (form_creator && form_creator.datum_id != null) ? String(form_creator.datum_id) : null;
  // Recorre el store por una arista de rels ('parents' | 'children') con visitados.
  const recorrer = (desde, lista, alVisitar) => {
    const vistos = new Set([String(desde)]);
    const pila = [String(desde)];
    while (pila.length) {
      const d = f3Chart.store.getDatum(pila.pop());
      if (!d || !d.rels) continue;
      (d.rels[lista] || []).forEach(x => {
        const sx = String(x);
        if (vistos.has(sx)) return;
        vistos.add(sx);
        const p = f3Chart.store.getDatum(sx);
        if (p) alVisitar(p);
        pila.push(sx);
      });
    }
  };
  if (idEd && f3Chart.store.getDatum(idEd)) {
    recorrer(idEd, 'parents', p => { const a = anio(p.data.birthday); if (a == null) return; if (restr.maxParent == null || a > restr.maxParent) { restr.maxParent = a; restr.parentName = etqNombre(p); } });
    recorrer(idEd, 'children', c => { const a = anio(c.data.birthday); if (a == null) return; if (restr.minChild == null || a < restr.minChild) { restr.minChild = a; restr.childName = etqNombre(c); } });
  }

  // Marca / limpia el error de un campo (borde rojo + mensaje debajo).
  function marcar(wrap, msg) {
    if (!wrap) return;
    wrap.classList.add('campo-error');
    let m = wrap.querySelector('.campo-error-msg');
    if (!m) { m = document.createElement('p'); m.className = 'campo-error-msg'; wrap.appendChild(m); }
    m.textContent = msg;
  }
  function limpiar(wrap) {
    if (!wrap) return;
    wrap.classList.remove('campo-error');
    const m = wrap.querySelector('.campo-error-msg');
    if (m) m.remove();
  }
  function ponerAsterisco(label) {
    if (label && !label.querySelector('.oblig-ast')) {
      const s = document.createElement('span'); s.className = 'oblig-ast'; s.textContent = '*';
      label.appendChild(s);
    }
  }

  // Referencias a los campos.
  const inpNombre = form.querySelector('input[name="first name"]');
  const inpFecha  = form.querySelector('[name="birthday"]');
  const grupoSexo = form.querySelector('.f3-radio-group');
  const wrapNombre = inpNombre ? inpNombre.closest('.f3-form-field') : null;
  const wrapFecha  = inpFecha ? inpFecha.closest('.f3-form-field') : null;

  // Envolver el SEXO como un campo más (etiqueta "Sexo *" + grupo), para marcarlo igual.
  let wrapSexo = null;
  if (grupoSexo) {
    wrapSexo = grupoSexo.closest('.campo-sexo');
    if (!wrapSexo) {
      wrapSexo = document.createElement('div');
      wrapSexo.className = 'f3-form-field campo-sexo';
      const lbl = document.createElement('label');
      lbl.className = 'f3-sexo-label';
      lbl.textContent = 'Sexo';
      ponerAsterisco(lbl);
      grupoSexo.parentNode.insertBefore(wrapSexo, grupoSexo);
      wrapSexo.appendChild(lbl);
      wrapSexo.appendChild(grupoSexo);
    }
  }
  if (wrapNombre) ponerAsterisco(wrapNombre.querySelector('label'));

  // Validadores (devuelven mensaje de error o null).
  const errNombre = () => (inpNombre && inpNombre.value.trim() === '') ? 'El nombre es obligatorio.' : null;
  const errSexo = () => (form.querySelector('input[name="gender"]:checked')) ? null : 'Indica el sexo (Hombre o Mujer).';
  const errFecha = () => {
    if (!inpFecha) return null;
    const Y = anio(inpFecha.value);
    if (Y == null) return null;                    // fecha vacía = válida (opcional)
    if (restr.maxParent != null && Y <= restr.maxParent)
      return 'No puede ser del mismo año ni anterior al de su antepasado/a «' + restr.parentName + '» (' + restr.maxParent + ').';
    if (restr.minChild != null && Y >= restr.minChild)
      return 'Debe ser anterior al de su descendiente «' + restr.childName + '» (' + restr.minChild + ').';
    return null;
  };

  const revNombre = () => { const e = errNombre(); e ? marcar(wrapNombre, e) : limpiar(wrapNombre); return !e; };
  const revSexo = () => { const e = errSexo(); e ? marcar(wrapSexo, e) : limpiar(wrapSexo); return !e; };
  const revFecha = () => { const e = errFecha(); e ? marcar(wrapFecha, e) : limpiar(wrapFecha); return !e; };

  // Live: cada campo se revalida al escribir/cambiar.
  if (inpNombre) inpNombre.addEventListener('input', revNombre);
  if (inpFecha) inpFecha.addEventListener('input', revFecha);
  form.querySelectorAll('input[name="gender"]').forEach(r => r.addEventListener('change', revSexo));

  // La FECHA se marca desde el principio si ya fuera imposible; Nombre/Sexo NO se
  // marcan al abrir (no ser agresivos con una ficha nueva): se marcan al tocar o
  // al intentar guardar.
  revFecha();

  // Guardar: solo si TODO es válido. Si algo falla, marca todo en rojo y NO guarda.
  // Fase de CAPTURA para pararlo antes del handler de la librería.
  const btn = form.querySelector('button[type="submit"]');
  if (btn) {
    btn.addEventListener('click', ev => {
      const ok = [revNombre(), revSexo(), revFecha()].every(Boolean);
      if (!ok) { ev.stopPropagation(); ev.preventDefault(); }
    }, true);
  }
}

// ─── FASE D-a: formulario de EDICIÓN centrado + coreografía "añadir familiar" ──
// TODO esto es ADITIVO y va con gate por clase (body.form-edicion-modal): quitar
// esa clase devuelve el formulario lateral al instante. NO toca el núcleo de
// cancelarAnadirFamiliar ni el arreglo de centrado del árbol.

// Id de la persona desde la que se pulsó "Añadir familiar". Sirve para distinguir
// (por ID, como se confirmó empíricamente) la FASE A —re-render de ESA persona,
// hay que ocultar la tarjeta para ver los huecos— de la FASE B —persona nueva, id
// distinto, hay que mostrar su formulario—.
let origenAddRel = null;

// Rótulo flotante que se muestra en FASE A (mientras se elige un hueco).
const anadirHint = document.createElement('div');
anadirHint.className = 'anadir-hint';
anadirHint.innerHTML = '<span>Elige dónde añadir el familiar</span>'
  + '<button type="button" class="anadir-hint-cancelar">Cancelar</button>';
document.body.appendChild(anadirHint);
anadirHint.querySelector('.anadir-hint-cancelar')
  .addEventListener('click', () => cancelarAnadirFamiliar(true));

// Sincroniza body.form-edicion-modal (hay un formulario EDITABLE abierto → tarjeta
// centrada) y hace de RED DE SEGURIDAD: si el formulario se cierra por CUALQUIER
// vía (pierde la clase "opened"), limpia la coreografía. Observa el contenedor del
// formulario, que ya existe (lo crea editTree en arbol.js).
(function setupModalEdicion() {
  const cont = document.querySelector('.f3-form-cont');
  if (!cont) return;
  const sync = () => {
    const abierto = cont.classList.contains('opened');
    const editable = abierto && !!cont.querySelector('.f3-form:not(.non-editable)');
    document.body.classList.toggle('form-edicion-modal', editable);
    if (!abierto) {                                    // form cerrado → limpiar coreografía
      document.body.classList.remove('anadiendo-familiar');
      origenAddRel = null;
    }
  };
  new MutationObserver(sync).observe(cont, { attributes: true, attributeFilter: ['class'], childList: true, subtree: true });
  sync();

  // "Cancelar" CIERRA el formulario de EDICIÓN. El botón nativo NO cierra: hace
  // editable=false y re-renderiza, dejando la VISTA DE LECTURA de la persona. Como
  // ahora quitamos la X de arriba, "Cancelar" debe cerrar de verdad. Lo interceptamos
  // en fase de CAPTURA sobre el contenedor (se ejecuta antes que el handler nativo del
  // botón) y cerramos por la vía OFICIAL (closeForm). Si se está "añadiendo familiar",
  // delegamos en cancelarAnadirFamiliar (sale del modo + limpia huecos + cierra). En
  // LECTURA no hay "Cancelar", así que no interfiere con su × redondo de cerrar.
  cont.addEventListener('click', e => {
    const btn = e.target.closest && e.target.closest('.f3-cancel-btn');
    if (!btn) return;
    if (!cont.querySelector('.f3-form:not(.non-editable)')) return;   // solo con formulario EDITABLE
    e.stopImmediatePropagation();
    e.preventDefault();
    if (f3Edit.isAddingRelative && f3Edit.isAddingRelative()) {
      cancelarAnadirFamiliar(true);
    } else {
      try { f3Edit.closeForm(); } catch (_) {}
    }
  }, true);
})();

// Coreografía por ID. Se llama al crear/recargar el formulario (setOnFormCreation):
//   · FASE A  → isAddingRelative() y el form es la MISMA persona de origen:
//               ocultar la tarjeta (mostrar los huecos + el rótulo flotante).
//   · FASE B  → isAddingRelative() pero id distinto (persona nueva): mostrar.
//   · normal  → no se está añadiendo: mostrar.
function coreografiaAnadir(form_creator) {
  const id = form_creator && form_creator.datum_id != null ? String(form_creator.datum_id) : null;
  if (f3Edit.isAddingRelative() && id && id === origenAddRel) {
    document.body.classList.add('anadiendo-familiar');       // FASE A
  } else {
    document.body.classList.remove('anadiendo-familiar');    // FASE B / edición normal
    if (!f3Edit.isAddingRelative()) origenAddRel = null;
  }
}

// Icono de la sección "Foto" (los de Datos/Fechas/Familia se reutilizan de
// formulario.js: ICO_DATOS, ICO_FECHAS, ICO_FAMILIA).
const ICO_FOTO = '<svg viewBox="0 0 24 24"><path d="M4 5h3l1.5-2h7L17 5h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2zm8 3a4.5 4.5 0 1 0 0 9 4.5 4.5 0 0 0 0-9zm0 2a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5z"/></svg>';

// Icono "persona +" para el botón de acción "Añadir familiar".
const ICO_ANADIR = '<svg viewBox="0 0 24 24"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>';

// ─── FASE D-b: agrupar los campos del formulario de EDICIÓN en SECCIONES ───────
// TODO VISIBLE (sin acordeón: al rellenar no conviene esconder campos). Mueve
// BLOQUES .f3-form-field ENTEROS (que ya llevan dentro sus controles inyectados:
// foto, toggle de fecha, sexo, separar) DESPUÉS de que corran los enhancers, con
// guarda anti-duplicado por formulario. Mover un nodo conserva su estructura, sus
// listeners y las referencias JS que apunten a él, así que la validación/foto/
// fecha/separar siguen funcionando y la librería sigue leyendo los valores por
// [name] (los inputs siguen dentro de .f3-form). Cabeceras NEUTRAS (el azul/rosa
// es solo para el sexo, que aquí se está editando).
function agruparEnSecciones(cont) {
  const form = cont.querySelector('.f3-form');
  if (!form || form.classList.contains('non-editable')) return;   // solo edición
  if (form.dataset.seccionesPuestas) return;
  form.dataset.seccionesPuestas = '1';

  const seccion = (titulo, icono) => {
    const s = document.createElement('div');
    s.className = 'form-seccion';
    s.innerHTML = '<div class="form-seccion-hdr">' + icono + '<span>' + titulo + '</span></div>'
      + '<div class="form-seccion-body"></div>';
    return s;
  };
  const cuerpo = s => s.querySelector('.form-seccion-body');
  const campo = name => {
    const inp = form.querySelector('.f3-form-field [name="' + name + '"]');
    return inp ? inp.closest('.f3-form-field') : null;
  };
  const mover = (destino, nodo) => { if (nodo) cuerpo(destino).appendChild(nodo); };

  const secDatos   = seccion('Datos personales', ICO_DATOS);
  const secFechas  = seccion('Fechas', ICO_FECHAS);
  const secFamilia = seccion('Familia / pareja', ICO_FAMILIA);
  const secFoto    = seccion('Foto', ICO_FOTO);

  mover(secDatos, campo('first name'));
  mover(secDatos, campo('last name'));
  mover(secDatos, campo('last name 2'));
  mover(secDatos, form.querySelector('.campo-sexo'));   // sexo (envuelto por la validación)
  mover(secDatos, campo('place'));
  mover(secDatos, campo('occupation'));
  mover(secDatos, campo('notes'));
  mover(secFechas, campo('birthday'));
  mover(secFechas, campo('death'));
  const separar = form.querySelector('.separar-pareja');
  mover(secFamilia, separar);
  mover(secFoto, campo('avatar'));

  const wrap = document.createElement('div');
  wrap.className = 'form-secciones';
  wrap.append(secDatos, secFechas);
  if (separar) wrap.append(secFamilia);   // "Familia / pareja" solo si tiene pareja
  wrap.append(secFoto);

  // ── "Añadir familiar": SECCIÓN de ACCIÓN propia (D-a intacto) ──────────────
  // Movemos el <span class="f3-add-relative-btn"> NATIVO a una sección propia y lo
  // reestilizamos como botón con texto. El nodo conserva TODOS sus listeners al
  // moverse (appendChild no los desengancha): el de la LIBRERÍA (entra en modo
  // añadir) y el NUESTRO de captura de D-a (centrar la persona, fijar origenAddRel,
  // activar body.anadiendo-familiar). Así el flujo de "añadir familiar" se dispara
  // EXACTAMENTE igual que antes; solo cambia el aspecto y el sitio. Aditivo y
  // reversible: quitar la sección devolvería el botón, y el gate por clase de D-a
  // (anadiendo-familiar / form-edicion-modal) no depende de dónde viva el botón.
  const btnAdd = form.querySelector('.f3-add-relative-btn');
  if (btnAdd) {
    const topDiv = btnAdd.parentElement;   // <div text-align:right> con [añadir][editar(oculto)]
    const secAnadir = seccion('Añadir familiar', ICO_FAMILIA);
    secAnadir.classList.add('seccion-anadir');
    btnAdd.classList.add('add-familiar-accion');
    btnAdd.innerHTML = ICO_ANADIR + '<span>Añadir familiar</span>';
    btnAdd.setAttribute('role', 'button');
    btnAdd.setAttribute('tabindex', '0');
    btnAdd.setAttribute('title', 'Añadir un familiar: madre, padre, pareja o hijo/a');
    // Enter/Espacio → click (dispara los mismos listeners que el ratón).
    btnAdd.addEventListener('keydown', ev => {
      if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); btnAdd.click(); }
    });
    cuerpo(secAnadir).appendChild(btnAdd);
    wrap.append(secAnadir);
    // El hueco de iconos nativo (arriba a la derecha) queda vacío: lo ocultamos.
    if (topDiv && topDiv !== form) topDiv.style.display = 'none';
  }

  const botones = form.querySelector('.f3-form-buttons');
  form.insertBefore(wrap, botones || null);

  const hr = form.querySelector('hr');    // el <hr> nativo queda huérfano; lo ocultamos
  if (hr) hr.style.display = 'none';
}

// Vía oficial: setOnFormCreation(fn) se ejecuta con el contenedor del formulario
// cada vez que este se crea/recarga (sin polling). Sustituimos los textos fijos.
f3Edit.setOnFormCreation(({ cont, form_creator }) => {
  mejorarCampoFoto(cont, form_creator && form_creator.datum_id);
  mejorarCampoFecha(cont, 'birthday');
  mejorarCampoFecha(cont, 'death');
  mejorarSepararPareja(cont, form_creator);
  fichaLecturaBonita(cont, form_creator);
  configurarValidacionPersona(cont, form_creator);   // validación en vivo (obligatorios + fechas)
  coreografiaAnadir(form_creator);   // D-a: fase A oculta la tarjeta / fase B la muestra
  const traducir = (sel, texto) => { const el = cont.querySelector(sel); if (el) el.textContent = texto; };
  traducir('button[type="submit"]', 'Guardar');
  traducir('.f3-cancel-btn', 'Cancelar');
  traducir('.f3-delete-btn', 'Eliminar');

  // (El cierre por "Cancelar" —incluida la salida del modo "añadir familiar"— se
  // gestiona de forma centralizada en el handler de CAPTURA de setupModalEdicion.)

  // CENTRAR en la persona editada ANTES de activar "Añadir familiar". El modo
  // añadir usa `one_level_rels` y dibuja SOLO el entorno de la persona MAIN; pero
  // family-chart busca el main con `===` y los ids de nodo son CADENAS, así que si
  // el main es un número (o no coincide) cae en `data[0]` (el vértice del árbol) y
  // la vista SALTA a esa rama (síntoma: al añadir familiar a una hoja como Vera, el
  // árbol se iba a los tatarabuelos). Fijando el main a la persona editada COMO
  // CADENA, el modo añadir queda centrado en ELLA y muestra sus huecos con calma.
  // En fase de CAPTURA para ejecutarnos antes del handler de la librería.
  const addBtn = cont.querySelector('.f3-add-relative-btn');
  if (addBtn && !addBtn.dataset.centrar && form_creator && form_creator.datum_id != null) {
    addBtn.dataset.centrar = '1';
    addBtn.addEventListener('click', () => {
      const idEd = String(form_creator.datum_id);
      if (f3Chart.store.getDatum(idEd)) f3Chart.store.updateMainId(idEd);
      origenAddRel = idEd;                                 // D-a: recordar el origen (fase A)
      document.body.classList.add('anadiendo-familiar');  // D-a: ocultar tarjeta al elegir hueco
    }, true);
  }

  // Si la librería ha DESHABILITADO "Eliminar" (setCanDelete → la persona quedaría
  // como tarjeta sin nombre y no iría a la papelera), explicamos por qué y, si
  // tiene pareja, sugerimos "Separar pareja" como alternativa.
  // SOLO en modo EDICIÓN: en lectura la librería deshabilita "Eliminar" en todas
  // las personas (no_edit), así que la nota no debe aparecer ahí.
  const formEdit = cont.querySelector('.f3-form');
  const enEdicion = formEdit && !formEdit.classList.contains('non-editable');
  const btnBorrar = cont.querySelector('.f3-delete-btn');
  if (enEdicion && btnBorrar && btnBorrar.disabled && form_creator && form_creator.datum_id) {
    const datum = f3Chart.store.getDatum(form_creator.datum_id);
    const tienePareja = !!(datum && datum.rels && (datum.rels.spouses || []).length);
    btnBorrar.title = 'No se puede eliminar: al quitarla, parte de la familia quedaría suelta.';
    const cont2 = btnBorrar.parentNode;
    if (cont2 && !cont2.querySelector('.f3-delete-nota')) {
      // Nota general (correcta en todos los casos): se bloquea cuando, al quitar a
      // la persona, algún familiar dejaría de estar conectado al árbol. Se explica
      // por qué una madre/padre con hijos a veces SÍ y a veces NO se puede borrar.
      const nota = document.createElement('p');
      nota.className = 'f3-delete-nota';
      nota.innerHTML = 'No se puede eliminar: al quitarla, parte de la familia quedaría suelta '
        + '(hay personas que solo siguen unidas al árbol a través de ella), así que no iría a la '
        + 'papelera —la librería la dejaría como una tarjeta sin nombre—. Otras personas con hijos '
        + 'sí pueden borrarse cuando sus familiares siguen conectados por otro lado.'
        + (tienePareja ? ' Para deshacer solo el vínculo de pareja, usa <strong>Separar pareja</strong>.' : '');
      cont2.appendChild(nota);
    }
  }
  // Botón NATIVO "Quitar relación": sobra (usamos nuestra sección "Separar",
  // que quita el vínculo de pareja dejando a los hijos con ambos progenitores).
  // Lo eliminamos limpiamente: su <div> contenedor solo lo envuelve a él.
  const quitarNativo = cont.querySelector('.f3-remove-relative-btn');
  if (quitarNativo) {
    const env = quitarNativo.parentElement;
    (env && env.children.length === 1 && !env.className ? env : quitarNativo).remove();
  }
  // Sexo (modo edición): cambiar SOLO el nodo de texto de cada opción del radio.
  cont.querySelectorAll('.f3-radio-group label').forEach(label => {
    const input = label.querySelector('input[type="radio"]');
    const texto = input && input.value === 'F' ? 'Mujer' : 'Hombre';
    label.childNodes.forEach(n => { if (n.nodeType === 3 && n.textContent.trim()) n.textContent = ' ' + texto; });
  });
  // Sexo (modo lectura): la ficha muestra el valor crudo "M"/"F"; traducirlo.
  cont.querySelectorAll('.f3-info-field').forEach(f => {
    const label = f.querySelector('.f3-info-field-label');
    const valor = f.querySelector('.f3-info-field-value');
    if (label && valor && label.textContent.trim() === 'Sexo') {
      const v = valor.textContent.trim();
      valor.textContent = v === 'F' ? 'Mujer' : v === 'M' ? 'Hombre' : v;
    }
  });
  agruparEnSecciones(cont);   // D-b: agrupar los campos en secciones (al final, tras los enhancers)
});

// Cierra el modo "añadir familiar" por la VÍA OFICIAL: addRelativeInstance.onCancel()
// desactiva el modo y hace cleanUp de los huecos (madre/padre/cónyuge/hijo). Se
// usa al pulsar Cancelar, al salir de edición y al hacer clic fuera. `cerrarFicha`
// cierra además el formulario. Devuelve true si había algo que cerrar.
function cancelarAnadirFamiliar(cerrarFicha) {
  try {
    if (f3Edit.isAddingRelative && f3Edit.isAddingRelative()) {
      f3Edit.addRelativeInstance.onCancel();          // sale del modo + limpia huecos
      if (cerrarFicha) { try { f3Edit.closeForm(); } catch (_) {} }
      f3Chart.updateTree();
      return true;
    }
  } catch (_) {}
  return false;
}
window.cancelarAnadirFamiliar = cancelarAnadirFamiliar;

// Clic FUERA con el modo "añadir familiar" activo: si se pulsa en el vacío del
// árbol (ni en el formulario, ni en una TARJETA —los huecos madre/padre/… se
// dibujan como `.card_cont` y SÍ deben poder pulsarse para añadir—, ni en el
// propio botón), se cierra el modo por la vía oficial.
document.addEventListener('click', e => {
  if (!(f3Edit.isAddingRelative && f3Edit.isAddingRelative())) return;
  if (e.target.closest('.f3-form-cont') ||
      e.target.closest('.card_cont') ||
      e.target.closest('.f3-add-relative-btn')) return;
  cancelarAnadirFamiliar(true);
});

// Clic FUERA del formulario en EDICIÓN NORMAL → cerrar (igual que "Cancelar"). El
// contenedor no tiene fondo (pointer-events:none), así que los clics fuera de la
// tarjeta llegan al árbol; aquí los tratamos como "cerrar" por la vía OFICIAL
// (closeForm), no como pasar a lectura.
//   SALVEDAD CRÍTICA: NO cerrar si se está AÑADIENDO familiar. En FASE A los huecos
//   del árbol deben poder pulsarse para elegir dónde añadir (de eso se encarga el
//   handler de ARRIBA, que solo actúa en modo añadir); por eso aquí salimos en cuanto
//   isAddingRelative / body.anadiendo-familiar estén activos. Tampoco cerramos al
//   pulsar una TARJETA (.card_cont → onCardClick abre esa ficha) ni dentro del form.
document.addEventListener('click', e => {
  const cont = document.querySelector('.f3-form-cont');
  if (!cont || !cont.classList.contains('opened')) return;
  if (!cont.querySelector('.f3-form:not(.non-editable)')) return;      // solo formulario EDITABLE
  if (f3Edit.isAddingRelative && f3Edit.isAddingRelative()) return;    // NO en "añadir familiar"
  if (document.body.classList.contains('anadiendo-familiar')) return;  // refuerzo (fase A)
  if (e.target.closest('.f3-form-cont') || e.target.closest('.card_cont')) return;
  try { f3Edit.closeForm(); } catch (_) {}
});

// setOnChange se dispara al final de cada cambio (editar/añadir/quitar).
// Tras AÑADIR un familiar, la librería deja el "modo añadir" activo a propósito
// (para encadenar), dejando los huecos de los demás parentescos abiertos. Como
// aquí queremos añadir de uno en uno, salimos del modo por la vía OFICIAL
// (addRelativeInstance.onCancel(), que hace cleanUp de los placeholders) para que
// los huecos desaparezcan y el árbol quede limpio.
f3Edit.setOnChange(() => {
  if (f3Edit.isAddingRelative()) {
    f3Edit.addRelativeInstance.onCancel();   // sale del modo añadir + limpia placeholders
    f3Edit.closeForm();
    f3Chart.updateTree();
  }
  // La coherencia de fechas (hijo/progenitor) se valida EN VIVO en el formulario
  // (configurarValidacionPersona): si algo es imposible, el campo se marca en rojo
  // y no se puede guardar, así que aquí no hace falta comprobar de nuevo. El
  // SERVIDOR (src/Fechas.php) sigue siendo la autoridad final por si acaso.
  // PASO 6: persistir el cambio en la BD (crear/editar/borrar/vincular/separar).
  // Embudo único: por aquí pasan todos los cambios (acaban en updateHistory→onChange).
  persistirCambios();
});

// Primer render. A partir de aquí SIEMPRE updateTree(); nunca se recrea el chart.
f3Chart.updateTree({ initial: true });

