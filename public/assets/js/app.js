
// Expuesto en consola (F12) para inspeccionar la fuente real de datos.
window.f3Chart = f3Chart;
window.f3Edit = f3Edit;

// ─── Acceso / sesión (PASO 8) ─────────────────────────────────────────────
const loginPantalla = document.getElementById('loginPantalla');
const loginForm = document.getElementById('loginForm');
const loginError = document.getElementById('loginError');

function mostrarLogin(cancelable) {
  document.body.classList.remove('cargando-datos', 'sesion-pendiente');
  // El botón "Seguir viendo sin acceder" solo aparece cuando se abre el login de
  // forma OPCIONAL (árbol abierto): en control de acceso el login es obligatorio.
  const cancelar = document.getElementById('loginCancelar');
  if (cancelar) cancelar.hidden = !cancelable;
  loginPantalla.hidden = false;
}
// Árbol aún no instalado: invita al asistente en vez de mostrar un error.
function mostrarNoInstalado() {
  document.body.classList.remove('cargando-datos', 'sesion-pendiente');
  const el = document.getElementById('noInstalado');
  if (el) el.hidden = false;
}
// CAL-01: la carga inicial del árbol falló (red / 500 / datos). En vez de dejar la
// pantalla en blanco, se avisa con claridad y se ofrece reintentar (recargar).
function mostrarErrorCarga(detalle) {
  document.body.classList.remove('cargando-datos', 'sesion-pendiente');
  const el = document.getElementById('errorCarga');
  if (!el) return;
  const d = document.getElementById('errorCargaDetalle');
  if (d && detalle) d.textContent = detalle;
  el.hidden = false;
}
const btnReintentarCarga = document.getElementById('errorCargaReintentar');
if (btnReintentarCarga) btnReintentarCarga.addEventListener('click', () => location.reload());
function ocultarLogin() {
  document.body.classList.remove('sesion-pendiente');
  loginPantalla.hidden = true;
}

// Si una petición pierde la sesión (401), se vuelve al login. Lo llama api.js.
window.alPerderSesion = function () { window.csrfToken = null; mostrarLogin(); };

// Aplica el rol: lectura → modo solo-lectura (reutiliza el de móvil/tablet).
function aplicarRol(rol) {
  window.rolLectura = (rol !== 'edicion');
  if (typeof evaluarDispositivo === 'function') evaluarDispositivo();
}

// En árbol ABIERTO sin sesión se entra en solo-lectura pública, pero el dueño debe
// poder autenticarse para administrar (SEC-02: administrar exige sesión también en
// modo abierto). Para eso se muestra un botón DEDICADO y visible "Acceder para
// administrar" (#btnAccederAdmin), y se OCULTA el botón "Salir" (no hay sesión que
// cerrar). Se controla con la clase body.modo-abierto-anon (el CSS hace el resto).
function actualizarBotonAcceso(autenticado, abierto) {
  const modoAcceder = !autenticado && abierto;
  document.body.classList.toggle('modo-abierto-anon', modoAcceder);
}

// Halo DORADO sobre la persona que ENTRA (login), con el mismo dorado del buscador
// pero PERSISTENTE: se mantiene hasta la primera interacción (toque, rueda, tecla o
// mover el árbol) y entonces desaparece. Marca la tarjeta cuando exista tras el
// render (reintenta un poco) y registra la retirada al primer gesto.
function resaltarEntrada(id) {
  const sid = String(id);
  const quitar = () => {
    document.querySelectorAll('#FamilyChart .card.localizada-entrada')
      .forEach(c => c.classList.remove('localizada-entrada'));
    document.removeEventListener('pointerdown', quitar, true);
    document.removeEventListener('wheel', quitar, true);
    document.removeEventListener('keydown', quitar, true);
    document.removeEventListener('touchstart', quitar, true);
  };
  let intentos = 0;
  const t = setInterval(() => {
    const cards = document.querySelectorAll(`#FamilyChart .card[data-person-id="${CSS.escape(sid)}"]`);
    if (cards.length) {
      cards.forEach(c => c.classList.add('localizada-entrada'));
      clearInterval(t);
      // Margen para no capturar el propio clic de "Entrar" como interacción.
      setTimeout(() => {
        document.addEventListener('pointerdown', quitar, true);
        document.addEventListener('wheel', quitar, true);
        document.addEventListener('keydown', quitar, true);
        document.addEventListener('touchstart', quitar, true);
      }, 250);
    } else if (++intentos > 30) {
      clearInterval(t);
    }
  }, 80);
}

// Carga el árbol desde la BD y lo centra en `centrarEn` (la persona que entra).
// SOLO lectura de datos; editar (si el rol lo permite) persiste vía persistir.js.
async function iniciarDesdeBD(centrarEn) {
  try {
    const { ajustes, personas } = await cargarArbolDesdeBD();
    if (!personas.length) { console.warn('El árbol está vacío.'); return; }
    f3Chart.store.updateData(personas);
    // Ajustes de PANTALLA cargados (los usa el panel de administración como
    // valores actuales de «Ajustes del árbol» y «Apariencia»): persona central
    // por defecto, orientación, profundidad y tema por defecto.
    window.ajustesArbol = ajustes;
    // Visualización (orientación/profundidad) ANTES del primer render.
    if (typeof aplicarVisualizacion === 'function') aplicarVisualizacion(ajustes);
    // Tema por defecto: solo en la carga inicial (no pisa el interruptor de sesión
    // en recargas posteriores, que no pasan por aquí).
    if (ajustes.tema_defecto === 'oscuro') document.body.classList.add('dark');
    else if (ajustes.tema_defecto === 'claro') document.body.classList.remove('dark');
    if (typeof pintarBotonTema === 'function') pintarBotonTema();
    const existe = id => personas.some(p => String(p.id) === String(id));
    const mainId = (centrarEn && existe(centrarEn)) ? centrarEn : (ajustes.main_id || personas[0].id);
    // CENTRAR en la persona (como CADENA): family-chart busca el main con === y los
    // ids son cadenas; con cadena, el árbol queda enraizado/centrado EN ella (con
    // número caería en data[0]). Así, al entrar, cada uno ve el árbol desde sí mismo.
    f3Chart.store.updateMainId(String(mainId));
    // Persona de INICIO para el botón "Volver al inicio": la persona en la que se
    // centra el árbol al entrar. Se guarda una sola vez y NO cambia al navegar (la
    // navegación por el mini-árbol va reescribiendo el main_id, pero el "inicio"
    // debe seguir siendo el de partida). Hoy = la persona que entra, que en el demo
    // coincide con main_id. FUTURO (login por usuario): seguirá siendo la persona de
    // la sesión, que es justo lo deseado.
    window.personaInicio = String(mainId);
    aplicarTituloDesdeAjustes(ajustes);
    f3Chart.updateTree({ initial: true });
    // RAÍZ del arreglo de deshacer/rehacer: fijar la base del historial con el
    // árbol COMPLETO recién cargado (si no, el primer "Deshacer" revertía al nodo
    // de arranque y vaciaba el árbol). Se hace tras el primer render.
    if (typeof reiniciarHistorial === 'function') reiniciarHistorial();
    fijarInstantanea();
    fijarMainGuardado(mainId);
    resaltarEntrada(mainId);   // halo dorado sobre la persona que entra (hasta interactuar)
  } catch (e) {
    console.error('No se pudo cargar el árbol desde la base de datos:', e);
    // CAL-01: si es un 401, cargarArbolDesdeBD ya ha mostrado el login (alPerderSesion);
    // no tapamos el login con el error de carga. Para cualquier otro fallo, avisamos.
    const login = document.getElementById('loginPantalla');
    const sesionCaducada = login && !login.hidden;
    if (!sesionCaducada) mostrarErrorCarga(e && e.message ? e.message : '');
  } finally {
    document.body.classList.remove('cargando-datos');
  }
}

// Tras entrar (login o sesión ya válida): guardar token, aplicar rol, cargar árbol.
async function entrar(sesion) {
  window.csrfToken = sesion.csrf;
  aplicarRol(sesion.rol);
  // Botón Acceder/Salir: "acceder" solo si es árbol abierto (control_activo===false)
  // y NO hay sesión (sin csrf). Tras un login normal, sesion.csrf existe → "Salir".
  actualizarBotonAcceso(!!sesion.csrf, sesion.control_activo === false);
  ocultarLogin();
  document.body.classList.add('cargando-datos');
  await iniciarDesdeBD(sesion.persona_id);   // centrado en la persona que entra
}

// Arranque: ¿hay sesión ya iniciada?
(async function arrancar() {
  let s = null;
  try { s = await apiSesion(); } catch (_) {}
  if (s && s.instalado === false) { mostrarNoInstalado(); return; }   // sin instalar → asistente
  if (!s || (s.control_activo && !s.autenticado)) { mostrarLogin(); return; }
  await entrar(s);
})();

// ─── Formulario de login ──────────────────────────────────────────────────
document.getElementById('loginSoloAnio').addEventListener('change', e => {
  const on = e.target.checked;
  document.getElementById('loginAnioCampo').style.display = on ? '' : 'none';
  document.getElementById('loginFechaCampo').style.display = on ? 'none' : '';
});

loginForm.addEventListener('submit', async e => {
  e.preventDefault();
  loginError.textContent = '';
  const btn = document.getElementById('loginEntrar');
  const soloAnio = document.getElementById('loginSoloAnio').checked;
  const nacimiento = soloAnio
    ? (document.getElementById('loginAnio').value || '').trim()
    : (document.getElementById('loginFecha').value || '').trim();
  const datos = {
    nombre:   document.getElementById('loginNombre').value,
    apellido: document.getElementById('loginApellido').value,
    nacimiento,
    clave:    document.getElementById('loginClave').value
  };
  if (!datos.nombre.trim() || !datos.apellido.trim() || !nacimiento || !datos.clave) {
    loginError.textContent = 'Rellena todos los campos.';
    return;
  }
  btn.disabled = true;
  try {
    const { json } = await apiLogin(datos);
    if (json && json.ok) {
      document.getElementById('loginClave').value = '';
      await entrar(json);
    } else {
      loginError.textContent = (json && json.error) ? json.error : 'No se pudo iniciar sesión.';
    }
  } catch (_) {
    loginError.textContent = 'Error de conexión. Inténtalo de nuevo.';
  } finally {
    btn.disabled = false;
  }
});

// ─── Cerrar sesión ────────────────────────────────────────────────────────
document.getElementById('btnSalir').addEventListener('click', async () => {
  try { await apiLogout(); } catch (_) {}
  window.csrfToken = null;
  location.reload();   // recarga → vuelve a la pantalla de login
});

// ─── Acceder para administrar (árbol abierto) ─────────────────────────────
// Botón DEDICADO visible en modo abierto anónimo: abre el login (cancelable) para
// que el dueño inicie sesión con su clave de edición y administre.
const btnAccederAdmin = document.getElementById('btnAccederAdmin');
if (btnAccederAdmin) btnAccederAdmin.addEventListener('click', () => {
  loginError.textContent = '';
  mostrarLogin(true);
});

// "Seguir viendo sin acceder": cierra el login opcional (árbol abierto) y sigue en
// solo-lectura pública.
const loginCancelarBtn = document.getElementById('loginCancelar');
if (loginCancelarBtn) loginCancelarBtn.addEventListener('click', () => {
  loginError.textContent = '';
  ocultarLogin();
});
