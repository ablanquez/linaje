// ─── Utilidades compartidas del cliente (Tanda Q1 · deduplicación) ───────────
// Un ÚNICO sitio para escapar HTML, construir nombres y las opciones de
// profundidad, para que copias repartidas por varios archivos no se
// desincronicen (CAL-03 / CAL-06 / CAL-07 / CAL-08). Se carga ANTES que el
// resto de scripts de la app (sin dependencias).

// esc(): escapa texto de usuario para insertarlo en innerHTML. Endurecido (CAL-03):
//  · convierte SIEMPRE a cadena (no revienta con números, null u objetos);
//  · escapa también la comilla simple (') → seguro también dentro de atributos '…'.
function esc(v) {
  return String(v == null ? '' : v).replace(/[&<>"']/g, c => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
  ));
}

// Partes limpias [nombre, apellido1, apellido2] de una persona. Acepta tanto un
// DATUM de family-chart (con .data) como un objeto de datos suelto (CAL-07).
function partesNombre(d) {
  const x = d && d.data ? d.data : (d || {});
  return [x['first name'], x['last name'], x['last name 2']].map(s => (s || '').trim());
}
// Nombre COMPLETO (las tres partes). fallback: qué devolver si no hay nombre ('' por defecto).
function nombreDe(d, fallback = '') {
  return partesNombre(d).filter(Boolean).join(' ') || fallback;
}
// Nombre CORTO (nombre + primer apellido). fallback 'Sin nombre' por defecto
// (mismo marcador que el resto de la app para una persona sin nombre).
function nombreCorto(d, fallback = 'Sin nombre') {
  const [n, a] = partesNombre(d);
  return [n, a].filter(Boolean).join(' ') || fallback;
}

// Opciones de un <select> de profundidad de generaciones: "Todas" (100) o 1..8.
// (CAL-08: el mismo bucle vivía duplicado en admin.js y en vista.js.)
function opcionesProfundidadHTML() {
  let html = '<option value="100">Todas</option>';
  for (let i = 1; i <= 8; i++) html += `<option value="${i}">${i}</option>`;
  return html;
}

// ─── Tanda Q2 · deduplicación (SOLO helpers puros, comportamiento 1:1) ───────

// Año (4 dígitos) al inicio de una fecha guardada ("AAAA" o "AAAA-MM-DD"), o ''.
// (JS-4 · antes vivía en arbol.js; único punto para no reimplementar el regex.)
const anioDe = s => { const m = (s || '').trim().match(/^(\d{4})/); return m ? m[1] : ''; };

// Rango de años de vida a partir de nacimiento y fallecimiento. `estilo`:
//   'guion'      → "N – F" | "N" | "F" | ""       (une con guion lo que haya)
//   'nacimiento' → "N – F" | "N" | ""             (exige nacimiento; sin él, "")
//   'vida'       → "N – F" | "n. N" | "† F" | ""  (marca cuál falta)
function rangoAnios(nac, fall, estilo = 'guion') {
  const n = anioDe(nac), f = anioDe(fall);
  if (estilo === 'vida') {
    if (n && f) return `${n} – ${f}`;
    if (n) return `n. ${n}`;
    if (f) return `† ${f}`;
    return '';
  }
  if (estilo === 'nacimiento') return n ? (f ? `${n} – ${f}` : n) : '';
  return [n, f].filter(Boolean).join(' – ');
}

// Fecha guardada → "DD/MM/AAAA". Acepta "AAAA-MM-DD" o un datetime que empiece por
// fecha (ignora la hora); "AAAA" (solo año) u otro texto se devuelven tal cual.
function formatearFecha(s) {
  s = (s || '').trim();
  const m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
  return m ? `${m[3]}/${m[2]}/${m[1]}` : s;
}
// Igual, pero con hora: "AAAA-MM-DD HH:MM(:SS)" → "DD/MM/AAAA HH:MM".
function formatearFechaHora(s) {
  const m = (s || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
  return m ? `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}` : (s || '');
}

// ¿El avatar es un nombre de archivo NUESTRO (32 hex + ".jpg", SEC-10)? Único
// predicado del formato de foto en el cliente (lo usan urlFoto y tieneFoto).
function esFotoNuestra(avatar) {
  return /^[a-f0-9]{32}\.jpg$/.test((avatar || '').trim());
}

// Convierte un texto en "slug" para nombre de archivo (sin acentos, minúsculas,
// solo [a-z0-9] separado por guiones). `fallback` si el resultado queda vacío.
function slugify(texto, fallback = 'arbol') {
  return (texto == null ? '' : String(texto))
    .normalize('NFD').replace(/[̀-ͯ]/g, '')
    .toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || fallback;
}

// Fuerza la descarga de un recurso (data URL, blob URL o URL del servidor).
function descargarArchivo(href, nombre) {
  const a = document.createElement('a');
  a.href = href; a.download = nombre;
  document.body.appendChild(a); a.click(); a.remove();
}
