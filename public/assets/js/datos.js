// ─── Exportar el árbol a JSON (PORTABILIDAD, no respaldo) ──────────────────
// Se dispara desde el bloque «Datos» del panel de administración
// (window.exportarDatos). Su propósito es SACAR los datos del árbol FUERA de la
// app: llevarlos a otro programa, a otro formato, o dárselos a una IA para que
// los procese. NO es una copia de seguridad: el respaldo/recuperación (con
// FOTOS) vive en «Copias de seguridad → Restaurar desde archivo…».
//
// Por eso el JSON:
//   · es AUTOEXPLICATIVO (campos en español, con una descripción dentro);
//   · expresa las RELACIONES de forma explícita y legible (con ids Y nombres),
//     tanto anidadas por persona como en listas globales de aristas;
//   · NO embebe las fotos (harían el archivo enorme e ilegible), pero deja
//     constancia de quién tiene foto con `tiene_foto`.
//
// Fuente de datos: el store de la librería vía la vía oficial f3Edit.exportData()
// (limpia placeholders y formatea). Contiene solo las personas ACTIVAS.

const EXPORT_FORMATO = 'arbol-genealogico-export';
const EXPORT_VERSION = 1;

// ¿La persona tiene una foto subida? El avatar es SIEMPRE un nombre de archivo
// nuestro (`<32hex>.jpg`, SEC-10); la imagen vive en el servidor y no viaja aquí.
function tieneFoto(avatar) {
  return esFotoNuestra(avatar);   // predicado del formato de foto: util.js (JS-5)
}
// 'M' | 'F' | null → texto legible (una IA no debería adivinar qué es "M").
function sexoLegible(g) {
  return g === 'M' ? 'hombre' : g === 'F' ? 'mujer' : 'desconocido';
}
// Campo de texto opcional: null si está vacío (menos ambiguo que "").
function opcional(v) {
  const s = (v == null ? '' : String(v)).trim();
  return s === '' ? null : s;
}

// Construye el OBJETO de exportación (función pura, sin descargar nada: así se
// puede inspeccionar y probar). Devuelve la estructura completa documentada.
function construirExportacion() {
  const personas = f3Edit.exportData() || [];

  // Índice id → nombre completo, para poder nombrar a ambos lados de cada relación.
  const nombrePorId = new Map();
  personas.forEach(p => nombrePorId.set(String(p.id), nombreDe(p, 'Sin nombre')));
  const ref = id => ({ id: String(id), nombre: nombrePorId.get(String(id)) || '(desconocida)' });
  // Solo referencias a personas presentes en la exportación (activas).
  const refs = ids => (ids || []).map(String).filter(i => nombrePorId.has(i)).map(ref);

  const listaPersonas = personas.map(p => {
    const d = p.data || {};
    const rels = p.rels || {};
    return {
      id: String(p.id),
      nombre_completo: nombreDe(p, 'Sin nombre'),
      nombre: opcional(d['first name']),
      apellido1: opcional(d['last name']),
      apellido2: opcional(d['last name 2']),
      sexo: sexoLegible(d.gender),
      nacimiento: opcional(d.birthday),
      fallecimiento: opcional(d.death),
      lugar: opcional(d.place),
      ocupacion: opcional(d.occupation),
      notas: opcional(d.notes),
      tiene_foto: tieneFoto(d.avatar),
      relaciones: {
        progenitores: refs(rels.parents),
        hijos: refs(rels.children),
        parejas: refs(rels.spouses),
      },
    };
  });

  // Listas GLOBALES de relaciones (aristas), con id y nombre a ambos lados.
  // Filiación: se deriva de los progenitores de cada persona (sin duplicar).
  const vistasFil = new Set();
  const filiaciones = [];
  personas.forEach(hijo => {
    ((hijo.rels && hijo.rels.parents) || []).forEach(pid => {
      const clave = String(pid) + '>' + String(hijo.id);
      if (vistasFil.has(clave) || !nombrePorId.has(String(pid))) return;
      vistasFil.add(clave);
      filiaciones.push({
        progenitor_id: String(pid), progenitor: nombrePorId.get(String(pid)),
        hijo_id: String(hijo.id), hijo: nombrePorId.get(String(hijo.id)),
      });
    });
  });
  // Pareja: arista simétrica → se guarda UNA vez, con el par ordenado.
  const vistasPar = new Set();
  const parejas = [];
  personas.forEach(a => {
    ((a.rels && a.rels.spouses) || []).forEach(bid => {
      if (!nombrePorId.has(String(bid))) return;
      const par = [String(a.id), String(bid)].sort();
      const clave = par[0] + '|' + par[1];
      if (vistasPar.has(clave)) return;
      vistasPar.add(clave);
      parejas.push({
        persona_a_id: par[0], persona_a: nombrePorId.get(par[0]),
        persona_b_id: par[1], persona_b: nombrePorId.get(par[1]),
      });
    });
  });

  return {
    formato: EXPORT_FORMATO,
    version: EXPORT_VERSION,
    descripcion: 'Exportación del árbol genealógico en JSON para usarlo FUERA de la aplicación '
      + '(otro programa, otro formato o una IA). Las relaciones se expresan con id y nombre a ambos '
      + 'lados. NO es una copia de seguridad: las fotos no van incluidas (solo se indica quién tiene '
      + 'foto con "tiene_foto"). Para respaldar y recuperar el árbol con fotos, usa «Copias de seguridad».',
    generado_en: new Date().toISOString(),
    notas_de_formato: {
      fechas: 'Cadena "AAAA" (solo año) o "AAAA-MM-DD" (fecha exacta). null si se desconoce.',
      sexo: '"hombre" | "mujer" | "desconocido".',
      ids: 'Cadenas internas de la aplicación; sirven para enlazar las relaciones entre sí.',
      personas: 'Solo las personas ACTIVAS (las de la papelera no se exportan).',
      fotos: 'No se incluyen las imágenes. "tiene_foto" indica si esa persona tiene una foto guardada.',
      relaciones: 'Cada persona lleva sus relaciones anidadas; además, "relaciones" trae las listas '
        + 'globales de filiaciones (progenitor→hijo) y parejas (arista única, sin duplicar).',
    },
    arbol: {
      titulo: opcional(typeof arbolMeta !== 'undefined' ? arbolMeta.titulo : ''),
      subtitulo: opcional(typeof arbolMeta !== 'undefined' ? arbolMeta.subtitulo : ''),
      total_personas: listaPersonas.length,
      total_filiaciones: filiaciones.length,
      total_parejas: parejas.length,
      personas_con_foto: listaPersonas.filter(p => p.tiene_foto).length,
    },
    personas: listaPersonas,
    relaciones: { filiaciones, parejas },
  };
}
window.construirExportacion = construirExportacion;

// Nombre de archivo legible a partir del título del árbol + fecha.
// slugify() vive en util.js (JS-6).
function nombreArchivoExport(titulo) {
  const base = slugify(titulo, 'arbol-genealogico');
  const hoy = new Date().toISOString().slice(0, 10);
  return `${base}-${hoy}.json`;
}

function exportarDatos() {
  const datos = construirExportacion();
  const blob = new Blob([JSON.stringify(datos, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  descargarArchivo(url, nombreArchivoExport(datos.arbol.titulo));   // util.js (JS-7)
  URL.revokeObjectURL(url);
}
window.exportarDatos = exportarDatos;
