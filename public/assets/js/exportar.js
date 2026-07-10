// ─── F2-N: exportar el árbol a imagen (PNG) o PDF ────────────────────────────
// La librería dibuja tarjetas en HTML y líneas en SVG. Para incluir TODO el árbol
// (no solo lo visible) montamos la vista al tamaño completo (store.getTree().dim,
// la misma medida que usa treeFit), rasterizamos con html-to-image y, para PDF,
// envolvemos el PNG con jsPDF. Disponible en lectura y edición.
(function () {
  const wrap = document.querySelector('.fab-exportar-wrap');
  const btn = document.getElementById('btnExportarArbol');
  const overlay = document.getElementById('exportOverlay');
  const msg = document.getElementById('exportMsg');

  btn.addEventListener('click', e => { e.stopPropagation(); wrap.classList.toggle('abierto'); });
  document.addEventListener('click', e => { if (!wrap.contains(e.target)) wrap.classList.remove('abierto'); });

  // slugify() y descargarArchivo() viven en util.js (JS-6/JS-7).
  function nombreArchivo(ext) {
    return slugify(arbolMeta.titulo, 'arbol') + '.' + ext;
  }
  const descargar = (dataUrl, nombre) => descargarArchivo(dataUrl, nombre);

  // Genera un PNG (dataURL) con el árbol COMPLETO. Devuelve { dataUrl, W, H }.
  //
  // El árbol se dibuja en DOS capas apiladas dentro de #f3Canvas: un <svg> con las
  // líneas y un <div id="htmlSvg"> con las tarjetas. En pantalla, AMBAS capas y el
  // propio canvas tienen el TAMAÑO DEL VIEWPORT (p. ej. 1584×905) con overflow
  // oculto, y el zoom/paneo de d3 vive como transform en los grupos .view /
  // .cards_view. Para exportar el árbol entero hay que, TEMPORALMENTE:
  //   1) agrandar TODA la cadena de contenedores (canvas, svg y #htmlSvg) al tamaño
  //      completo del árbol y quitarles el recorte (overflow) — si no, cualquier
  //      tarjeta/línea fuera del viewport se corta (era el fallo con árboles grandes);
  //   2) neutralizar el zoom de d3 poniendo en las dos capas una transform propia a
  //      escala 1 que sitúe el árbol dentro del lienzo.
  // Todo se restaura en el finally, pase lo que pase.
  async function generarPNG() {
    // Estado LIMPIO y REVELADO: family-chart revela las tarjetas de forma perezosa
    // (las de fuera del encuadre quedan a opacity 0) y deja "fantasmas" de
    // transiciones. Encuadrar TODO el árbol sin animación (fit, transition 0) hace
    // que dibuje y revele (opacity 1) TODAS las tarjetas y líneas de una vez y sin
    // fantasmas. Luego lo llevamos a tamaño completo para rasterizar.
    f3Chart.updateTree({ tree_position: 'fit', transition_time: 0 });
    await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));
    const dim = f3Chart.store.getTree().dim;
    const M = 60;
    const W = Math.ceil(dim.width + 2 * M), H = Math.ceil(dim.height + 2 * M);
    // Limitar el lado mayor para no superar el máximo de canvas del navegador
    // (~16384 px/lado en Chrome) en árboles enormes: bajamos resolución sin cortar.
    const escala = Math.max(0.5, Math.min(2, 13000 / Math.max(W, H)));
    const fam = document.getElementById('FamilyChart');
    const canvas = fam.querySelector('#f3Canvas');
    const svg = canvas.querySelector('svg');
    const svgView = svg.querySelector('.view');
    const htmlSvg = canvas.querySelector('#htmlSvg');
    const cardsView = htmlSvg && htmlSvg.querySelector('.cards_view');
    const fondo = (getComputedStyle(document.body).getPropertyValue('--bg').trim()) || '#ffffff';
    // Las líneas (.link) llevan stroke="#fff" como ATRIBUTO de presentación; en
    // pantalla lo tapa la CSS (.link{stroke:var(--line)}), pero html-to-image no
    // aplica esa regla al SVG y rasterizaría el #fff (líneas blancas, invisibles
    // en fondo claro). Fijamos el color real del tema como estilo EN LÍNEA (gana
    // al atributo y sí lo captura el rasterizado) y lo restauramos al terminar.
    const links = [...svg.querySelectorAll('.links_view .link')];
    let lineaColor = (getComputedStyle(document.body).getPropertyValue('--line').trim()) || '#333333';
    if (links[0]) { const s = getComputedStyle(links[0]).stroke; if (s && s !== 'none') lineaColor = s; }
    const prevStroke = links.map(l => l.style.stroke);
    // Ocultar los "puntitos" del mini-árbol de navegación: son ayuda interactiva,
    // no deben salir en la imagen exportada.
    const minis = [...canvas.querySelectorAll('.mini-tree')];
    const prevMini = minis.map(m => m.style.display);
    minis.forEach(m => { m.style.display = 'none'; });
    // Guardar estado (cssText de cada contenedor) para restaurar pase lo que pase.
    const prev = {
      famOv: fam.style.overflow,
      canvasCss: canvas.style.cssText, svgCss: svg.style.cssText, htmlCss: htmlSvg ? htmlSvg.style.cssText : '',
      svgW: svg.getAttribute('width'), svgH: svg.getAttribute('height'),
      vT: svgView.style.transform, cT: cardsView ? cardsView.style.transform : ''
    };
    // 1) Agrandar y desclipar TODA la cadena de contenedores.
    fam.style.overflow = 'visible';
    for (const el of [canvas, svg, htmlSvg]) {
      if (!el) continue;
      el.style.width = W + 'px'; el.style.height = H + 'px'; el.style.overflow = 'visible'; el.style.maxWidth = 'none'; el.style.maxHeight = 'none';
    }
    svg.setAttribute('width', W); svg.setAttribute('height', H);
    // 2) Colocar ambas capas a escala 1 dentro del lienzo (neutraliza el zoom d3).
    const off = `translate(${dim.x_off + M}px,${dim.y_off + M}px) scale(1)`;
    svgView.style.transform = off;
    if (cardsView) cardsView.style.transform = off;
    links.forEach(l => { l.style.stroke = lineaColor; });
    try {
      const dataUrl = await htmlToImage.toPng(canvas, { pixelRatio: escala, backgroundColor: fondo, width: W, height: H });
      return { dataUrl, W, H };
    } finally {
      fam.style.overflow = prev.famOv;
      canvas.style.cssText = prev.canvasCss; svg.style.cssText = prev.svgCss; if (htmlSvg) htmlSvg.style.cssText = prev.htmlCss;
      if (prev.svgW) svg.setAttribute('width', prev.svgW); else svg.removeAttribute('width');
      if (prev.svgH) svg.setAttribute('height', prev.svgH); else svg.removeAttribute('height');
      svgView.style.transform = prev.vT; if (cardsView) cardsView.style.transform = prev.cT;
      links.forEach((l, i) => { l.style.stroke = prevStroke[i]; });   // restaurar líneas
      minis.forEach((m, i) => { m.style.display = prevMini[i]; });     // restaurar mini-árbol
      f3Chart.updateTree({ tree_position: 'fit' });   // devolver la vista a su sitio
    }
  }

  async function exportar(tipo) {
    if (btn.disabled) return;
    wrap.classList.remove('abierto');
    btn.disabled = true;
    msg.textContent = tipo === 'pdf' ? 'Generando PDF…' : 'Generando imagen…';
    overlay.classList.add('visible');
    // Dejar pintar el velo antes de la tarea pesada.
    await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));
    try {
      const { dataUrl, W, H } = await generarPNG();
      if (tipo === 'png') {
        descargar(dataUrl, nombreArchivo('png'));
      } else {
        const { jsPDF } = window.jspdf;
        const pw = W * 0.75, ph = H * 0.75;   // px (96dpi) -> pt (72)
        const pdf = new jsPDF({ orientation: W >= H ? 'landscape' : 'portrait', unit: 'pt', format: [pw, ph] });
        // compresión 'SLOW' (zlib máx): el PDF pesa como el PNG, no decenas de MB.
        pdf.addImage(dataUrl, 'PNG', 0, 0, pw, ph, 'arbol', 'SLOW');
        pdf.save(nombreArchivo('pdf'));
      }
    } catch (err) {
      console.error('Export error:', err);
      alert('No se pudo exportar el árbol.');
    } finally {
      overlay.classList.remove('visible');
      btn.disabled = false;
    }
  }
  document.getElementById('btnExpPNG').addEventListener('click', () => exportar('png'));
  document.getElementById('btnExpPDF').addEventListener('click', () => exportar('pdf'));
})();
