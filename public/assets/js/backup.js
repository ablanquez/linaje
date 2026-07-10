// ─── Copias de seguridad (PASO 11) ────────────────────────────────────────
// Modal abierto desde el botón "Copias" (solo edición). Permite GENERAR copias
// (se guardan en el servidor, retención de 5, y se pueden descargar), RESTAURAR
// (desde una copia guardada o desde un archivo subido) y ELIMINAR copias.
// La restauración es DESTRUCTIVA: doble confirmación + el servidor guarda una
// copia automática del estado actual antes de tocar nada. Pieza modular pensada
// para el bloque "Datos" del futuro panel de administración.
(function () {
  // Igual que la papelera: desde el PASO 13 este modal se abre desde el bloque
  // «Datos» del panel (window.abrirCopias); el botón de barra ya no existe.
  const btn = document.getElementById('btnCopias');

  // esc() y formatearFechaHora() viven en util.js (Q1/Q2).
  const fechaBonita = s => formatearFechaHora(s);
  const tam = b => { b = +b || 0; return b < 1024 ? b + ' B' : b < 1048576 ? (b / 1024).toFixed(0) + ' KB' : (b / 1048576).toFixed(1) + ' MB'; };
  const esPrevio = a => /-previo(-|\.)/.test(a || '');

  // Modal (se crea una vez).
  const overlay = document.createElement('div');
  overlay.className = 'copias-overlay';
  overlay.hidden = true;
  overlay.innerHTML =
    `<div class="copias-caja" role="dialog" aria-modal="true">
       <div class="copias-cab">
         <h2>Copias de seguridad</h2>
         <div class="copias-cab-btns">
           <button type="button" class="copias-generar">Generar copia</button>
           <button type="button" class="copias-cerrar" aria-label="Cerrar">&times;</button>
         </div>
       </div>
       <div class="copias-lista"></div>
       <div class="copias-subir">
         <span class="copias-subir-txt">¿Tienes una copia en tu equipo?</span>
         <button type="button" class="copias-subir-btn">Restaurar desde archivo…</button>
         <input type="file" class="copias-file" accept="application/json,.json" hidden>
       </div>
     </div>`;
  document.body.appendChild(overlay);
  const lista = overlay.querySelector('.copias-lista');
  const btnGenerar = overlay.querySelector('.copias-generar');
  const btnSubir = overlay.querySelector('.copias-subir-btn');
  const inputFile = overlay.querySelector('.copias-file');

  const abrir = () => { overlay.hidden = false; refrescar(); };
  const cerrar = () => { overlay.hidden = true; };
  overlay.addEventListener('click', e => { if (e.target === overlay) cerrar(); });
  overlay.querySelector('.copias-cerrar').addEventListener('click', cerrar);
  if (btn) btn.addEventListener('click', abrir);
  window.abrirCopias = abrir;   // el panel de administración lo abre por aquí

  let cargando = false;
  async function refrescar() {
    if (cargando) return;
    cargando = true;
    lista.innerHTML = '<p class="copias-vacia">Cargando…</p>';
    let copias;
    try { copias = await apiBackupListar(); }
    catch (e) { lista.innerHTML = `<p class="copias-vacia">No se pudieron cargar las copias.</p>`; cargando = false; return; }
    finally { cargando = false; }

    if (!copias.length) { lista.innerHTML = '<p class="copias-vacia">Aún no hay copias. Pulsa «Generar copia».</p>'; return; }
    lista.innerHTML = '';
    copias.forEach(c => {
      const nPer = c.recuentos && c.recuentos.personas != null ? c.recuentos.personas : '?';
      const nFotos = c.recuentos && c.recuentos.fotos != null ? c.recuentos.fotos : 0;
      const item = document.createElement('div');
      item.className = 'copias-item';
      item.innerHTML =
        `<div class="copias-info">
           <div class="copias-fecha">${esc(fechaBonita(c.fecha))}${esPrevio(c.archivo) ? ' <span class="copias-tag">previa a restaurar</span>' : ''}</div>
           <div class="copias-datos">${nPer} personas · ${nFotos} fotos · ${tam(c.bytes)}</div>
         </div>
         <div class="copias-acciones">
           <button type="button" class="copias-descargar">Descargar</button>
           <button type="button" class="copias-restaurar">Restaurar</button>
           <button type="button" class="copias-eliminar" aria-label="Eliminar">Eliminar</button>
         </div>`;
      item.querySelector('.copias-descargar').addEventListener('click', () => descargar(c.archivo));
      item.querySelector('.copias-restaurar').addEventListener('click', () => restaurarGuardada(c.archivo, nPer));
      item.querySelector('.copias-eliminar').addEventListener('click', () => eliminar(c.archivo));
      lista.appendChild(item);
    });
  }

  // Generar: crea + guarda en el servidor + refresca la lista.
  btnGenerar.addEventListener('click', async () => {
    if (btnGenerar.disabled) return;
    btnGenerar.disabled = true; btnGenerar.textContent = 'Generando…';
    try { await apiBackupGenerar(); }
    catch (e) { alert('No se pudo generar la copia:\n\n' + e.message); }
    finally { btnGenerar.disabled = false; btnGenerar.textContent = 'Generar copia'; }
    refrescar();
  });

  // Descargar: fuerza la descarga del archivo (portero con sesión).
  // descargarArchivo() vive en util.js (JS-7).
  function descargar(archivo) {
    descargarArchivo(urlBackupDescargar(archivo), archivo);
  }

  // Eliminar: una confirmación.
  function eliminar(archivo) {
    confirmarDialogo({
      titulo: 'Eliminar copia',
      textoHTML: '¿Eliminar esta copia de seguridad? No se puede deshacer.',
      textoBoton: 'Eliminar',
      onConfirmar: async () => {
        try { await apiBackupEliminar(archivo); }
        catch (e) { alert('No se pudo eliminar:\n\n' + e.message); return; }
        refrescar();
      }
    });
  }

  // Mensaje común de restauración (destructiva) con la red de seguridad.
  function avisoRestaurar(detalle) {
    return `Vas a <strong>sustituir TODO el árbol actual</strong> por el contenido de esta copia `
      + `(personas, vínculos, ajustes, fotos y papelera). ${detalle} `
      + `Antes de tocar nada, se guardará automáticamente una copia del estado actual.`;
  }

  // Ejecuta la restauración (desde copia guardada o desde archivo) con doble
  // confirmación y recarga del árbol al terminar.
  function confirmarYRestaurar(detalle, ejecutar) {
    confirmarDialogo({
      titulo: 'Restaurar copia',
      textoHTML: avisoRestaurar(detalle),
      textoBoton: 'Continuar',
      onConfirmar: () => setTimeout(() => confirmarDialogo({
        titulo: '¿Seguro del todo?',
        textoHTML: 'Esta acción <strong>reemplaza el árbol actual</strong> por la copia. Se puede deshacer restaurando la copia automática «previa», pero confirma que quieres continuar.',
        textoBoton: 'Restaurar ahora',
        onConfirmar: async () => {
          try {
            const r = await ejecutar();
            await recargarDesdeBD();
            refrescar();
            const rec = r && r.restaurado;
            alert('Copia restaurada correctamente.' + (rec ? `\n\n${rec.personas} personas, ${rec.fotos} fotos.` : ''));
          } catch (e) {
            alert('No se pudo restaurar:\n\n' + e.message + '\n\nEl árbol NO se ha modificado.');
          }
        }
      }), 0)
    });
  }

  function restaurarGuardada(archivo, nPer) {
    confirmarYRestaurar(`La copia tiene ${nPer} personas.`, () => apiBackupRestaurar(archivo));
  }

  // Restaurar desde archivo subido.
  btnSubir.addEventListener('click', () => inputFile.click());
  inputFile.addEventListener('change', () => {
    const file = inputFile.files && inputFile.files[0];
    inputFile.value = '';   // permite volver a elegir el mismo archivo
    if (!file) return;
    confirmarYRestaurar(`Archivo: <strong>${esc(file.name)}</strong>.`, () => apiBackupRestaurarArchivo(file));
  });
})();
