// ─── PASO 2: editor oficial (editTree) con el store como única fuente de verdad ───
// El árbol REAL se carga desde la base de datos al iniciar (ver api.js y app.js).
// family-chart necesita al menos un nodo para crear el chart (deriva el main_id
// de data[0]), así que arrancamos con este nodo MÍNIMO de relleno. Nunca se ve:
// el contenedor está oculto (body.cargando-datos) hasta el primer render con los
// datos de la BD, momento en el que store.updateData() lo reemplaza por completo.
document.body.classList.add('cargando-datos');
document.body.classList.add('sesion-pendiente');   // oculta la interfaz hasta decidir login (PASO 8)
const data = [
  { id: "__arranque__", data: { "first name": "", "last name": "", gender: "M" }, rels: {} }
];

// El chart se crea UNA sola vez. Su store es la ÚNICA fuente de verdad.
const f3Chart = f3.createChart('#FamilyChart', data)
  .setTransitionTime(800)
  .setCardXSpacing(240)
  .setCardYSpacing(200)
  // Profundidad del árbol calculado desde la persona central. Por defecto la
  // librería muestra solo un vecindario cercano; para ver la familia COMPLETA
  // (todas las generaciones a la vez, con zoom/desplazamiento) subimos la
  // profundidad hacia arriba (antepasados) y hacia abajo (descendientes). Vía
  // oficial setAncestryDepth/setProgenyDepth. 100 = de sobra para un árbol real.
  .setAncestryDepth(100)
  .setProgenyDepth(100)
  // Vía OFICIAL: no dibujar la tarjeta vacía del "otro progenitor" cuando un hijo
  // solo tiene un progenitor en el árbol (por defecto la librería la muestra como
  // "Unknown"/"Sin nombre"). Así, al mandar a la papelera a un miembro de una
  // pareja con hijos, no queda ningún hueco "Sin nombre" en el árbol. El vínculo
  // real se conserva (dormido) y al restaurar vuelve a aparecer el progenitor.
  .setSingleParentEmptyCard(false)
  .setOrientationVertical();

// ─── Tarjetas circulares (interior propio) ───────────────────────────────
// Muñequito genérico cuando la persona no tiene foto.
const ICONO_PERSONA = '<svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12zm0 2.4c-3.3 0-9.8 1.6-9.8 4.9v2.5h19.6v-2.5c0-3.3-6.5-4.9-9.8-4.9z"/></svg>';
// esc() vive ahora en util.js (único, endurecido: CAL-03/06).

// URL de la foto de una persona. Solo se sirven fotos NUESTRAS (subidas al
// servidor) por el portero foto.php?persona=<id>. SEC-10: NO se sirven URLs
// externas (privacidad + contenido mixto + CSP estricta); un avatar que no sea
// nuestro nombre de archivo se trata como "sin foto". Vacío = sin foto.
function urlFoto(avatar, id) {
  if (!esFotoNuestra(avatar)) return '';        // esFotoNuestra() vive en util.js (JS-5)
  return 'foto.php?persona=' + encodeURIComponent(id);
}

// Interpreta una fecha de persona guardada en el bloque C:
//   "AAAA-MM-DD" = fecha exacta;  "AAAA" = solo año (se toma el 1 de enero).
// Devuelve { date, soloAnio } o null si no hay fecha.
function parseFechaPersona(s) {
  s = (s || '').trim();
  if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return { date: new Date(s + 'T00:00:00'), soloAnio: false };
  const m = s.match(/^(\d{4})/);
  if (m) return { date: new Date(+m[1], 0, 1), soloAnio: true };
  return null;
}
// anioDe() vive ahora en util.js (JS-4): único punto para extraer el año.

// Edad en años. Si vive: hasta hoy. Si falleció: hasta el fallecimiento (deja de
// contar al morir). Marca como aproximada (asterisco) si el nacimiento o el
// fallecimiento usados en el cálculo son solo año. Sin nacimiento -> null.
function calcularEdad(birthday, death) {
  const nac = parseFechaPersona(birthday);
  if (!nac) return null;
  const muerte = parseFechaPersona(death);
  const fin = muerte ? muerte.date : new Date();   // vive -> hasta hoy (exacto)
  let edad = fin.getFullYear() - nac.date.getFullYear();
  const dm = fin.getMonth() - nac.date.getMonth();
  if (dm < 0 || (dm === 0 && fin.getDate() < nac.date.getDate())) edad--;
  if (edad < 0) return null;
  return { edad, aprox: nac.soloAnio || (muerte ? muerte.soloAnio : false) };
}

// Comparador de HERMANOS por edad (vía oficial setSortChildrenFunction): el más
// joven a la IZQUIERDA (primero), el más mayor a la derecha. Sin fecha de
// nacimiento -> al final. Usa parseFechaPersona (año "AAAA" = 1 de enero), así
// ordena bien tanto solo-año como fecha exacta. Se ejecuta dentro de cada
// updateTree(), por lo que se recalcula solo al añadir/editar una fecha.
function comparaHermanosPorEdad(a, b) {
  const fa = parseFechaPersona(a.data && a.data.birthday);
  const fb = parseFechaPersona(b.data && b.data.birthday);
  if (!fa && !fb) return 0;
  if (!fa) return 1;                 // a sin fecha -> al final (derecha)
  if (!fb) return -1;                // b sin fecha -> a va antes
  return fb.date - fa.date;          // fecha mayor (más joven) primero -> izquierda
}

// Genera el interior de cada tarjeta. `d` es el TreeDatum; los datos de la
// persona están en d.data.data. Tres líneas: nombre / edad / años.
function tarjetaInterior(d) {
  const p = d.data.data || {};
  const mujer = p.gender === 'F';
  const nombre = (p['first name'] || '').trim();
  const foto = urlFoto(p.avatar, d.data.id);   // nombre de archivo → foto.php?persona=id
  const circulo = foto
    ? `<div class="fc-circle"><img class="fc-foto" src="${esc(foto)}" alt=""></div>`
    : `<div class="fc-circle">${ICONO_PERSONA}</div>`;

  // Línea 3: años (SIEMPRE solo el año). Si vive, solo el de nacimiento.
  const aNac = anioDe(p.birthday), aFall = anioDe(p.death);
  const fechas = aNac ? (aFall ? `${aNac} – ${aFall}` : aNac) : '';

  // Línea 2: edad (discreta), con asterisco si es aproximada.
  const e = calcularEdad(p.birthday, p.death);
  const edad = e
    ? `<div class="fc-edad"${e.aprox ? ' title="Edad aproximada (solo se conoce el año)"' : ''}>${e.edad}${e.aprox ? '*' : ''} años</div>`
    : '';

  return `<div class="fcard ${mujer ? 'female' : 'male'}">
    ${circulo}
    <div class="fc-txt">
      <div class="fc-nombre">${nombre ? esc(nombre) : 'Sin nombre'}</div>
      ${edad}
      ${fechas ? `<div class="fc-fechas">${esc(fechas)}</div>` : ''}
    </div>
  </div>`;
}

// Vía oficial: CardHtml + setCardInnerHtmlCreator (sustituye el interior de la
// tarjeta oficial, sin reescribir tarjetas por encima con JS).
// setMiniTree(true): muestra el mini-árbol de navegación (los "puntitos") sobre
// las personas que tienen familiares no mostrados, para poder adentrarse/volver
// en las ramas. En CardHtml viene desactivado por defecto (en CardSvg venía on).
const f3Card = f3Chart.setCardHtml()
  .setCardInnerHtmlCreator(tarjetaInterior)
  .setMiniTree(true)
  // El mini-árbol (los "puntitos") debe SOLO navegar (recentrar), no abrir la
  // ficha (eso es el clic en el círculo). Como en la tarjeta HTML su clic
  // burbujea al de la tarjeta, lo interceptamos con el hook oficial por-tarjeta
  // setOnCardUpdate: paramos la propagación y recentramos por la vía nativa.
  .setOnCardUpdate(function (d) {
    // Marcar la tarjeta con el id de persona para poder localizarla (buscador).
    const card = this.querySelector('.card');
    if (card) card.dataset.personId = d.data.id;
    const mini = this.querySelector('.mini-tree');
    if (mini) mini.addEventListener('click', e => {
      e.stopPropagation();
      f3Chart.store.updateMainId(d.data.id);
      f3Chart.updateTree();
      // La navegación por el mini-árbol es TEMPORAL: recentra la vista pero YA NO
      // reescribe la persona central por defecto en la BD (PASO 13, decisión: el
      // "centro por defecto" es estable y solo se cambia desde el panel). Al
      // recargar, el árbol vuelve a centrarse en el centro configurado.
    });
  });

// Ordenar hermanos por edad en todos los niveles (más joven a la izquierda).
// Método oficial: se aplica dentro de cada updateTree(), así se recalcula solo.
f3Chart.setSortChildrenFunction(comparaHermanosPorEdad);

// Editor oficial. Comparte el MISMO store del chart (no hay datos paralelos).
const f3Edit = f3Chart.editTree()
  .setFields([
    { type: 'text',     label: 'Nombre',        id: 'first name'  },
    { type: 'text',     label: 'Apellido 1',    id: 'last name'   },
    { type: 'text',     label: 'Apellido 2',    id: 'last name 2' },
    { type: 'switch',   label: 'Sexo',          id: 'gender', initial_value: 'M',
      options: [ { value: 'M', label: 'Hombre' }, { value: 'F', label: 'Mujer' } ] },
    { type: 'text',     label: 'Nacimiento',    id: 'birthday'    },
    { type: 'text',     label: 'Fallecimiento', id: 'death'       },
    { type: 'text',     label: 'Lugar',         id: 'place'       },
    { type: 'text',     label: 'Profesión',     id: 'occupation'  },
    { type: 'textarea', label: 'Notas',         id: 'notes'       },
    { type: 'text',     label: 'Foto',          id: 'avatar'      }
  ])
  .setEditFirst(true)             // abrir directamente en modo edición
  // Traduce los huecos de "añadir familiar" (el sistema nativo que se activa con
  // el muñequito del formulario). Vía oficial setAddRelLabels: estas 5 etiquetas
  // alimentan tanto los huecos alrededor de la persona como el título del
  // formulario "nuevo", así que con esto queda todo ese modo en español.
  .setAddRelLabels({
    father:   'Añadir padre',
    mother:   'Añadir madre',
    spouse:   'Añadir cónyuge',
    son:      'Añadir hijo',
    daughter: 'Añadir hija'
  });
  // NOTA: "vincular a una persona existente" NO se activa aquí. La librería solo
  // muestra ese desplegable si se llama a setLinkExistingRelConfig; al no llamarlo,
  // queda en estado neutro. Pendiente decidir de qué otra forma se vinculará
  // (ver PENDIENTES.md).

// Al pulsar una tarjeta se abre su ficha (lectura) o formulario (edición) SIN
// recentrar el árbol. (setCardClickOpen recentraba con onCardClickDefault y el
// árbol se movía; la navegación/recentrado queda solo en el mini-árbol.)
f3Card.setOnCardClick((e, d) => f3Edit.open(d.data));

// Tooltips en las flechas de historial (las crea editTree(), existen ya aquí).
const btnDeshacer = document.querySelector('.f3-back-button');
const btnRehacer = document.querySelector('.f3-forward-button');
if (btnDeshacer) btnDeshacer.title = 'Deshacer';
if (btnRehacer) btnRehacer.title = 'Rehacer';

// Botón "Volver al inicio": reconstruye la VISTA INICIAL COMPLETA. Recentra el
// árbol en la persona de INICIO (la de partida, guardada en window.personaInicio;
// hoy = la persona que entró / main_id) y lo ENCUADRA, para deshacer cualquier
// navegación por ramas y volver a la panorámica de partida. Vía OFICIAL:
// updateMainId + updateTree({tree_position:'fit'}) (fit = encuadre animado, como al
// entrar). Funciona en lectura y edición y en cualquier tamaño de pantalla.
const btnVerTodo = document.getElementById('btnVerTodo');
if (btnVerTodo) btnVerTodo.addEventListener('click', () => {
  // Si estaba activo "añadir familiar", salir de ese modo PRIMERO. Si no, la
  // cancelación interna de la librería (que dispara updateTree) secuestra el
  // main_id y el recentrado se encasquilla (no vuelve al inicio al primer clic).
  if (typeof window.cancelarAnadirFamiliar === 'function') window.cancelarAnadirFamiliar(true);
  const inicio = (typeof window !== 'undefined' && window.personaInicio) || f3Chart.store.getMainId();
  // CLAVE: family-chart reconstruye el árbol COMPLETO (con colaterales: hermanos,
  // tíos, primos…) solo si el main_id se pasa como NÚMERO —así lo hace el arranque—;
  // con un id de CADENA colapsa a la línea directa. Por eso lo convertimos a entero
  // para recuperar exactamente la panorámica inicial.
  if (inicio != null && f3Chart.store.getDatum(String(inicio))) {
    const n = parseInt(inicio, 10);
    f3Chart.store.updateMainId(Number.isNaN(n) ? inicio : n);
  }
  f3Chart.updateTree({ tree_position: 'fit' });
});

// ─── Ajustes de VISUALIZACIÓN (PASO 13, panel «Ajustes del árbol») ─────────
// Aplica orientación (vertical/horizontal) y profundidad de generaciones
// (antepasados/descendientes) por la vía OFICIAL (setOrientation*/setAncestry
// Depth/setProgenyDepth, que solo escriben en store.state). No redibuja: quien
// llama decide cuándo hacer updateTree. Se usa al cargar (app.js) y en caliente
// desde el panel. Valores ausentes/ inválidos → se deja lo que hubiera.
function aplicarVisualizacion(ajustes) {
  if (!ajustes) return;
  if (ajustes.orientacion === 'horizontal') f3Chart.setOrientationHorizontal();
  else if (ajustes.orientacion === 'vertical') f3Chart.setOrientationVertical();
  const pa = parseInt(ajustes.prof_arriba, 10);
  if (!Number.isNaN(pa)) f3Chart.setAncestryDepth(pa);
  const pb = parseInt(ajustes.prof_abajo, 10);
  if (!Number.isNaN(pb)) f3Chart.setProgenyDepth(pb);
}
window.aplicarVisualizacion = aplicarVisualizacion;

// ─── Historial deshacer/rehacer: fijar la BASE con el árbol CARGADO ───────────
// PROBLEMA (integridad de datos): al montar el chart, la librería crea el
// historial con el nodo de arranque (`__arranque__`, 1 nodo) como base; cargar el
// árbol real (store.updateData) NO registra un punto de historial, así que el
// PRIMER "Deshacer" revertía a ese estado de 1 nodo y persistir.js mandaba a la
// papelera al resto (colapso a 1 persona).
// ARREGLO (vía OFICIAL): tras cargar el árbol, RE-CREAR el historial con
// f3Edit.createHistory() —cuyo changed() final captura el store ACTUAL (el árbol
// completo) como base—, destruyendo antes los controles viejos para no duplicar
// los botones. Desde ahí, el primer "Deshacer" queda deshabilitado (no hay estado
// anterior) hasta que haya un cambio real, y NUNCA revierte a vacío.
function reiniciarHistorial() {
  try {
    if (!f3Edit || typeof f3Edit.createHistory !== 'function') return;
    if (f3Edit.history && f3Edit.history.controls && typeof f3Edit.history.controls.destroy === 'function') {
      f3Edit.history.controls.destroy();          // quita los controles viejos (evita duplicar)
    }
    f3Edit.history = f3Edit.createHistory();       // base = árbol actual (completo)
    // Re-aplicar los tooltips (los botones se han recreado).
    const bd = document.querySelector('.f3-back-button'); if (bd) bd.title = 'Deshacer';
    const br = document.querySelector('.f3-forward-button'); if (br) br.title = 'Rehacer';
  } catch (e) {
    console.warn('No se pudo reiniciar el historial de deshacer/rehacer:', e);
  }
}
window.reiniciarHistorial = reiniciarHistorial;

