// ─── Mejoras propias del formulario (la librería no las cubre nativamente) ──
// Se inyectan por setOnFormCreation (hook oficial, sin polling). setFields solo
// admite text/textarea/select; para "subir foto" y "año/fecha exacta" añadimos
// controles propios, de la forma más limpia posible.

// Reduce una imagen a maxLado px (lado mayor) y devuelve un dataURL JPEG.
function reducirImagen(file, maxLado, callback) {
  const lector = new FileReader();
  lector.onload = () => {
    const img = new Image();
    img.onload = () => {
      const escala = Math.min(1, maxLado / Math.max(img.width, img.height));
      const w = Math.max(1, Math.round(img.width * escala));
      const h = Math.max(1, Math.round(img.height * escala));
      const canvas = document.createElement('canvas');
      canvas.width = w; canvas.height = h;
      canvas.getContext('2d').drawImage(img, 0, 0, w, h);
      callback(canvas.toDataURL('image/jpeg', 0.85));
    };
    img.onerror = () => alert('No se pudo leer la imagen');
    img.src = lector.result;
  };
  lector.readAsDataURL(file);
}

// Busca el .f3-form-field que contiene el input con ese name.
function campoDe(cont, id) {
  return [...cont.querySelectorAll('.f3-form-field')].find(f => f.querySelector('[name="' + id + '"]'));
}

// PUNTO 5 / PASO 7 — Foto: subir archivo al SERVIDOR (api/foto.php, que
// redimensiona con GD) y guardar el NOMBRE del archivo en avatar. La vista
// previa usa una imagen local inmediata.
//   personaId: id de la persona (para previsualizar una foto YA guardada por el
//   portero). Puede venir vacío al crear una persona nueva.
// SEC-10: la ÚNICA vía es subir un archivo; no se admite pegar una URL externa
// (privacidad + contenido mixto + CSP). El campo avatar pasa a ser oculto: solo
// guarda el nombre del archivo subido, el usuario no lo escribe a mano.
function mejorarCampoFoto(cont, personaId) {
  const campo = campoDe(cont, 'avatar');
  if (!campo || campo.dataset.mejorado) return;
  campo.dataset.mejorado = '1';
  const input = campo.querySelector('[name="avatar"]');
  input.type = 'hidden';   // SEC-10: no es editable a mano (solo lo fija "Subir imagen")

  const control = document.createElement('div');
  control.className = 'foto-control';
  const prev = document.createElement('div');
  prev.className = 'foto-prev';
  const file = document.createElement('input');
  file.type = 'file'; file.accept = 'image/*'; file.style.display = 'none';
  const btnSubir = document.createElement('button');
  btnSubir.type = 'button'; btnSubir.className = 'foto-btn'; btnSubir.textContent = 'Subir imagen';
  const btnQuitar = document.createElement('button');
  btnQuitar.type = 'button'; btnQuitar.className = 'foto-quitar'; btnQuitar.textContent = 'Quitar';

  let previewLocal = null;   // URL de objeto de la foto recién elegida (solo para la vista previa)

  // Fuente de la vista previa: la local recién subida manda; si no, se deriva del
  // valor de avatar (archivo nuestro → portero; URL/dataURL → directo).
  function fuentePrev() {
    if (previewLocal) return previewLocal;
    return urlFoto(input.value, personaId);
  }
  function pintarPrev() {
    const src = fuentePrev();
    prev.style.backgroundImage = src ? "url('" + src.replace(/'/g, "\\'") + "')" : '';
    prev.classList.toggle('con-foto', !!src);
  }

  function soltarLocal() { if (previewLocal) { URL.revokeObjectURL(previewLocal); previewLocal = null; } }

  async function subir(f) {
    soltarLocal();
    previewLocal = URL.createObjectURL(f);   // vista previa inmediata, sin esperar al servidor
    pintarPrev();
    btnSubir.disabled = true; btnSubir.textContent = 'Subiendo…';
    try {
      const json = await apiSubirFoto(f);   // única puerta al backend (api.js, JS-2)
      input.value = json.avatar;   // NOMBRE de archivo; se persistirá al Guardar la persona
      input.dispatchEvent(new Event('input', { bubbles: true }));   // que la librería lo registre
    } catch (e) {
      soltarLocal();
      input.value = '';
      input.dispatchEvent(new Event('input', { bubbles: true }));
      alert('No se pudo subir la imagen.\n\n' + e.message);
    } finally {
      btnSubir.disabled = false; btnSubir.textContent = 'Subir imagen';
    }
  }

  btnSubir.addEventListener('click', () => file.click());
  file.addEventListener('change', e => { const f = e.target.files[0]; if (f) subir(f); e.target.value = ''; });
  btnQuitar.addEventListener('click', () => { soltarLocal(); input.value = ''; input.dispatchEvent(new Event('input', { bubbles: true })); });
  // La vista previa se repinta en cada 'input'. Solo si lo escribió el USUARIO
  // (isTrusted) se suelta la vista previa local (p.ej. al pegar una URL a mano);
  // los 'input' que disparamos nosotros al subir NO deben borrar esa vista previa.
  input.addEventListener('input', e => { if (e.isTrusted) soltarLocal(); pintarPrev(); });

  const botones = document.createElement('div');
  botones.className = 'foto-botones';
  botones.append(btnSubir, btnQuitar);
  control.append(prev, botones, file);
  campo.insertBefore(control, input);   // control arriba; el input de URL debajo
  pintarPrev();
}

// PUNTO 6 — Nacimiento/Fallecimiento: alternar entre "Año" y "Fecha exacta".
// El formato del valor codifica el modo: "AAAA" = año, "AAAA-MM-DD" = fecha exacta.
// Retrocompatible con los datos actuales (solo año).
const ES_FECHA = v => /^\d{4}-\d{2}-\d{2}$/.test((v || '').trim());
function mejorarCampoFecha(cont, id) {
  const campo = campoDe(cont, id);
  if (!campo || campo.dataset.mejorado) return;
  campo.dataset.mejorado = '1';
  const input = campo.querySelector('[name="' + id + '"]');

  const toggle = document.createElement('div');
  toggle.className = 'fecha-toggle';
  toggle.innerHTML = '<button type="button" data-modo="anio">Año</button>' +
                     '<button type="button" data-modo="fecha">Fecha exacta</button>';
  campo.insertBefore(toggle, input);

  function aplicar(modo) {
    if (modo === 'fecha') {
      if (!ES_FECHA(input.value)) input.value = '';   // no inventar fecha exacta desde un año
      input.type = 'date';
      input.removeAttribute('maxlength');
    } else {
      const m = (input.value || '').match(/^(\d{4})/);
      // IMPORTANTE: cambiar el tipo a 'text' ANTES de asignar el valor. Un input
      // type="date" rechaza una cadena que no sea "AAAA-MM-DD" (un año suelto como
      // "1983" se descartaría a ''), así que asignar el año con el tipo aún en 'date'
      // perdía el año al pasar de "Fecha exacta" a "Año".
      input.type = 'text';
      input.setAttribute('inputmode', 'numeric');
      input.maxLength = 4;
      input.placeholder = 'Año (p. ej. 1950)';
      input.value = m ? m[1] : '';                    // conservar el año si lo había
    }
    toggle.querySelectorAll('button').forEach(b => b.classList.toggle('activo', b.dataset.modo === modo));
    // Avisar de que el valor cambió, para que la validación en vivo (fechas) reaccione.
    input.dispatchEvent(new Event('input', { bubbles: true }));
  }
  toggle.querySelectorAll('button').forEach(b => b.addEventListener('click', () => aplicar(b.dataset.modo)));
  aplicar(ES_FECHA(input.value) ? 'fecha' : 'anio');   // modo inicial según el dato
}

// PUNTO 4 — Separar pareja: quita SOLO el vínculo de pareja entre dos personas,
// manteniendo a los hijos con AMBOS progenitores (no se tocan parents/children).
// La vía nativa (removeRelative) no sirve: obliga a reasignar los hijos a un solo
// progenitor. Hacemos la operación sobre el store (única fuente de verdad) y
// registramos en el historial para que sea deshacible con las flechas nativas.
// nombreCorto() vive en util.js (único: CAL-07).
function separarPareja(idA, idB) {
  const s = f3Chart.store;
  const A = s.getDatum(idA), B = s.getDatum(idB);
  if (!A || !B) return;
  if (!confirm(`¿Separar la pareja ${nombreCorto(A)} – ${nombreCorto(B)}?\nDejan de aparecer como pareja; los hijos siguen colgando de ambos.`)) return;
  // CAL-04: comparar ids como cadena (los ids de family-chart son cadenas, pero un
  // id numérico suelto no debe romper el filtrado con !== estricto).
  A.rels.spouses = (A.rels.spouses || []).filter(id => String(id) !== String(idB));
  B.rels.spouses = (B.rels.spouses || []).filter(id => String(id) !== String(idA));
  f3Chart.updateTree();
  f3Edit.updateHistory();        // deshacible con las flechas de la librería
  f3Edit.openFormWithId(idA);    // refrescar la ficha (actualiza la sección Pareja)
}
// Inyecta la sección "Pareja" con un botón "Separar" por cada cónyuge, solo en
// la ficha de EDICIÓN de una persona existente que tenga pareja(s).
function mejorarSepararPareja(cont, form_creator) {
  const form = cont.querySelector('.f3-form');
  if (!form || form.dataset.separarPuesto) return;
  if (!form_creator || !form_creator.datum_id) return;
  if (!form.querySelector('input[name="first name"]')) return;   // solo en edición (no lectura)
  const persona = f3Chart.store.getDatum(form_creator.datum_id);
  if (!persona || !persona.rels) return;   // CAL-05: guardar el acceso a .rels
  const parejas = (persona.rels.spouses || []).map(id => f3Chart.store.getDatum(id)).filter(Boolean);
  if (!parejas.length) return;
  form.dataset.separarPuesto = '1';

  const sec = document.createElement('div');
  sec.className = 'separar-pareja';
  sec.innerHTML = '<div class="separar-hdr">Pareja</div>';
  parejas.forEach(sp => {
    const fila = document.createElement('div');
    fila.className = 'separar-fila';
    const etq = document.createElement('span');
    etq.textContent = nombreCorto(sp);
    const btn = document.createElement('button');
    btn.type = 'button'; btn.className = 'separar-btn'; btn.textContent = 'Separar';
    btn.addEventListener('click', () => separarPareja(persona.id, sp.id));
    fila.append(etq, btn);
    sec.appendChild(fila);
  });
  const hr = form.querySelector('hr');
  if (hr) form.insertBefore(sec, hr); else form.appendChild(sec);
}

// PUNTO F1-E — Ficha de LECTURA rediseñada. La librería pinta la ficha de solo
// lectura como una lista cruda (incluidos campos vacíos). Solo en modo lectura
// (form .non-editable), ocultamos ese render (por CSS) e inyectamos una ficha
// bonita: círculo con aro por sexo + nombre completo + solo campos con contenido.
// formatFecha() se retiró: el formateo de fecha vive en util.js (formatearFecha, JS-3).
// Sincroniza la clase body.ficha-modal (que activa el modal de lectura por CSS)
// con el estado real del panel, y cierra al pulsar el fondo. Se instala una vez.
// Vía event-driven (MutationObserver), sin polling ni :has().
let modalLecturaListo = false;
function setupModalLectura() {
  if (modalLecturaListo) return;
  const panel = document.querySelector('.f3-form-cont');
  if (!panel) return;
  modalLecturaListo = true;
  const sync = () => document.body.classList.toggle('ficha-modal',
    panel.classList.contains('opened') && !!panel.querySelector('.f3-form.non-editable'));
  new MutationObserver(sync).observe(panel, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
  panel.addEventListener('click', ev => {
    if (document.body.classList.contains('ficha-modal') && !ev.target.closest('.f3-form')) f3Edit.closeForm();
  });
  sync();
}

// Iconos discretos para las cabeceras de bloque (SVG, siguen el tema por currentColor).
const ICO_FECHAS = '<svg viewBox="0 0 24 24"><path d="M7 2v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2H7zm12 7v10H5V9h14z"/></svg>';
const ICO_DATOS  = '<svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>';
const ICO_FAMILIA = '<svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 3-1.34 3-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>';

// Construye un bloque (cabecera con icono+título + filas con valor). '' si vacío.
// `clase` permite colorear el icono de cada bloque de forma distinta.
// La cabecera lleva una flecha (chevron) que solo se ve en móvil/tablet, donde la
// sección funciona como ACORDEÓN (plegable). El contenido va envuelto en
// .fl-grupo-body para poder ocultarlo al plegar. En escritorio se ve todo siempre.
function bloqueLectura(clase, icono, titulo, pares) {
  const filas = pares
    .filter(([, v]) => v)
    .map(([et, v]) => `<div class="fl-campo"><span class="fl-et">${et}</span><span class="fl-val">${esc(v)}</span></div>`);
  if (!filas.length) return '';
  return `<div class="fl-grupo fl-grupo--${clase}">`
    + `<div class="fl-grupo-hdr">${icono}<span class="fl-grupo-tit">${titulo}</span><span class="fl-chevron" aria-hidden="true"></span></div>`
    + `<div class="fl-grupo-body"><div class="fl-grupo-inner">${filas.join('')}</div></div></div>`;
}

// Bloque "Familia" con SUBSECCIONES (Familia directa / Ascendientes / Descendientes
// / Colaterales / Política). `subsecciones` viene de parentescos() (ficha.js): una
// lista { titulo, tipo:'sangre'|'politica', filas:[[etiqueta,nombres]] }, ya filtrada.
function bloqueFamilia(subsecciones) {
  if (!subsecciones || !subsecciones.length) return '';
  const html = subsecciones.map(s => {
    const filas = s.filas
      .map(([et, v]) => `<div class="fl-campo"><span class="fl-et">${esc(et)}</span><span class="fl-val">${esc(v)}</span></div>`)
      .join('');
    return `<div class="fl-subsec fl-subsec--${s.tipo}"><div class="fl-subsec-hdr">${esc(s.titulo)}</div>${filas}</div>`;
  }).join('');
  return `<div class="fl-grupo fl-grupo--familia">`
    + `<div class="fl-grupo-hdr">${ICO_FAMILIA}<span class="fl-grupo-tit">Familia</span><span class="fl-chevron" aria-hidden="true"></span></div>`
    + `<div class="fl-grupo-body"><div class="fl-grupo-inner">${html}</div></div></div>`;
}

