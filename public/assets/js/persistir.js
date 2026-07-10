// ─── Persistencia de la edición contra la base de datos (PASO 6) ──────────
// Estrategia (opción B, aprobada): tras cada cambio del editor comparamos el
// estado LIMPIO actual (f3Edit.exportData()) con la última instantánea GUARDADA,
// traducimos la diferencia a operaciones (crear/editar/borrar persona,
// vincular/desvincular) y las mandamos al backend. Al terminar, RELEEMOS el
// árbol desde la BD (que devuelve los ids reales) y refrescamos: así lo que se
// ve coincide siempre con lo guardado, y los ids nuevos se resuelven solos.
// Si algo falla, releemos igualmente (se revierte lo no guardado) y avisamos.
//
// Un ÚNICO disparador: f3Edit.setOnChange (ver ficha.js), por el que pasan
// editar, añadir, separar pareja y borrar (todos acaban en updateHistory→onChange).

// Campos que se persisten. avatar = nombre de archivo de la foto (PASO 7), ya cabe
// en la BD; su subida y limpieza las gestiona el backend (Fotos + Personas).
const CAMPOS_PERSISTIR = ['first name', 'last name', 'last name 2', 'gender', 'birthday', 'death', 'place', 'occupation', 'notes', 'avatar'];

// Última instantánea confirmada como guardada (formato exportData).
let snapshotGuardado = [];
// Cola para serializar guardados (evita solapamientos si onChange se dispara seguido).
let colaPersist = Promise.resolve();
// Persona central ya guardada en la BD (para no re-guardar navegaciones repetidas).
let mainGuardado = null;

// ── Estado de la cola de guardado (arreglo de la carrera en borrados/edición
//    consecutivos) ─────────────────────────────────────────────────────────
// Cada cambio CAPTURA de forma SÍNCRONA el estado deseado (f3Edit.exportData())
// en el momento del clic y lo encola. La relectura de la BD (que reemplaza todo
// el store) se hace UNA sola vez, cuando la cola queda VACÍA; así una relectura
// intermedia nunca pisa un cambio que el usuario acaba de hacer y que todavía no
// se ha enviado (que era la causa de que reapareciesen personas ya borradas).
let pendientesPersist = 0;          // nº de cambios encolados sin procesar aún
let algoSeGuardo = false;           // ¿algún cambio de la ráfaga envió algo?
let huboFalloPersist = false;       // ¿falló algún cambio de la ráfaga?
let mensajeFalloPersist = '';       // mensaje concreto del servidor (p.ej. fecha imposible)
// idTemporal (persona nueva, id aleatorio) → idReal de la BD. Vive hasta la
// relectura final, para poder resolver vínculos/ediciones/borrados de personas
// recién creadas aunque abarquen varios cambios encolados.
const mapaTempReal = new Map();

// ── Control de RELECTURA EN VUELO (CONC-01) ─────────────────────────────────
// Mientras recargarDesdeBD() está en marcha, el store se está reemplazando por la
// verdad de la BD (ids temporales → ids reales). Si un cambio del editor se captura
// JUSTO en esa ventana, su diff mezclaría ids temporales con reales y podría
// DUPLICAR una persona y mandar la original a la papelera. Para evitarlo, mientras
// hay una relectura en vuelo NO se captura el cambio: se marca como diferido y se
// procesa DESPUÉS, contra el árbol ya fresco.
let relecturaEnVuelo = false;
let hayCambioDiferido = false;

function fijarInstantanea() { snapshotGuardado = f3Edit.exportData(); }
function fijarMainGuardado(id) { mainGuardado = id != null ? String(id) : null; }

// Sub-objeto solo con los campos persistibles de una persona.
function datosPersona(p) {
  const d = p.data || {};
  const o = {};
  CAMPOS_PERSISTIR.forEach(k => { o[k] = d[k] != null ? d[k] : ''; });
  return o;
}
function datosIguales(a, b) {
  const da = a.data || {}, db = b.data || {};
  return CAMPOS_PERSISTIR.every(k => (da[k] != null ? da[k] : '') === (db[k] != null ? db[k] : ''));
}
function mapaPorId(arr) { const m = new Map(); arr.forEach(p => m.set(String(p.id), p)); return m; }

// CONC-03: sustituye en un estado (exportData) los ids TEMPORALES ya resueltos por
// su id REAL, usando mapaTempReal. Sirve cuando un Deshacer/Rehacer reinyecta un
// snapshot antiguo del historial que todavía tenía el UUID de una persona que, tras
// una relectura, ya vive en la BD con id entero: así el diff se hace en el espacio
// de ids REAL (no la duplica ni manda la original a la papelera). Los ids reales
// (enteros) nunca están en el mapa, así que pasan intactos.
function remapearIdsTemporales(arr) {
  if (!mapaTempReal.size) return arr;
  const R = id => { const s = String(id); return mapaTempReal.has(s) ? mapaTempReal.get(s) : id; };
  return (arr || []).map(p => {
    const rels = p.rels || {};
    const relsR = {};
    for (const k in rels) relsR[k] = Array.isArray(rels[k]) ? rels[k].map(R) : rels[k];
    return Object.assign({}, p, { id: R(p.id), rels: relsR });
  });
}

// Aristas como conjuntos de cadenas, para diferenciar (añadidas / quitadas).
function filiacionSet(arr) {   // "progenitor>hijo" (desde los parents de cada hijo)
  const s = new Set();
  arr.forEach(p => (p.rels && p.rels.parents ? p.rels.parents : []).forEach(par => s.add(String(par) + '>' + String(p.id))));
  return s;
}
function parejaSet(arr) {      // "a|b" con los dos ids ordenados (canónico)
  const s = new Set();
  arr.forEach(p => (p.rels && p.rels.spouses ? p.rels.spouses : []).forEach(sp => {
    const par = [String(p.id), String(sp)].sort();
    s.add(par[0] + '|' + par[1]);
  }));
  return s;
}

// Calcula y envía las operaciones necesarias para que la BD refleje `objetivo`
// (el estado deseado, CAPTURADO de forma síncrona por quien llama, no releído
// aquí: leerlo aquí, en diferido, era lo que se contaminaba con la carrera).
// Devuelve true si envió algo, false si no había nada que guardar.
async function enviarCambios(objetivo) {
  const prev = snapshotGuardado;
  const mapPrev = mapaPorId(prev), mapAct = mapaPorId(objetivo);

  // Personas: creadas / editadas / borradas.
  // RED DE SEGURIDAD (A.2): si la librería hubiera dejado a alguien como nodo
  // 'unknown' (limbo "Sin nombre", con los datos borrados del store), NO se
  // persiste esa ficha en blanco (perdería el nombre): se manda a la PAPELERA
  // (soft-delete) conservando su fila y datos intactos y con las aristas dormidas,
  // así se recupera completa y reconectada. En condiciones normales esto no ocurre
  // (el botón Eliminar está deshabilitado para quien quedaría anónima); es una
  // salvaguarda para casos heredados o imprevistos.
  // Un id TEMPORAL (uuid) ausente = alta NUEVA. Un id REAL (entero) ausente de la
  // BD activa = persona creada esta sesión que un DESHACER mandó a la papelera; un
  // REHACER debe RESTAURARLA (reutiliza su identidad y sus vínculos dormidos), no
  // crear un duplicado que además chocaría con el vínculo viejo (falso "dos padres").
  const esIdReal = id => /^\d+$/.test(String(id));
  const creaciones = [], restauraciones = [], ediciones = [], borrados = [];
  for (const [id, p] of mapAct) {
    if (p.unknown) continue;                     // 'unknown' → se trata como borrado (abajo)
    if (!mapPrev.has(id)) {
      if (esIdReal(id)) restauraciones.push(p);  // id real ausente → estaba en la papelera → restaurar
      else creaciones.push(p);                   // id temporal → alta nueva
    }
    else if (!datosIguales(p, mapPrev.get(id))) ediciones.push(p);
  }
  for (const [id, p] of mapPrev) {
    if (!mapAct.has(id)) borrados.push(id);              // desaparecidos → papelera
    else if (mapAct.get(id).unknown) borrados.push(id);  // quedaron 'unknown' → papelera con datos intactos
  }

  // Aristas: añadidas / quitadas.
  const filPrev = filiacionSet(prev), filAct = filiacionSet(objetivo);
  const parPrev = parejaSet(prev), parAct = parejaSet(objetivo);
  const filAdd = [...filAct].filter(e => !filPrev.has(e));
  const parAdd = [...parAct].filter(e => !parPrev.has(e));
  // Al BORRAR (papelera), las aristas de la persona NO se quitan: quedan
  // "dormidas" con el soft-delete y se reactivan al restaurar (PASO 10). Por eso
  // se excluyen de los "quitados" las aristas que tocan a una persona borrada;
  // el borrado físico definitivo ya las limpia por el ON DELETE CASCADE.
  const borradosSet = new Set(borrados.map(String));
  const tocaBorrado = (a, b) => borradosSet.has(String(a)) || borradosSet.has(String(b));
  const filDel = [...filPrev].filter(e => !filAct.has(e)).filter(e => { const [pr, hi] = e.split('>'); return !tocaBorrado(pr, hi); });
  const parDel = [...parPrev].filter(e => !parAct.has(e)).filter(e => { const [a, b] = e.split('|'); return !tocaBorrado(a, b); });

  const nada = !creaciones.length && !restauraciones.length && !ediciones.length && !borrados.length
    && !filAdd.length && !filDel.length && !parAdd.length && !parDel.length;
  if (nada) return false;

  // INT-03 — TODO EL DIFF EN UN SOLO LOTE ATÓMICO. Antes se enviaba operación a
  // operación (cada una su transacción): si la persona se creaba y su filiación
  // fallaba, quedaba una persona suelta. Ahora el servidor aplica el lote entero en
  // UNA transacción (todo o nada) y resuelve los ids temporales (uuid) de las
  // personas creadas en este mismo lote.
  //
  // Resolución de referencias antes de enviar:
  //   · uuid creado en ESTE lote → se envía tal cual (lo resuelve el servidor);
  //   · uuid de un lote ANTERIOR → ya está en mapaTempReal → se envía su id real;
  //   · id real → se envía tal cual.
  const esCreacionAhora = new Set(creaciones.map(p => String(p.id)));
  const ref = id => {
    const s = String(id);
    if (esCreacionAhora.has(s)) return s;                 // lo resuelve el servidor
    if (mapaTempReal.has(s)) return mapaTempReal.get(s);  // creado en un lote previo
    return s;                                             // ya es id real
  };

  const lote = {
    creaciones: creaciones.map(p => ({ temp: String(p.id), datos: datosPersona(p) })),
    restauraciones: restauraciones.map(p => ref(p.id)),   // ids reales a reactivar (papelera → activa)
    ediciones:  ediciones.map(p => ({ id: ref(p.id), datos: datosPersona(p) })),
    filAdd: filAdd.map(e => { const [pr, hi] = e.split('>'); return { progenitor: ref(pr), hijo: ref(hi) }; }),
    parAdd: parAdd.map(e => { const [a, b] = e.split('|'); return { a: ref(a), b: ref(b) }; }),
    filDel: filDel.map(e => { const [pr, hi] = e.split('>'); return { progenitor: ref(pr), hijo: ref(hi) }; }),
    parDel: parDel.map(e => { const [a, b] = e.split('|'); return { a: ref(a), b: ref(b) }; }),
    borrados: borrados.map(id => ref(id)),
  };

  const resp = await apiGuardarLote(lote);
  // Adoptar los ids reales de las personas creadas (para cambios encolados posteriores
  // que aún las referencien por su uuid, antes de la relectura final).
  if (resp && resp.ids) { for (const k in resp.ids) mapaTempReal.set(String(k), String(resp.ids[k])); }

  // Este objetivo pasa a ser la nueva "verdad guardada" (en espacio de ids del
  // editor). La relectura final adoptará los ids reales y volverá a fijarla.
  snapshotGuardado = objetivo;
  return true;
}

// Relee el árbol desde la BD y refresca la vista (conservando la persona central
// si sigue existiendo). Es la "verdad": deja store y pantalla igual que la BD.
// Marca relecturaEnVuelo durante todo el proceso (CONC-01) y, al terminar, re-basa
// el historial de deshacer/rehacer (CONC-03) y procesa cualquier cambio diferido.
async function recargarDesdeBD() {
  relecturaEnVuelo = true;
  try {
    const { ajustes, personas } = await cargarArbolDesdeBD();
    const mainPrevio = f3Chart.store.getMainId();
    try { f3Edit.closeForm(); } catch (_) {}
    f3Chart.store.updateData(personas);
    const sigue = personas.some(p => String(p.id) === String(mainPrevio));
    const mainId = sigue ? mainPrevio : (ajustes.main_id || (personas[0] && personas[0].id));
    if (mainId) { f3Chart.store.updateMainId(mainId); fijarMainGuardado(mainId); }
    aplicarTituloDesdeAjustes(ajustes);   // el título/subtítulo también son la verdad de la BD
    f3Chart.updateTree();
    // La BD releída ES la verdad: fijamos la instantánea aquí SIEMPRE, para que
    // cualquier vía que recargue (guardado, restaurar desde la papelera…) deje el
    // snapshot coherente y una edición posterior no vea "creaciones" fantasma.
    fijarInstantanea();
    // CONC-03: NO se limpia mapaTempReal ni se re-basa el historial. El historial de
    // deshacer/rehacer sigue vivo (útil: revertir varios pasos), y el mapa temp→real
    // se CONSERVA toda la sesión para que, si un Deshacer reinyecta un estado con un
    // id temporal de una persona ya creada, persistirCambios lo remapee a su id real
    // (ver remapearIdsTemporales) en vez de duplicarla. Cada persona nueva tiene un
    // UUID único y los ids reales son enteros, así que el mapa nunca colisiona.
  } finally {
    relecturaEnVuelo = false;
  }
  // CONC-01: si llegó un cambio mientras releíamos, procesarlo AHORA contra el árbol
  // ya fresco (nunca se capturó un diff temp-vs-real). Solo tras una relectura OK.
  if (hayCambioDiferido) { hayCambioDiferido = false; persistirCambios(); }
}

// Vuelca título/subtítulo de la BD en la tarjeta de título (arbolMeta vive en tema.js).
function aplicarTituloDesdeAjustes(ajustes) {
  if (typeof arbolMeta === 'undefined') return;
  if (ajustes.titulo != null && ajustes.titulo !== '') arbolMeta.titulo = ajustes.titulo;
  if (ajustes.subtitulo != null) arbolMeta.subtitulo = ajustes.subtitulo;
  if (typeof pintarTitulo === 'function') pintarTitulo();
}

// Guarda la PERSONA CENTRAL en la BD (solo si cambió). Se llama al recentrar
// (mini-árbol o buscador). En fallo avisa (la vista no se revierte: es navegación).
async function guardarMainId(id) {
  id = String(id);
  if (id === mainGuardado) return;
  try {
    await apiGuardarAjustes({ main_id: parseInt(id, 10) });
    mainGuardado = id;
  } catch (e) {
    console.error('No se pudo guardar la persona central:', e);
    alert('No se pudo guardar la persona central en la base de datos.\n\n' + e.message);
  }
}

// Guarda TÍTULO/SUBTÍTULO en la BD. En fallo revierte lo mostrado y avisa.
async function guardarTituloEnBD(tituloAnterior, subtituloAnterior) {
  try {
    await apiGuardarAjustes({ titulo: arbolMeta.titulo, subtitulo: arbolMeta.subtitulo });
  } catch (e) {
    arbolMeta.titulo = tituloAnterior;
    arbolMeta.subtitulo = subtituloAnterior;
    if (typeof pintarTitulo === 'function') pintarTitulo();
    console.error('No se pudo guardar el título:', e);
    alert('No se pudo guardar el título en la base de datos.\n\n' + e.message
      + '\n\nSe ha restaurado el título anterior.');
  }
}

// Punto de entrada: se llama desde setOnChange (embudo único de cambios).
//
// CLAVE del arreglo: capturamos el estado deseado (exportData) de forma SÍNCRONA
// AQUÍ, en el momento del cambio, antes de cualquier await. Así el objetivo queda
// "congelado" e inmune a que una relectura o un cambio posterior alteren el store
// mientras este guardado espera su turno en la cola. La relectura de la BD (que
// reemplaza todo el store) se hace UNA sola vez, cuando la cola queda vacía.
function persistirCambios() {
  // CONC-01: si hay una relectura en vuelo, NO capturamos ahora (el store se está
  // reemplazando por la verdad de la BD; capturar aquí mezclaría ids temporales y
  // reales → duplicado + papelera). Marcamos el cambio como diferido; recargarDesdeBD
  // lo re-procesará contra el árbol ya fresco al terminar.
  if (relecturaEnVuelo) { hayCambioDiferido = true; return colaPersist; }

  // Captura SÍNCRONA del estado deseado ahora, con los ids temporales ya resueltos
  // a reales (CONC-03: neutraliza un Deshacer que reinyecte un UUID ya creado).
  const objetivo = remapearIdsTemporales(f3Edit.exportData());

  // ── RED DE SEGURIDAD (integridad de datos) ──────────────────────────────────
  // Un cambio legítimo (editar, añadir, separar, borrar UNA persona) nunca hace
  // desaparecer muchas personas a la vez ni deja el árbol en un estado de arranque.
  // Si al procesar un cambio (típicamente un "Deshacer" mal fundado) el estado
  // resultante es ANÓMALO, NO se persiste (no se borra en masa): se descarta y se
  // RECARGA de la BD, que es la verdad. Señales de anomalía:
  //   · aparece el nodo de arranque (`__arranque__`) → nunca debe persistirse;
  //   · el árbol colapsa a ≤1 persona teniendo antes ≥5;
  //   · un SOLO cambio haría desaparecer (a la papelera) demasiadas personas a la
  //     vez (>3 y >1/3 del árbol) — el borrado normal solo quita 1.
  const activos = arr => (arr || []).filter(p => p && !p.unknown);
  const prevAct = activos(snapshotGuardado), objAct = activos(objetivo);
  const nPrev = prevAct.length, nObj = objAct.length;
  const idsObj = new Set(objAct.map(p => String(p.id)));
  const desaparecidos = prevAct.filter(p => !idsObj.has(String(p.id))).length;
  const hayArranque = objAct.some(p => String(p.id) === '__arranque__');
  const colapso = nPrev >= 5 && nObj <= 1;
  const borradoMasivo = nPrev >= 5 && desaparecidos > Math.max(3, Math.floor(nPrev / 3));
  if (hayArranque || colapso || borradoMasivo) {
    console.warn('Red de seguridad: estado anómalo descartado (arranque=' + hayArranque +
      ', ' + nPrev + '→' + nObj + ' personas, desaparecerían ' + desaparecidos +
      '). NO se escribe; se recarga desde la base de datos.');
    // CONC-05: tampoco tragamos aquí el fallo de la relectura (si no, el estado
    // anómalo se queda en pantalla sin aviso). Si falla, avisamos (salvo sesión caducada).
    recargarDesdeBD().catch(e => {
      console.error('No se pudo recargar tras descartar un estado anómalo:', e);
      const login = document.getElementById('loginPantalla');
      if (!(login && !login.hidden)) {
        alert('No se pudo recargar el árbol desde el servidor'
          + (e && e.message ? ' (' + e.message + ')' : '')
          + '.\n\nRecarga la página (F5) para ver el estado real guardado.');
      }
    });
    return colaPersist;
  }

  pendientesPersist++;
  colaPersist = colaPersist.then(async () => {
    // Si un cambio anterior de la ráfaga ya falló, no seguimos enviando: drenamos
    // la cola y al final se relee la BD (revirtiendo lo no guardado) una sola vez.
    if (!huboFalloPersist) {
      try {
        if (await enviarCambios(objetivo)) algoSeGuardo = true;
      } catch (e) {
        huboFalloPersist = true;
        if (!mensajeFalloPersist && e && e.message) mensajeFalloPersist = e.message;
        console.error('No se pudo guardar el cambio:', e);
      }
    }
    pendientesPersist--;
    if (pendientesPersist > 0) return;    // aún quedan cambios en cola: no releer todavía

    // Cola VACÍA. Releemos la verdad de la BD (adopta ids reales y reconcilia) solo
    // si algo cambió o hubo un fallo que revertir. recargarDesdeBD ya re-fija la
    // instantánea y limpia el mapa de ids temporales.
    // CONC-05: NO tragamos el fallo de la relectura. Si falla, la pantalla puede
    // quedar divergente (con ids temporales sin resolver); hay que avisar de verdad
    // y no dar el mensaje engañoso de "se ha recargado".
    let falloRelectura = false, mensajeRelectura = '';
    if (algoSeGuardo || huboFalloPersist) {
      try {
        await recargarDesdeBD();
      } catch (e) {
        falloRelectura = true;
        mensajeRelectura = (e && e.message) ? e.message : '';
        console.error('No se pudo recargar el árbol desde la base de datos:', e);
      }
    }
    const fallo = huboFalloPersist;
    const mensaje = mensajeFalloPersist;
    algoSeGuardo = false;
    huboFalloPersist = false;
    mensajeFalloPersist = '';

    // Si la relectura falló por sesión caducada, cargarArbolDesdeBD ya mostró el
    // login (alPerderSesion): no tapamos ese login con un alert redundante.
    const login = document.getElementById('loginPantalla');
    const sesionCaducada = login && !login.hidden;

    if (fallo && !falloRelectura) {
      // Guardado falló, pero la relectura repuso la verdad: mensaje veraz.
      alert((mensaje ? mensaje + '\n\n' : 'No se pudo guardar algún cambio en la base de datos.\n\n')
        + 'Se ha recargado el árbol tal como está guardado.');
    } else if (fallo && falloRelectura && !sesionCaducada) {
      alert((mensaje ? mensaje + '\n\n' : 'No se pudo guardar algún cambio en la base de datos.\n\n')
        + 'Además, no se pudo recargar el árbol desde el servidor'
        + (mensajeRelectura ? ' (' + mensajeRelectura + ')' : '')
        + '. Recarga la página (F5) para ver el estado real guardado.');
    } else if (!fallo && falloRelectura && !sesionCaducada) {
      // El guardado fue BIEN pero el refresco no: la pantalla puede no reflejar la BD.
      alert('Los cambios se guardaron, pero no se pudo refrescar la vista desde el servidor'
        + (mensajeRelectura ? ' (' + mensajeRelectura + ')' : '')
        + '.\n\nRecarga la página (F5) para ver el estado real guardado.');
    }
  });
  return colaPersist;
}
