// ─── Panel de administración (PASO 13) ─────────────────────────────────────
// Centro de mando del admin (solo rol edición): agrupa piezas ya construidas
// modularmente (papelera, copias, import/export, claves vía Acceso, interruptor,
// ajustes) y cierra el hueco de cambiar claves/interruptor desde la app.
//
// Es un modal con pestañas a la izquierda (Ajustes · Apariencia · Seguridad ·
// Datos · Sistema). Reutiliza el mismo lenguaje visual y las convenciones de los
// demás modales (overlay, cierre por fondo/Escape, anti-doble-disparo). Su
// z-index queda POR DEBAJO de papelera/copias (2500) para que esos modales, que
// se abren desde el bloque «Datos», aparezcan por encima del panel; y por debajo
// del diálogo de confirmación (3500), que siempre manda.
(function () {
  const btnAdmin = document.getElementById('btnAdmin');
  if (!btnAdmin) return;

  // esc() y nombreDe() viven en util.js (únicos: CAL-06/07).

  // ── Estructura del modal ──────────────────────────────────────────────────
  const overlay = document.createElement('div');
  overlay.className = 'admin-overlay';
  overlay.hidden = true;
  overlay.innerHTML =
    `<div class="admin-caja" role="dialog" aria-modal="true" aria-label="Administración">
       <div class="admin-cab">
         <h2>Administración</h2>
         <button type="button" class="admin-cerrar" aria-label="Cerrar">&times;</button>
       </div>
       <div class="admin-cuerpo">
         <nav class="admin-nav">
           <button type="button" data-tab="ajustes" class="activo">Ajustes del árbol</button>
           <button type="button" data-tab="apariencia">Apariencia</button>
           <button type="button" data-tab="seguridad">Seguridad</button>
           <button type="button" data-tab="datos">Datos</button>
           <button type="button" data-tab="sistema">Sistema</button>
         </nav>
         <div class="admin-cont">
           <section class="admin-pane activo" data-pane="ajustes"></section>
           <section class="admin-pane" data-pane="apariencia"></section>
           <section class="admin-pane" data-pane="seguridad"></section>
           <section class="admin-pane" data-pane="datos"></section>
           <section class="admin-pane" data-pane="sistema"></section>
         </div>
       </div>
     </div>`;
  document.body.appendChild(overlay);

  const paneDe = t => overlay.querySelector(`.admin-pane[data-pane="${t}"]`);
  const cont = overlay.querySelector('.admin-cont');

  // ── Abrir / cerrar ────────────────────────────────────────────────────────
  function abrir() {
    overlay.hidden = false;
    activarTab('ajustes');
    document.addEventListener('keydown', onKey);
  }
  function cerrar() {
    overlay.hidden = true;
    document.removeEventListener('keydown', onKey);
  }
  const onKey = e => { if (e.key === 'Escape') cerrar(); };
  overlay.addEventListener('click', e => { if (e.target === overlay) cerrar(); });
  overlay.querySelector('.admin-cerrar').addEventListener('click', cerrar);
  btnAdmin.addEventListener('click', abrir);

  // ── Navegación por pestañas ───────────────────────────────────────────────
  const onShow = {};   // callback por pestaña (se llama al mostrarla)
  overlay.querySelectorAll('.admin-nav button').forEach(b =>
    b.addEventListener('click', () => activarTab(b.dataset.tab)));
  function activarTab(t) {
    overlay.querySelectorAll('.admin-nav button').forEach(b => b.classList.toggle('activo', b.dataset.tab === t));
    overlay.querySelectorAll('.admin-pane').forEach(p => p.classList.toggle('activo', p.dataset.pane === t));
    cont.scrollTop = 0;
    if (typeof onShow[t] === 'function') onShow[t]();
  }

  // Ayudante: línea de estado (verde ok / rojo error / neutro) por sección.
  function estado(el, msg, tipo) {
    if (!el) return;
    el.textContent = msg || '';
    el.className = 'admin-estado' + (tipo ? ' ' + tipo : '');
  }

  // ══════════════════════════════════════════════════════════════════════════
  // BLOQUE 1 — AJUSTES DEL ÁRBOL
  // ══════════════════════════════════════════════════════════════════════════
  paneDe('ajustes').innerHTML =
    `<h3 class="admin-h3">Identidad</h3>
     <div class="admin-campo"><label>Título</label><input type="text" id="adTitulo" maxlength="200"></div>
     <div class="admin-campo"><label>Subtítulo</label><input type="text" id="adSubtitulo" maxlength="200"></div>
     <h3 class="admin-h3">Persona central por defecto</h3>
     <p class="admin-desc">Desde quién se centra el árbol al mostrarlo. Es un valor <strong>estable</strong>:
        moverte por el mini-árbol o el buscador ya no lo cambia; solo se cambia aquí.</p>
     <div class="admin-campo"><label>Persona</label><select id="adMain"></select></div>
     <h3 class="admin-h3">Visualización</h3>
     <div class="admin-campo"><label>Orientación</label>
       <select id="adOrient"><option value="vertical">Vertical (de arriba a abajo)</option><option value="horizontal">Horizontal (de izquierda a derecha)</option></select>
     </div>
     <div class="admin-doscol">
       <div class="admin-campo"><label>Generaciones de antepasados</label><select id="adProfArriba"></select></div>
       <div class="admin-campo"><label>Generaciones de descendientes</label><select id="adProfAbajo"></select></div>
     </div>
     <div class="admin-acciones">
       <button type="button" class="admin-btn-primario" id="adGuardarAjustes">Guardar ajustes</button>
       <span class="admin-estado" id="adEstadoAjustes"></span>
     </div>`;

  const adTitulo = paneDe('ajustes').querySelector('#adTitulo');
  const adSubtitulo = paneDe('ajustes').querySelector('#adSubtitulo');
  const adMain = paneDe('ajustes').querySelector('#adMain');
  const adOrient = paneDe('ajustes').querySelector('#adOrient');
  const adProfArriba = paneDe('ajustes').querySelector('#adProfArriba');
  const adProfAbajo = paneDe('ajustes').querySelector('#adProfAbajo');
  const adEstadoAjustes = paneDe('ajustes').querySelector('#adEstadoAjustes');

  // Opciones de profundidad (helper único en util.js: CAL-08).
  [adProfArriba, adProfAbajo].forEach(sel => { sel.innerHTML = opcionesProfundidadHTML(); });

  onShow.ajustes = function () {
    const a = window.ajustesArbol || {};
    adTitulo.value = (typeof arbolMeta !== 'undefined' ? arbolMeta.titulo : a.titulo) || '';
    adSubtitulo.value = (typeof arbolMeta !== 'undefined' ? arbolMeta.subtitulo : a.subtitulo) || '';
    // Persona central: rellenar el desplegable con las personas activas (por nombre).
    const gente = f3Chart.store.getData().filter(d => !d.unknown)
      .map(d => ({ id: String(d.id), nombre: nombreDe(d.data) || 'Sin nombre' }))
      .sort((x, y) => x.nombre.localeCompare(y.nombre, 'es'));
    const actual = String(a.main_id || f3Chart.store.getMainId() || '');
    adMain.innerHTML = gente.map(g => `<option value="${esc(g.id)}"${g.id === actual ? ' selected' : ''}>${esc(g.nombre)}</option>`).join('');
    // Visualización: leer el estado VIVO del store (refleja lo aplicado al cargar).
    const st = f3Chart.store.state || {};
    adOrient.value = st.is_horizontal ? 'horizontal' : 'vertical';
    adProfArriba.value = String(st.ancestry_depth != null ? st.ancestry_depth : 100);
    adProfAbajo.value = String(st.progeny_depth != null ? st.progeny_depth : 100);
    estado(adEstadoAjustes, '', '');
  };

  paneDe('ajustes').querySelector('#adGuardarAjustes').addEventListener('click', async function () {
    const btn = this;
    const cambios = {
      titulo: adTitulo.value.trim(),
      subtitulo: adSubtitulo.value.trim(),
      main_id: parseInt(adMain.value, 10),
      orientacion: adOrient.value,
      prof_arriba: parseInt(adProfArriba.value, 10),
      prof_abajo: parseInt(adProfAbajo.value, 10),
    };
    btn.disabled = true;
    estado(adEstadoAjustes, 'Guardando…', '');
    try {
      await apiGuardarAjustes(cambios);
      // Aplicar en caliente: título, centro por defecto y visualización.
      if (typeof arbolMeta !== 'undefined') {
        arbolMeta.titulo = cambios.titulo || 'Nuestro árbol';
        arbolMeta.subtitulo = cambios.subtitulo;
        if (typeof pintarTitulo === 'function') pintarTitulo();
      }
      window.ajustesArbol = Object.assign({}, window.ajustesArbol, {
        main_id: String(cambios.main_id), titulo: cambios.titulo, subtitulo: cambios.subtitulo,
        orientacion: cambios.orientacion, prof_arriba: String(cambios.prof_arriba), prof_abajo: String(cambios.prof_abajo),
      });
      if (typeof aplicarVisualizacion === 'function') {
        aplicarVisualizacion({ orientacion: cambios.orientacion, prof_arriba: cambios.prof_arriba, prof_abajo: cambios.prof_abajo });
        f3Chart.updateTree({ tree_position: 'fit' });
      }
      estado(adEstadoAjustes, 'Ajustes guardados.', 'ok');
    } catch (e) {
      estado(adEstadoAjustes, e.message || 'No se pudo guardar.', 'err');
    } finally {
      btn.disabled = false;
    }
  });

  // ══════════════════════════════════════════════════════════════════════════
  // BLOQUE 2 — APARIENCIA
  // ══════════════════════════════════════════════════════════════════════════
  paneDe('apariencia').innerHTML =
    `<h3 class="admin-h3">Tema por defecto</h3>
     <p class="admin-desc">Con qué tema se muestra el árbol al entrar. Cada persona puede cambiarlo
        durante su sesión con el botón «Tema»; esto fija el punto de partida.</p>
     <div class="admin-radios">
       <label><input type="radio" name="adTema" value="claro"> Claro</label>
       <label><input type="radio" name="adTema" value="oscuro"> Oscuro</label>
     </div>
     <div class="admin-acciones"><span class="admin-estado" id="adEstadoTema"></span></div>`;
  const adEstadoTema = paneDe('apariencia').querySelector('#adEstadoTema');

  onShow.apariencia = function () {
    const tema = (window.ajustesArbol && window.ajustesArbol.tema_defecto) === 'oscuro' ? 'oscuro' : 'claro';
    const r = paneDe('apariencia').querySelector(`input[name="adTema"][value="${tema}"]`);
    if (r) r.checked = true;
    estado(adEstadoTema, '', '');
  };

  paneDe('apariencia').querySelectorAll('input[name="adTema"]').forEach(radio =>
    radio.addEventListener('change', async function () {
      const tema = this.value;
      estado(adEstadoTema, 'Guardando…', '');
      try {
        await apiGuardarAjustes({ tema_defecto: tema });
        window.ajustesArbol = Object.assign({}, window.ajustesArbol, { tema_defecto: tema });
        // Vista previa inmediata: aplicar el tema al momento.
        document.body.classList.toggle('dark', tema === 'oscuro');
        if (typeof pintarBotonTema === 'function') pintarBotonTema();
        estado(adEstadoTema, 'Tema por defecto guardado.', 'ok');
      } catch (e) {
        estado(adEstadoTema, e.message || 'No se pudo guardar.', 'err');
      }
    }));

  // ══════════════════════════════════════════════════════════════════════════
  // BLOQUE 3 — SEGURIDAD
  // ══════════════════════════════════════════════════════════════════════════
  paneDe('seguridad').innerHTML =
    `<h3 class="admin-h3">Control de acceso</h3>
     <p class="admin-desc">Si está activo, hay que identificarse con clave para entrar. Si se desactiva,
        el árbol queda <strong>abierto</strong> (cualquiera con el enlace puede verlo).</p>
     <div class="admin-acceso" id="adAcceso"><p class="admin-desc">Cargando…</p></div>

     <h3 class="admin-h3">Cambiar clave de edición (administrador)</h3>
     <div class="admin-campo"><label>Clave de edición actual</label><input type="password" id="adEdActual" autocomplete="off"></div>
     <div class="admin-doscol">
       <div class="admin-campo"><label>Clave nueva</label><input type="password" id="adEdNueva" autocomplete="new-password"></div>
       <div class="admin-campo"><label>Repetir clave nueva</label><input type="password" id="adEdRepetir" autocomplete="new-password"></div>
     </div>
     <div class="admin-acciones">
       <button type="button" class="admin-btn-primario" id="adBtnClaveEd">Cambiar clave de edición</button>
       <span class="admin-estado" id="adEstadoEd"></span>
     </div>

     <h3 class="admin-h3">Cambiar clave de lectura</h3>
     <div class="admin-campo"><label>Clave de edición actual</label><input type="password" id="adLeActual" autocomplete="off"></div>
     <div class="admin-doscol">
       <div class="admin-campo"><label>Clave de lectura nueva</label><input type="password" id="adLeNueva" autocomplete="new-password"></div>
       <div class="admin-campo"><label>Repetir clave nueva</label><input type="password" id="adLeRepetir" autocomplete="new-password"></div>
     </div>
     <div class="admin-acciones">
       <button type="button" class="admin-btn-primario" id="adBtnClaveLe">Cambiar clave de lectura</button>
       <span class="admin-estado" id="adEstadoLe"></span>
     </div>`;

  const adAcceso = paneDe('seguridad').querySelector('#adAcceso');

  onShow.seguridad = function () { refrescarAcceso(); limpiarSeguridad(); };

  function limpiarSeguridad() {
    ['adEdActual', 'adEdNueva', 'adEdRepetir', 'adLeActual', 'adLeNueva', 'adLeRepetir']
      .forEach(id => { const el = paneDe('seguridad').querySelector('#' + id); if (el) el.value = ''; });
    estado(paneDe('seguridad').querySelector('#adEstadoEd'), '', '');
    estado(paneDe('seguridad').querySelector('#adEstadoLe'), '', '');
  }

  async function refrescarAcceso() {
    adAcceso.innerHTML = '<p class="admin-desc">Cargando…</p>';
    let s;
    try { s = await apiSeguridadEstado(); }
    catch (e) { adAcceso.innerHTML = `<p class="admin-estado err">No se pudo leer el estado: ${esc(e.message)}</p>`; return; }
    if (s.control_activo) {
      adAcceso.innerHTML =
        `<div class="admin-acceso-estado"><span class="admin-pill on">Con control de acceso</span>
           <span>El árbol pide clave para entrar.</span></div>
         <div class="admin-campo"><label>Clave de edición actual (para confirmar)</label><input type="password" id="adCtrlClave" autocomplete="off"></div>
         <div class="admin-acciones">
           <button type="button" class="admin-btn-peligro" id="adBtnAbrir">Abrir árbol (quitar el control)</button>
           <span class="admin-estado" id="adEstadoCtrl"></span>
         </div>`;
      adAcceso.querySelector('#adBtnAbrir').addEventListener('click', () => abrirArbol());
    } else {
      adAcceso.innerHTML =
        `<div class="admin-acceso-estado"><span class="admin-pill off">Árbol abierto</span>
           <span>Cualquiera con el enlace puede entrar sin clave.</span></div>
         ${s.hay_edicion && s.hay_lectura ? '' :
            `<div class="admin-doscol">
               <div class="admin-campo"><label>Clave de edición${s.hay_edicion ? ' (opcional, ya existe)' : ''}</label><input type="password" id="adNewEd" autocomplete="new-password"></div>
               <div class="admin-campo"><label>Clave de lectura${s.hay_lectura ? ' (opcional, ya existe)' : ''}</label><input type="password" id="adNewLe" autocomplete="new-password"></div>
             </div>`}
         <div class="admin-acciones">
           <button type="button" class="admin-btn-primario" id="adBtnActivar">Activar control de acceso</button>
           <span class="admin-estado" id="adEstadoCtrl"></span>
         </div>`;
      adAcceso.querySelector('#adBtnActivar').addEventListener('click', () => activarControl(s));
    }
  }

  // Desactivar el control (abrir el árbol): pide la clave de edición actual y avisa.
  function abrirArbol() {
    const clave = (adAcceso.querySelector('#adCtrlClave') || {}).value || '';
    const est = adAcceso.querySelector('#adEstadoCtrl');
    if (!clave) { estado(est, 'Escribe la clave de edición actual para confirmar.', 'err'); return; }
    confirmarDialogo({
      titulo: 'Abrir el árbol',
      textoHTML: 'Vas a <strong>quitar el control de acceso</strong>: cualquiera con el enlace podrá ver el árbol sin clave. ¿Continuar?',
      textoBoton: 'Abrir árbol',
      onConfirmar: async () => {
        try {
          await apiEstablecerControl(false, clave);
          await refrescarAcceso();
        } catch (e) {
          estado(adAcceso.querySelector('#adEstadoCtrl'), e.message || 'No se pudo cambiar.', 'err');
        }
      }
    });
  }

  // Activar el control. Si ya existen ambas claves, basta activar; si no, se piden.
  async function activarControl(s) {
    const est = adAcceso.querySelector('#adEstadoCtrl');
    const extras = {};
    if (!(s.hay_edicion && s.hay_lectura)) {
      const ed = (adAcceso.querySelector('#adNewEd') || {}).value || '';
      const le = (adAcceso.querySelector('#adNewLe') || {}).value || '';
      if (!s.hay_edicion) { if (ed.length < 8) { estado(est, 'La clave de edición debe tener al menos 8 caracteres.', 'err'); return; } extras.clave_edicion = ed; }
      else if (ed) extras.clave_edicion = ed;
      if (!s.hay_lectura) { if (le.length < 8) { estado(est, 'La clave de lectura debe tener al menos 8 caracteres.', 'err'); return; } extras.clave_lectura = le; }
      else if (le) extras.clave_lectura = le;
    }
    estado(est, 'Guardando…', '');
    try {
      await apiEstablecerControl(true, '', extras);   // venía de "abierto": no hay clave actual que verificar
      await refrescarAcceso();
    } catch (e) {
      estado(adAcceso.querySelector('#adEstadoCtrl'), e.message || 'No se pudo activar.', 'err');
    }
  }

  // Cambio de clave (edición o lectura). Para 'lectura', la clave de confirmación
  // es igualmente la de EDICIÓN actual (así lo exige la reautenticación).
  function conectarCambioClave(rol, ids) {
    const btn = paneDe('seguridad').querySelector('#' + ids.btn);
    const est = paneDe('seguridad').querySelector('#' + ids.est);
    btn.addEventListener('click', async () => {
      const actual = paneDe('seguridad').querySelector('#' + ids.actual).value;
      const nueva = paneDe('seguridad').querySelector('#' + ids.nueva).value;
      const rep = paneDe('seguridad').querySelector('#' + ids.rep).value;
      if (nueva.length < 8) { estado(est, 'La clave nueva debe tener al menos 8 caracteres.', 'err'); return; }
      if (nueva !== rep) { estado(est, 'La clave nueva y su repetición no coinciden.', 'err'); return; }
      btn.disabled = true;
      estado(est, 'Guardando…', '');
      try {
        await apiCambiarClave(rol, actual, nueva);
        estado(est, 'Clave cambiada. Los próximos inicios de sesión usarán la nueva.', 'ok');
        [ids.actual, ids.nueva, ids.rep].forEach(id => { paneDe('seguridad').querySelector('#' + id).value = ''; });
      } catch (e) {
        estado(est, e.message || 'No se pudo cambiar la clave.', 'err');
      } finally {
        btn.disabled = false;
      }
    });
  }
  conectarCambioClave('edicion', { btn: 'adBtnClaveEd', est: 'adEstadoEd', actual: 'adEdActual', nueva: 'adEdNueva', rep: 'adEdRepetir' });
  conectarCambioClave('lectura', { btn: 'adBtnClaveLe', est: 'adEstadoLe', actual: 'adLeActual', nueva: 'adLeNueva', rep: 'adLeRepetir' });

  // ══════════════════════════════════════════════════════════════════════════
  // BLOQUE 4 — DATOS
  // ══════════════════════════════════════════════════════════════════════════
  paneDe('datos').innerHTML =
    `<h3 class="admin-h3">Copias y portabilidad</h3>
     <div class="admin-grid-btns">
       <button type="button" class="admin-btn" id="adCopias">Copias de seguridad…</button>
       <button type="button" class="admin-btn" id="adExportar">Exportar árbol a JSON (otras apps o IA)</button>
     </div>
     <p class="admin-desc">«<strong>Exportar árbol a JSON</strong>» descarga tu árbol en un formato
        JSON estándar y legible, pensado para <strong>usarlo fuera de esta aplicación</strong>: abrirlo
        con otra herramienta, convertirlo a otro formato o dárselo a una IA para que lo procese. Incluye
        todas las personas activas con sus datos y sus relaciones (quién es progenitor, hijo o pareja de
        quién). <strong>No incluye las fotos</strong> (solo indica quién tiene foto), y <strong>no sirve
        para restaurar</strong>.<br>
        Para <strong>respaldar y recuperar</strong> tu árbol completo —con la papelera y las
        <strong>fotos</strong>— usa <strong>Copias de seguridad</strong>: desde ahí, con
        «<strong>Restaurar desde archivo…</strong>», puedes recuperarlo a partir de una copia de tu
        equipo (con doble confirmación y una copia previa automática).</p>
     <h3 class="admin-h3">Papelera</h3>
     <div class="admin-grid-btns">
       <button type="button" class="admin-btn" id="adPapelera">Abrir papelera…</button>
     </div>
     <h3 class="admin-h3">Personas sin nombre</h3>
     <p class="admin-desc">Personas activas que se quedaron sin nombre. Puedes ponerles nombre o
        mandarlas a la papelera.</p>
     <div class="admin-acciones">
       <button type="button" class="admin-btn" id="adSinNombre">Revisar personas sin nombre</button>
       <span class="admin-estado" id="adEstadoSinNombre"></span>
     </div>
     <div class="admin-sinnombre" id="adSinNombreLista"></div>`;

  paneDe('datos').querySelector('#adCopias').addEventListener('click', () => { if (window.abrirCopias) window.abrirCopias(); });
  paneDe('datos').querySelector('#adExportar').addEventListener('click', () => { if (window.exportarDatos) window.exportarDatos(); });
  paneDe('datos').querySelector('#adPapelera').addEventListener('click', () => { if (window.abrirPapelera) window.abrirPapelera(); });

  const adSinNombreLista = paneDe('datos').querySelector('#adSinNombreLista');
  const adEstadoSinNombre = paneDe('datos').querySelector('#adEstadoSinNombre');
  paneDe('datos').querySelector('#adSinNombre').addEventListener('click', () => cargarSinNombre());

  async function cargarSinNombre() {
    estado(adEstadoSinNombre, 'Buscando…', '');
    adSinNombreLista.innerHTML = '';
    let gente;
    try { gente = await apiSinNombreListar(); }
    catch (e) { estado(adEstadoSinNombre, e.message || 'No se pudo consultar.', 'err'); return; }
    if (!gente.length) { estado(adEstadoSinNombre, 'No hay personas sin nombre.', 'ok'); return; }
    estado(adEstadoSinNombre, gente.length + (gente.length === 1 ? ' persona sin nombre.' : ' personas sin nombre.'), '');
    gente.forEach(p => {
      const item = document.createElement('div');
      item.className = 'admin-sn-item ' + (p.sexo === 'F' ? 'female' : 'male');
      const foto = (typeof urlFoto === 'function') ? urlFoto(p.avatar, p.id) : '';
      const aa = rangoAnios(p.nacimiento, p.fallecimiento);   // util.js (JS-4)
      item.innerHTML =
        `<div class="admin-sn-circ">${foto ? `<img src="${esc(foto)}" alt="">` : (typeof ICONO_PERSONA !== 'undefined' ? ICONO_PERSONA : '')}</div>
         <div class="admin-sn-campos">
           <div class="admin-sn-meta">Id ${esc(String(p.id))}${aa ? ' · ' + esc(aa) : ''}</div>
           <div class="admin-sn-inputs">
             <input type="text" class="sn-nombre" placeholder="Nombre" maxlength="100">
             <input type="text" class="sn-ape1" placeholder="Apellido 1" maxlength="100">
             <input type="text" class="sn-ape2" placeholder="Apellido 2" maxlength="100">
           </div>
         </div>
         <div class="admin-sn-acciones">
           <button type="button" class="admin-btn-primario sn-guardar">Guardar</button>
           <button type="button" class="admin-btn-peligro sn-papelera">A la papelera</button>
         </div>`;
      const bg = item.querySelector('.sn-guardar');
      const bp = item.querySelector('.sn-papelera');
      bg.addEventListener('click', async () => {
        const nombre = item.querySelector('.sn-nombre').value.trim();
        if (!nombre) { item.querySelector('.sn-nombre').focus(); return; }
        bg.disabled = true; bp.disabled = true;
        try {
          await apiRenombrar(p.id, nombre, item.querySelector('.sn-ape1').value.trim(), item.querySelector('.sn-ape2').value.trim());
          if (typeof recargarDesdeBD === 'function') await recargarDesdeBD();
          item.remove();
          if (!adSinNombreLista.children.length) estado(adEstadoSinNombre, 'Listo: ya no quedan personas sin nombre.', 'ok');
        } catch (e) {
          bg.disabled = false; bp.disabled = false;
          estado(adEstadoSinNombre, e.message || 'No se pudo guardar.', 'err');
        }
      });
      bp.addEventListener('click', () => {
        confirmarDialogo({
          titulo: 'Mandar a la papelera',
          textoHTML: 'Esta persona (sin nombre) irá a la <strong>papelera</strong>. Podrás recuperarla desde allí.',
          textoBoton: 'A la papelera',
          onConfirmar: async () => {
            try {
              await apiSinNombrePapelera(p.id);
              if (typeof recargarDesdeBD === 'function') await recargarDesdeBD();
              item.remove();
              if (!adSinNombreLista.children.length) estado(adEstadoSinNombre, 'Listo: ya no quedan personas sin nombre.', 'ok');
            } catch (e) {
              estado(adEstadoSinNombre, e.message || 'No se pudo mandar a la papelera.', 'err');
            }
          }
        });
      });
      adSinNombreLista.appendChild(item);
    });
  }

  // ══════════════════════════════════════════════════════════════════════════
  // BLOQUE 5 — SISTEMA
  // ══════════════════════════════════════════════════════════════════════════
  onShow.sistema = async function () {
    const pane = paneDe('sistema');
    pane.innerHTML = '<p class="admin-desc">Cargando…</p>';
    let s;
    try { s = await apiSistema(); }
    catch (e) { pane.innerHTML = `<p class="admin-estado err">No se pudo leer el sistema: ${esc(e.message)}</p>`; return; }
    const inst = s.instalacion || {};
    const si = b => b ? '<span class="admin-check ok">✔</span>' : '<span class="admin-check mal">✕</span>';
    const reqs = (s.requisitos || []).map(r =>
      `<div class="admin-req">${si(r.ok)}<div><strong>${esc(r.titulo)}</strong><span>${esc(r.detalle)}</span></div></div>`).join('');
    pane.innerHTML =
      `<h3 class="admin-h3">Versión</h3>
       <div class="admin-req">${si(true)}<div><strong>Versión del esquema</strong>
         <span>Instalada: ${esc(s.version_esquema || '—')} · esta app instala: ${esc(s.version_app || '—')} · PHP ${esc(s.php || '')}</span></div></div>
       <h3 class="admin-h3">Instalación</h3>
       <div class="admin-req">${si(!!inst.config)}<div><strong>config.php</strong><span>${inst.config ? 'Presente' : 'No encontrado'}</span></div></div>
       <div class="admin-req">${si(!!inst.conecta)}<div><strong>Conexión a la base de datos</strong><span>${esc((s.bd && s.bd.detalle) || '')}</span></div></div>
       <div class="admin-req">${si(!!inst.tablas)}<div><strong>Estructura de tablas</strong><span>${inst.tablas ? 'Creada' : 'Incompleta'}</span></div></div>
       <div class="admin-req">${si(!!inst.instalado)}<div><strong>Instalación finalizada</strong><span>${inst.instalado ? 'Sí (marca en la BD)' : 'No'}</span></div></div>
       <h3 class="admin-h3">Entorno</h3>
       ${reqs}`;
  };
})();
