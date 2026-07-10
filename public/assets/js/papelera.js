// ─── Papelera (PASO 10): ver y restaurar personas borradas ────────────────
// Modal propio abierto desde el botón "Papelera" (solo en modo edición). Diseñado
// como pieza independiente para integrarse luego en el PANEL DE ADMINISTRACIÓN.
// Las confirmaciones usan el diálogo compartido (dialogo.js): una sola instancia
// a la vez y con anti-doble-disparo. Las acciones directas (Restaurar) bloquean
// su botón mientras se procesan.
(function () {
  // Antes se abría desde un botón de la barra (btnPapelera). Desde el PASO 13 se
  // abre desde el bloque «Datos» del panel de administración; la pieza sigue
  // siendo la misma (modal independiente), solo cambia quién la dispara. Por eso
  // exponemos abrir() como window.abrirPapelera y el botón (si existiera) es opcional.
  const btn = document.getElementById('btnPapelera');

  // esc(), nombreDe(), rangoAnios() y formatearFecha() viven en util.js (Q1/Q2).
  const aniosDe = p => rangoAnios(p.nacimiento, p.fallecimiento, 'nacimiento');

  // Modal de la papelera (se crea una vez).
  const overlay = document.createElement('div');
  overlay.className = 'papelera-overlay';
  overlay.hidden = true;
  overlay.innerHTML =
    `<div class="papelera-caja" role="dialog" aria-modal="true">
       <div class="papelera-cab">
         <h2>Papelera</h2>
         <div class="papelera-cab-btns">
           <button type="button" class="papelera-vaciar">Vaciar papelera</button>
           <button type="button" class="papelera-cerrar" aria-label="Cerrar">&times;</button>
         </div>
       </div>
       <div class="papelera-lista"></div>
     </div>`;
  document.body.appendChild(overlay);
  const lista = overlay.querySelector('.papelera-lista');
  const btnVaciar = overlay.querySelector('.papelera-vaciar');

  const abrir = () => { overlay.hidden = false; refrescar(); };
  const cerrar = () => { overlay.hidden = true; };
  overlay.addEventListener('click', e => { if (e.target === overlay) cerrar(); });
  overlay.querySelector('.papelera-cerrar').addEventListener('click', cerrar);
  if (btn) btn.addEventListener('click', abrir);
  window.abrirPapelera = abrir;   // el panel de administración la abre por aquí

  let cargando = false;   // evita relistados solapados

  async function refrescar() {
    if (cargando) return;
    cargando = true;
    lista.innerHTML = '<p class="papelera-vacia">Cargando…</p>';
    let personas;
    try { personas = await apiPapeleraListar(); }
    catch (e) { lista.innerHTML = `<p class="papelera-vacia">No se pudo cargar la papelera.</p>`; cargando = false; return; }
    finally { cargando = false; }

    btnVaciar.style.display = personas.length ? '' : 'none';
    if (!personas.length) { lista.innerHTML = '<p class="papelera-vacia">La papelera está vacía.</p>'; return; }

    lista.innerHTML = '';
    personas.forEach(p => {
      // La papelera trae columnas de BD (nombre/apellido1/apellido2), no el formato
      // de family-chart, así que se adapta a las claves que espera nombreDe().
      const nombre = nombreDe({ 'first name': p.nombre, 'last name': p.apellido1, 'last name 2': p.apellido2 }, 'Sin nombre');
      const foto = urlFoto(p.avatar, p.id);
      const item = document.createElement('div');
      item.className = 'papelera-item ' + (p.sexo === 'F' ? 'female' : 'male');
      const aa = aniosDe(p);
      item.innerHTML =
        `<div class="papelera-circ">${foto ? `<img src="${esc(foto)}" alt="">` : ICONO_PERSONA}</div>
         <div class="papelera-info">
           <div class="papelera-nombre">${esc(nombre)}</div>
           <div class="papelera-datos">${esc(aa)}${aa ? ' · ' : ''}borrada el ${esc(formatearFecha(p.borrado_en))}</div>
         </div>
         <div class="papelera-acciones">
           <button type="button" class="papelera-restaurar">Restaurar</button>
           <button type="button" class="papelera-eliminar">Eliminar</button>
         </div>`;
      const btnR = item.querySelector('.papelera-restaurar');
      const btnE = item.querySelector('.papelera-eliminar');
      btnR.addEventListener('click', () => restaurar(p.id, btnR, btnE));
      btnE.addEventListener('click', () => eliminar(p.id, nombre));
      lista.appendChild(item);
    });
  }

  // Restaurar = acción DIRECTA (sin diálogo): bloqueamos los botones del elemento
  // en cuanto se pulsa, para que no se dispare dos veces por pulsación rápida.
  async function restaurar(id, btnR, btnE) {
    if (btnR.disabled) return;
    btnR.disabled = true; if (btnE) btnE.disabled = true;
    try { await apiPapeleraRestaurar(id); }
    catch (e) { btnR.disabled = false; if (btnE) btnE.disabled = false; alert('No se pudo restaurar:\n\n' + e.message); return; }
    await recargarDesdeBD();   // la persona vuelve al árbol, conectada y con su foto
    refrescar();               // y se actualiza la lista de la papelera
  }

  // Eliminar definitivo = confirmación (diálogo compartido, un solo diálogo y con
  // el botón bloqueado mientras se procesa). No hace falta bloquear el botón de la
  // lista: el propio diálogo impide abrir un segundo.
  function eliminar(id, nombre) {
    confirmarDialogo({
      titulo: 'Eliminar definitivamente',
      textoHTML: `¿Eliminar para siempre a <strong>${esc(nombre)}</strong>? Esta acción <strong>no se puede deshacer</strong>: se borran la persona, sus vínculos y su foto.`,
      textoBoton: 'Eliminar definitivamente',
      onConfirmar: async () => {
        try { await apiPapeleraEliminar(id); }
        catch (e) { alert('No se pudo eliminar:\n\n' + e.message); return; }
        refrescar();
      }
    });
  }

  // Vaciar = DOBLE confirmación (es irreversible y borra todo). El segundo diálogo
  // se abre DESPUÉS de que el primero se cierre (setTimeout), porque solo puede
  // haber un diálogo a la vez.
  btnVaciar.addEventListener('click', () => {
    confirmarDialogo({
      titulo: 'Vaciar papelera',
      textoHTML: 'Se eliminarán <strong>definitivamente</strong> todas las personas de la papelera, con sus vínculos y sus fotos.',
      textoBoton: 'Continuar',
      onConfirmar: () => {
        setTimeout(() => confirmarDialogo({
          titulo: '¿Seguro del todo?',
          textoHTML: 'Esta acción es <strong>IRREVERSIBLE</strong>. No hay vuelta atrás.',
          textoBoton: 'Vaciar definitivamente',
          onConfirmar: async () => {
            try { await apiPapeleraVaciar(); }
            catch (e) { alert('No se pudo vaciar:\n\n' + e.message); return; }
            refrescar();
          }
        }), 0);
      }
    });
  });
})();
