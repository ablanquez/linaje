// ─── Capa de datos: única puerta al backend ──────────────────────────────
// De momento (PASO 5) solo LECTURA: carga el árbol desde la base de datos.
// La escritura (guardar cambios) llegará en el PASO 6, aquí mismo.

// Pide el árbol al endpoint y devuelve { ajustes, personas }.
//   · ajustes  → objeto { main_id, titulo, subtitulo } (puede venir vacío)
//   · personas → lista de nodos family-chart { id, data, rels }
// Lanza Error si la respuesta no es correcta (para que quien llama lo trate).
async function cargarArbolDesdeBD() {
  const resp = await fetch('api/arbol.php', { headers: { 'Accept': 'application/json' } });
  if (resp.status === 401) {                       // sesión no iniciada / caducada
    if (typeof window.alPerderSesion === 'function') window.alPerderSesion();
    throw new Error('No has iniciado sesión.');
  }
  if (!resp.ok) {
    throw new Error('El servidor respondió ' + resp.status + ' ' + resp.statusText);
  }
  const json = await resp.json();
  // El endpoint devuelve {error, detalle} si algo falló en el servidor.
  if (json && json.error) {
    throw new Error(json.error + (json.detalle ? ': ' + json.detalle : ''));
  }
  return {
    ajustes: (json && json.ajustes) || {},
    personas: (json && json.personas) || []
  };
}

// Si la sesión ha caducado (401), avisa a la app (una vez, si hay handler).
function chequear401(resp) {
  if (resp.status === 401 && typeof window.alPerderSesion === 'function') window.alPerderSesion();
}

// ─── Lectura genérica (JS-1) ──────────────────────────────────────────────
// GET JSON con validación { ok:true }. Lanza Error con el mensaje del servidor.
// Unifica el mismo bloque que se repetía en los "listar" de cada sección.
async function getJSON(endpoint) {
  const resp = await fetch(endpoint, { headers: { 'Accept': 'application/json' } });
  chequear401(resp);
  const json = await resp.json().catch(() => null);
  if (!resp.ok || !json || json.ok !== true) {
    throw new Error((json && json.error) ? json.error : ('El servidor respondió ' + resp.status));
  }
  return json;
}

// ─── Escritura (PASO 6) ───────────────────────────────────────────────────
// POST JSON genérico a un endpoint de escritura. Lanza Error con el mensaje del
// servidor si la operación no fue { ok:true }.
async function postJSON(endpoint, cuerpo) {
  const resp = await fetch(endpoint, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-Token': window.csrfToken || ''      // exigido por el servidor en escrituras
    },
    body: JSON.stringify(cuerpo)
  });
  chequear401(resp);
  let json = null;
  try { json = await resp.json(); } catch (_) { /* respuesta no-JSON */ }
  if (!resp.ok || !json || json.ok !== true) {
    const msg = (json && json.error) ? json.error : ('El servidor respondió ' + resp.status);
    throw new Error(msg + (json && json.detalle ? ' (' + json.detalle + ')' : ''));
  }
  return json;
}

// Operaciones concretas (una por endpoint/acción).
const apiCrearPersona  = (datos)     => postJSON('api/persona.php',  { accion: 'crear',  datos });        // → { id }
const apiEditarPersona = (id, datos) => postJSON('api/persona.php',  { accion: 'editar', id, datos });
const apiBorrarPersona = (id)        => postJSON('api/persona.php',  { accion: 'borrar', id });
const apiVincular      = (tipo, ids) => postJSON('api/relacion.php', Object.assign({ tipo, operacion: 'anadir' }, ids));
const apiDesvincular   = (tipo, ids) => postJSON('api/relacion.php', Object.assign({ tipo, operacion: 'quitar' }, ids));
const apiGuardarAjustes = (cambios)  => postJSON('api/ajustes.php',  cambios);   // { titulo?, subtitulo?, main_id? }
// LOTE atómico (INT-03): manda todo el diff en una sola transacción. → { ids:{temp:idReal} }
const apiGuardarLote   = (lote)      => postJSON('api/guardar.php', lote);

// Sube una imagen (multipart) por la ÚNICA puerta al backend (JS-2). Gemelo de
// apiBackupRestaurarArchivo. Devuelve el JSON del servidor → { avatar }.
async function apiSubirFoto(file) {
  const fd = new FormData();
  fd.append('imagen', file);
  const resp = await fetch('api/foto.php', {
    method: 'POST',
    headers: { 'X-CSRF-Token': window.csrfToken || '' },   // multipart: sin Content-Type manual
    body: fd
  });
  chequear401(resp);
  const json = await resp.json().catch(() => null);
  if (!resp.ok || !json || json.ok !== true) {
    throw new Error((json && json.error) ? json.error : ('El servidor respondió ' + resp.status));
  }
  return json;
}

// ─── Papelera (PASO 10) ───────────────────────────────────────────────────
const apiPapeleraListar = () => getJSON('api/papelera.php').then(j => j.personas || []);
const apiPapeleraRestaurar = (id) => postJSON('api/papelera.php', { accion: 'restaurar', id });
const apiPapeleraEliminar  = (id) => postJSON('api/papelera.php', { accion: 'eliminar',  id });
const apiPapeleraVaciar    = ()   => postJSON('api/papelera.php', { accion: 'vaciar' });

// ─── Copias de seguridad (PASO 11) ────────────────────────────────────────
const apiBackupListar = () => getJSON('api/backup.php').then(j => j.copias || []);
const apiBackupGenerar   = ()        => postJSON('api/backup.php', { accion: 'generar' });     // → { archivo, manifest }
const apiBackupEliminar  = (archivo) => postJSON('api/backup.php', { accion: 'eliminar', archivo });
const apiBackupRestaurar = (archivo) => postJSON('api/backup.php', { accion: 'restaurar', archivo });   // DESTRUCTIVO
const urlBackupDescargar = (archivo) => 'api/backup-descargar.php?archivo=' + encodeURIComponent(archivo);
// Restaurar desde un archivo SUBIDO (multipart). Devuelve el JSON del servidor.
async function apiBackupRestaurarArchivo(file) {
  const fd = new FormData();
  fd.append('copia', file, file.name || 'copia.json');
  const resp = await fetch('api/backup-restaurar.php', {
    method: 'POST',
    headers: { 'X-CSRF-Token': window.csrfToken || '' },   // multipart: sin Content-Type manual
    body: fd
  });
  chequear401(resp);
  const json = await resp.json().catch(() => null);
  if (!resp.ok || !json || json.ok !== true) {
    throw new Error((json && json.error) ? json.error : ('El servidor respondió ' + resp.status));
  }
  return json;
}

// ─── Panel de administración (PASO 13) ────────────────────────────────────
// Seguridad: estado del acceso (nunca devuelve hashes, solo si existen) y
// cambios (claves + interruptor) con reautenticación en el servidor.
// → { control_activo, hay_edicion, hay_lectura }
const apiSeguridadEstado = () => getJSON('api/seguridad.php');
const apiCambiarClave = (rol, claveActual, claveNueva) =>
  postJSON('api/seguridad.php', { accion: 'cambiar_clave', rol, clave_actual: claveActual, clave_nueva: claveNueva });
const apiEstablecerControl = (activo, claveActual, extras = {}) =>
  postJSON('api/seguridad.php', Object.assign({ accion: 'establecer_control', activo, clave_actual: claveActual }, extras));

// Mantenimiento: personas sin nombre (listar / renombrar / a la papelera).
const apiSinNombreListar = () => getJSON('api/mantenimiento.php').then(j => j.personas || []);
const apiRenombrar = (id, nombre, apellido1, apellido2) =>
  postJSON('api/mantenimiento.php', { accion: 'renombrar', id, nombre, apellido1, apellido2 });
const apiSinNombrePapelera = (id) => postJSON('api/mantenimiento.php', { accion: 'papelera', id });

// Sistema: versión, estado del entorno e info de instalación (solo lectura).
const apiSistema = () => getJSON('api/sistema.php');

// ─── Sesión (PASO 8) ──────────────────────────────────────────────────────
const apiSesion = () => fetch('api/sesion.php', { headers: { 'Accept': 'application/json' } }).then(r => r.json());
const apiLogout = () => postJSON('api/logout.php', {});
// Login: devuelve { status, json } sin lanzar, para que la pantalla muestre el error.
async function apiLogin(datos) {
  const resp = await fetch('api/login.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify(datos)
  });
  const json = await resp.json().catch(() => null);
  return { status: resp.status, json };
}
