# Bitácora de fallos — Linaje (001)
> El registro crudo de los fallos reales del proyecto.
> No es un changelog (eso cuenta qué cambió). No es la guía (eso cuenta la ley).
> Esto cuenta EL CASO: qué pasó, qué prueba dio verde mientras pasaba, y cómo
> se cazó.
>
> **Fuente:** histórico de la conversación del proyecto (1.392 mensajes,
> 06–10 julio 2026) + historial de commits + documentación interna del repo
> (`PLAN-QA-SEGURIDAD.md`, `BACKTEST-FINAL.md`, `AUDITORIA-DUPLICACION.md`,
> `PENDIENTES.md`, `ESTADO-Y-DECISIONES.md`, `ESTADO.md`, `NOTAS-LIBRERIA.md`,
> `DESPLIEGUE-Y-SEGURIDAD.md`).
> **Cobertura:** [se declara al final del documento]

---

## Nota sobre dos campos del formato

- **Commit:** el repositorio se publicó con TODO el desarrollo (115 ficheros,
  12.489 líneas) en un único commit inicial (`ad23621`, 11-jul-2026) y un
  segundo commit que solo toca el README (`6954dd9`). **No existe ni un solo
  commit `fix:`**, así que el arreglo de casi ningún fallo se puede fechar por
  git: el campo va como `NO CONSTA` salvo el propio commit inicial. No es un
  hueco de investigación, es cómo se hizo el repo. Se declara en la cobertura.
- **Traza:** campo añadido al formato canónico para conservar la trazabilidad
  forense a la fuente (nº de mensaje de la conversación, código de hallazgo de
  la auditoría interna y ficheros/funciones citados). No sustituye a ningún
  campo pedido; los complementa.

---

# 2026-07-06 — El día de las ñapas (arrancar, tropezar con la librería, rehacer de cero)

## [2026-07-06] — El árbol no sabía qué era un hermano
**Categoría:** carencia
**Síntoma:** Se entregó el árbol "listo para usar" sin ninguna forma directa de añadir un hermano/a.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El asistente entregó el artefacto como terminado en #4 ("Aquí tenéis el árbol genealógico… a funcionar").
**Causa raíz:** Se modeló solo padres/hijos/pareja asumiendo que los hermanos "se deducen" de padres compartidos, sin comprobar si eso era usable.
**Cómo se cazó:** usuario ("has contemplado también hermanos y todo eso?")
**Arreglo aplicado:** Botón "+ Hermano/a" que copia los padres del hermano de referencia; detección de medios hermanos; campo sexo.
**Commit:** NO CONSTA
**Ley que sale de aquí:** "Se deduce del modelo" no significa "el usuario puede hacerlo".
**Traza:** #5–#8; `linkRelation`, `openAdd`, `openEdit`, `savePerson`.

## [2026-07-06] — Guardado persistente que no persistía fuera de casa
**Categoría:** despliegue
**Síntoma:** Se vendió "guardado persistente real" cuando el mecanismo (`window.storage`) solo existe dentro de la interfaz de Claude; en un servidor web se perdería todo al recargar.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ En #4 se afirmó "Todo se guarda solo entre sesiones, así que podéis cerrar y volver otro día sin perder nada", sin matizar el entorno.
**Causa raíz:** Se usó una API propietaria del entorno de artefactos como si fuera persistencia real, sin contrastarla con el objetivo declarado (subirlo a un servidor).
**Cómo se cazó:** usuario (preguntó si se podría subir a un servidor web)
**Arreglo aplicado:** Se explicó la limitación y se rehízo la persistencia contra `datos.json` + `guardar.php`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** La persistencia solo cuenta como persistencia en el entorno donde el usuario va a usarlo.
**Traza:** #4, #9–#12, #14; `window.storage`, `index.html`, `datos.json`, `guardar.php`.

## [2026-07-06] — El árbol con cara de "ventana de Teams"
**Categoría:** visual
**Síntoma:** Las tarjetas rectangulares del primer diseño resultaban feas comparadas con la imagen de referencia del usuario.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (el asistente entregó el ejemplo de 7 generaciones y pidió opinión).
**Causa raíz:** Se diseñó a ojo sin pedir antes referencia visual al usuario.
**Cómo se cazó:** usuario (envió una imagen de referencia)
**Arreglo aplicado:** Rediseño con fotos redondas, aro de color, corazones en las uniones y sin cajas.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Pide la referencia visual antes de dibujar, no después.
**Traza:** #20–#23; HTML del árbol de ejemplo.

## [2026-07-06] — El visor se colgó dos veces seguidas
**Categoría:** rompe
**Síntoma:** El visor interactivo del entorno se quedó colgado al renderizar el árbol grande, dos intentos seguidos.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** Árbol con demasiados elementos para el visor inline del entorno.
**Cómo se cazó:** casualidad (al intentar mostrarlo)
**Arreglo aplicado:** Plan B: generar el HTML como archivo descargable para abrirlo en el navegador del usuario.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Ten preparado el plan B de entrega antes de que el canal de previsualización falle.
**Traza:** #23.

## [2026-07-06] — El motor de colocación casero no colgaba a los hijos de sus padres
**Categoría:** rompe
**Síntoma:** Hijos no debajo de sus padres, líneas cruzándose por todas partes, imposible seguir quién desciende de quién.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (se entregó tras "replicar los detalles de tu imagen").
**Causa raíz:** El algoritmo centraba cada generación sin mirar a los hijos, en vez de reservar el ancho de la descendencia (tipo Reingold-Tilford).
**Cómo se cazó:** usuario (captura)
**Arreglo aplicado:** Reescritura del motor de abajo arriba, centrando cada pareja sobre el bloque de sus hijos.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Colocar árboles con parejas es un problema resuelto; no lo reinventes a mano.
**Traza:** #23–#26; lógica de posicionamiento del árbol.

## [2026-07-06] — La comprobación numérica dijo "sin solapes" y había solapes
**Categoría:** silencio falso
**Síntoma:** Un script en Node validó "36 personas colocadas, 6 generaciones, sin solapes"; la imagen renderizada mostraba el bloque de nietos de la izquierda montado sobre el del centro.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La validación numérica de solapes: solo comparaba dentro de la misma fila exacta, así que los subárboles que se pisaban entre filas pasaron el test.
**Causa raíz:** El test medía la propiedad equivocada (colisiones en la misma Y) en vez de la real (subárboles vecinos sin reserva de ancho).
**Cómo se cazó:** ojo humano (el propio asistente renderizó a imagen con Chrome headless y la miró)
**Arreglo aplicado:** Reservar para cada persona el ancho completo de su descendencia.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un test que pasa sobre la métrica equivocada es peor que no tener test: da permiso para entregar.
**Traza:** #26; módulo evaluable de posicionamiento, Playwright/Chromium headless.

## [2026-07-06] — "Sin solapes ni líneas cruzadas" y seguía siendo un desastre
**Categoría:** silencio falso
**Síntoma:** Tras la segunda reescritura, cónyuges aparecían aparcados arriba a la derecha, separados de su pareja, con líneas cruzando medio árbol. El usuario: "esta hecho aun de puta pena".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El asistente renderizó la imagen, la inspeccionó "con mis propios ojos" y declaró: "no hay solapes ni líneas cruzadas… la estructura ya es la de un árbol genealógico de verdad".
**Causa raíz:** El algoritmo propio no emparejaba: colocaba a los cónyuges en filas distintas en vez de junto a su pareja.
**Cómo se cazó:** usuario
**Arreglo aplicado:** Se abandonó el motor casero y se pasó a la librería family-chart.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Mirar una captura y que "te parezca bien" no es verificación; define de antemano qué invariantes tiene que cumplir la imagen.
**Traza:** #26–#31; motor de colocación propio.

## [2026-07-06] — Pantalla en blanco por mezclar dos APIs de la librería
**Categoría:** rompe
**Síntoma:** El árbol con family-chart no dibujaba nada; solo un resto de tarjeta en el borde derecho.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El asistente entregó afirmando que "el algoritmo no es mío, es el de una librería probada por miles de usuarios… el riesgo de que la colocación salga mal es mínimo".
**Causa raíz:** Se usaron métodos de una versión antigua (`f3.createStore`, `f3.d3AnimationCreateSvg`) en vez de la API 0.9.0 (`f3.createChart`). Métodos inventados/desfasados.
**Cómo se cazó:** usuario (lo abrió y no se veía nada)
**Arreglo aplicado:** Reescritura con `f3.createChart('#FamilyChart', data)` según el ejemplo oficial.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Que la librería sea fiable no hace fiable tu llamada a la librería.
**Traza:** #31–#32; `f3.createStore`, `f3.d3AnimationCreateSvg`, `f3.createChart`.

## [2026-07-06] — Líneas de parentesco blancas sobre fondo claro (v1)
**Categoría:** visual
**Síntoma:** Las líneas del árbol no se veían: el CSS de la librería las pinta claras (pensado para fondo oscuro).
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** La librería pinta el trazo con `stroke="#fff"`; el asistente no pudo confirmar el selector y no investigó la fuente.
**Cómo se cazó:** usuario
**Arreglo aplicado:** Ñapa doble: regla CSS "por si acaso" + script JS que recorre el SVG repintando las líneas por su forma, reaplicado tras zoom/movimiento. (Más tarde, en el proyecto nuevo, se sustituyó por una única regla CSS sobre `.link`, sin `!important` ni `setInterval` — reconocido como "una de mis peores ñapas".)
**Commit:** NO CONSTA
**Ley que sale de aquí:** Ser "redundante por si acaso" es confesar que no has ido a mirar la fuente.
**Traza:** #33, #134, #135; `blackenLines`, `paintLines`, `.link`.

## [2026-07-06] — La tarjeta del título desaparecía en modo oscuro
**Categoría:** visual
**Síntoma:** Al añadir el modo oscuro, la tarjeta del título de arriba a la izquierda seguía con color fijo y se fundía con el fondo.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (el asistente entregó el modo oscuro dándolo por bueno en #34).
**Causa raíz:** Color fijo codificado en la tarjeta, no vinculado a las variables de tema.
**Cómo se cazó:** usuario
**Arreglo aplicado:** Que la tarjeta responda al tema.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Al introducir un tema, audita TODO lo que lleve color fijo, no solo lo que acabas de tocar.
**Traza:** #34, #35; CSS del título, `blackenLines`.

## [2026-07-06] — El arreglo del título no era el contraste que se pidió
**Categoría:** visual
**Síntoma:** Tras "corregirlo", en modo oscuro el título pasó a un gris "algo más claro que el fondo": seguía sin destacar. El usuario tuvo que deletrear la regla (fondo claro → título oscuro; fondo oscuro → título claro).
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El asistente declaró "Corregido… se distingue bien en vez de fundirse".
**Causa raíz:** Se interpretó "que se vea" como "que se distinga un poco" en vez de "invertido respecto al fondo".
**Cómo se cazó:** usuario
**Arreglo aplicado:** Título siempre al contraste del fondo, con el texto invertido.
**Commit:** NO CONSTA
**Ley que sale de aquí:** "Corregido" solo lo dice el que pidió el cambio.
**Traza:** #36–#38; CSS de la tarjeta de título.

## [2026-07-06] — Métodos de la librería que no existían
**Categoría:** rompe
**Síntoma:** El código del editor llamaba a `fixed`, `setEditFirst`, `setOnHoverPathToMain`, `getMainDatum` y a `setFields` con objetos `{type,label,id}`, ninguno confirmado en la 0.9.0.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (se escribieron y solo después se decidió quitarlos "por prudencia").
**Causa raíz:** Se inventó la API a partir de intuición en vez de leer la referencia.
**Cómo se cazó:** el propio asistente, al releer la documentación antes de entregar
**Arreglo aplicado:** Quitar los métodos no documentados y usar `setFields(["first name", …])` con nombres simples (efecto colateral: etiquetas en inglés).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si no puedes citar dónde está documentado un método, no lo llames.
**Traza:** #41; `setFields`, `fixed`, `setEditFirst`, `setOnHoverPathToMain`, `getMainDatum`.

## [2026-07-06] — El editor de la librería no se abría al hacer clic
**Categoría:** rompe
**Síntoma:** Se pulsaba la tarjeta y no pasaba nada; el editor nunca aparecía.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El asistente entregó diciendo "arranca con una persona vacía… al pulsar sobre una tarjeta se abre su ficha de edición".
**Causa raíz:** `editTree()` por sí solo no conecta el clic; faltaba `setCardClickOpen(f3Card)`, método que el asistente no encontró hasta mucho después.
**Cómo se cazó:** usuario ("no sale")
**Arreglo aplicado:** Primero se abandonó el editor nativo (ver siguiente entrada); después, al leer la referencia de `EditTree`, se descubrió `setCardClickOpen` y se rehízo con el editor oficial.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Antes de declarar que una funcionalidad "no se puede", agota la referencia de clases de la librería.
**Traza:** #41–#44, #71; `editTree()`, `setCardClickOpen`, `EditTree`.

## [2026-07-06] — Se tiró el editor nativo para hacer uno casero… y hubo que volver atrás
**Categoría:** rompe
**Síntoma:** Ante el editor que no se abría, se decidió construir un panel de edición propio (opción A). Todo ese panel, el detector de clics de respaldo y la gestión manual de familiares hubo que tirarlos después.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El panel casero llegó a funcionar y el usuario metió datos reales de su familia con él (#47).
**Causa raíz:** Se tomó una decisión arquitectónica de fondo (abandonar la librería para editar) basada en una carencia de investigación, no en una limitación real.
**Cómo se cazó:** usuario ("quiero que el formulario sea con lo que marca la librería… 0 ñapas")
**Arreglo aplicado:** Reconstrucción completa con `editTree` + `setFields` + `setCardClickOpen` + `setAddRelLabels`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Rendirse ante una librería mal leída cuesta más caro que leerla.
**Traza:** #44–#46, #68–#73; panel casero `openPanel`/`savePerson`, `editTree`.

## [2026-07-06] — El validador propio inventó un error de sintaxis
**Categoría:** aviso falso
**Síntoma:** La validación con `new Function` reportó "Unexpected token )" en una línea que era correcta; el asistente perdió varios intentos culpando al heredoc de bash y a `${…}`.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Inverso: el validador dio ROJO sobre código correcto (falso positivo) y ocultó el error real, que estaba en otra línea.
**Causa raíz:** `new Function` evaluando fragmentos aislados con template strings/backticks no es un parser fiable.
**Cómo se cazó:** el propio asistente, al reevaluar con `node --check` sobre un `.js` temporal
**Arreglo aplicado:** Validar con el parser real de Node (`--check`) en vez de `new Function`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Usa el parser de verdad; un validador improvisado te hace perseguir fantasmas.
**Traza:** #46; `new Function`, `node --check`.

## [2026-07-06] — Un `});` de más en una arrow function
**Categoría:** rompe
**Síntoma:** Error real de sintaxis en la línea 117 del script: `onclick=()=>{...})` cerrado con `});` en vez de `};`.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La validación con `new Function` señalaba otra línea distinta (ver entrada anterior).
**Causa raíz:** Confusión entre arrow function asignada y callback de llamada.
**Cómo se cazó:** test (`node --check`)
**Arreglo aplicado:** Corregir el cierre.
**Commit:** NO CONSTA
**Ley que sale de aquí:** El parser correcto lo encuentra en un segundo; el improvisado te manda a otra línea.
**Traza:** #46; script inline de `index.html`.

## [2026-07-06] — El panel de edición se salía por la derecha
**Categoría:** visual
**Síntoma:** Barra de scroll horizontal y botones "+ Hijo/a" cortados: los cuatro botones de añadir familiar no cabían en una fila.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** Cuatro botones en una fila dentro de un panel más estrecho que su contenido.
**Cómo se cazó:** ojo humano (el asistente lo vio en la captura del usuario)
**Arreglo aplicado:** Rejilla 2×2 y panel más estrecho, sin desbordes.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Los controles nuevos hay que verlos al ancho real del contenedor, no en abstracto.
**Traza:** #47–#49; CSS del panel de edición.

## [2026-07-06] — Apellidos cortados en las tarjetas
**Categoría:** visual
**Síntoma:** "Blánquez Lacruz" no cabía: las tarjetas tenían ancho fijo y los nombres largos se salían.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** Ancho de tarjeta fijo sin ajuste de texto.
**Cómo se cazó:** usuario (datos reales con apellidos largos)
**Arreglo aplicado:** `setCardDim` (envuelto en try porque no se pudo confirmar la firma) + regla CSS de respaldo forzando el ajuste del texto.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Prueba siempre con el dato real más largo, no con "Juan".
**Traza:** #50, #52; `setCardDim`.

## [2026-07-06] — La capa casera de círculos reescribiendo las tarjetas
**Categoría:** rompe
**Síntoma:** Se empezó a montar una capa JS que reescribía cada tarjeta de la librería tras el render para convertirla en círculo — reconocida por el propio asistente como frágil ("hay riesgo real de que no encaje: posiciones raras, tarjetas duplicadas").
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (se paró antes de entregar)
**Causa raíz:** Se afirmó que la documentación no daba el método de tarjeta personalizada y se improvisó una capa por encima.
**Cómo se cazó:** usuario ("investiga más a fondo la librería porque si la documentación dice que se puede tiene que estar explicado por algún sitio")
**Arreglo aplicado:** Eliminar `styleCards`/`cardHTML` y usar los métodos oficiales (`setStyle("imageCircle")`, `setCardImageField`, `setDefaultPersonIcon`, `setOnCardClick`, `setCardInnerHtmlCreator`).
**Commit:** NO CONSTA
**Ley que sale de aquí:** "No está documentado" casi siempre significa "no he leído la referencia de clases".
**Traza:** #58–#60; `styleCards`, `cardHTML`, `CardHtmlClass`.

## [2026-07-06] — "La documentación no lo cubre" (sí lo cubría)
**Categoría:** aviso falso
**Síntoma:** El asistente afirmó dos veces que la librería no documentaba las tarjetas personalizadas ni el editor, y tomó decisiones grandes (panel casero, capa de círculos) sobre esa premisa falsa.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El asistente dio por concluida la búsqueda tras mirar solo la portada del repo y los ejemplos.
**Causa raíz:** Investigación superficial: no se consultó `donatso.github.io/family-chart/classes/` (CardHtmlClass, EditTree), que tenía todo.
**Cómo se cazó:** usuario (insistió en que buscara mejor)
**Arreglo aplicado:** Leer la referencia de clases; aparecieron `setStyle("imageCircle")`, `setCardInnerHtmlCreator`, `setCardClickOpen`, `setAddRelLabels`, `setNoEdit`, `setOnChange`, `exportData`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** El usuario tenía razón: si la portada dice que se puede, está escrito en algún sitio. Búscalo.
**Traza:** #59, #60, #68–#71; `CardHtmlClass`, `EditTree`.

## [2026-07-06] — Nombres truncados en la tarjeta circular
**Categoría:** visual
**Síntoma:** "Angeles Lacruz La…", "Jesús Antonio Blá…": el texto iba dentro de una cajita gris de ancho fijo.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** Estilo por defecto de la librería (píldora gris de ancho fijo) bajo la tarjeta.
**Cómo se cazó:** ojo humano / usuario
**Arreglo aplicado:** `setCardInnerHtmlCreator` con texto suelto en líneas, sin cajita.
**Commit:** NO CONSTA
**Ley que sale de aquí:** El render por defecto de la tarjeta no es tu diseño; contrólalo con el creador oficial de HTML interno.
**Traza:** #61, #62, #66, #73; `setCardInnerHtmlCreator`.

## [2026-07-06] — La cajita del nombre tapaba el círculo
**Categoría:** visual
**Síntoma:** El recuadro gris del nombre se montaba sobre el borde inferior de la foto en vez de quedar debajo.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** Solapamiento del layout por defecto de la tarjeta con el estilo `imageCircle`.
**Cómo se cazó:** usuario
**Arreglo aplicado:** Control total del interior de la tarjeta con `setCardInnerHtmlCreator`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Combinar un estilo de la librería con el layout por defecto produce solapes; toma el control entero del interior.
**Traza:** #64, #65, #73; `setCardInnerHtmlCreator`.

## [2026-07-06] — El rectángulo gris de fondo que nadie quería
**Categoría:** visual
**Síntoma:** El texto bajo el círculo salía sobre un fondo rectangular gris, lejos de la imagen de referencia.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** Estilo por defecto de la librería no anulado.
**Cómo se cazó:** usuario
**Arreglo aplicado:** Eliminarlo con la tarjeta personalizada oficial.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Cada resto visual del estilo por defecto que no anulas se queda en pantalla.
**Traza:** #66, #73; CSS de la librería, `setCardInnerHtmlCreator`.

## [2026-07-06] — Cuadros "ADD" que aparecían solos
**Categoría:** visual
**Síntoma:** La librería dibujaba automáticamente un hueco negro "ADD" cuando alguien tenía hijos pero no pareja registrada — absurdo en casos de madre soltera, viudedad o adopción.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** Comportamiento por defecto de la librería (`setSingleParentEmptyCard`) no desactivado.
**Cómo se cazó:** usuario (captura)
**Arreglo aplicado:** `setSingleParentEmptyCard(false)`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Los defaults de una librería son decisiones de otro; revísalos todos.
**Traza:** #67, #73; `setSingleParentEmptyCard`.

## [2026-07-06] — El formulario del editor, ilegible en tema claro
**Categoría:** visual
**Síntoma:** El panel del editor nativo salía oscuro con las etiquetas casi ilegibles sobre el tema claro.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** La librería fija sus propios colores (`--background-color: rgb(33,33,33)`, `--text-color:#fff`) y no se engancharon al tema.
**Cómo se cazó:** usuario (captura)
**Arreglo aplicado:** v1 (ñapa): reglas CSS "adivinando" clases + script que localiza el formulario en el DOM y le pinta los colores. v2 (limpio, en el proyecto nuevo): redefinir esas dos variables CSS solo dentro de `.f3-form-cont`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Para tematizar CSS de terceros, redefine SUS variables; no repintes elemento a elemento.
**Traza:** #74–#76, #132, #133; `themeEditorForm`, `.f3-form-cont`, `--background-color`, `--text-color`.

## [2026-07-06] — La botonera se montaba encima del formulario
**Categoría:** visual
**Síntoma:** Los botones "Finalizar edición / Exportar / Importar" se solapaban con el panel del editor, que se abre por esa misma esquina.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** Barra de botones y formulario compartiendo la esquina superior derecha sin jerarquía de capas.
**Cómo se cazó:** usuario (captura)
**Arreglo aplicado:** Subir el z-index del formulario para que tape la barra.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Dos cosas en la misma esquina necesitan jerarquía de capas explícita.
**Traza:** #74–#76; z-index del formulario / `.toolbar`.

## [2026-07-06] — Línea CSS mal formada colada en el archivo
**Categoría:** rompe
**Síntoma:** Se escribió una regla CSS inválida (`background:var(--title-bg)==transparent…`).
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** Desliz al editar; ninguna validación de CSS en el flujo.
**Cómo se cazó:** el propio asistente, al releer
**Arreglo aplicado:** Quitar la línea.
**Commit:** NO CONSTA
**Ley que sale de aquí:** El JS se validaba con parser; el CSS no se validaba con nada.
**Traza:** #76; CSS de `index.html`.

## [2026-07-06] — La interfaz de la librería hablaba inglés
**Categoría:** carencia
**Síntoma:** "Male/Female", "Cancel/Submit", "Delete", "Remove Relation" en un producto que debía estar íntegramente en español.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** Los textos están hardcodeados en la librería; no hay API de i18n. Usar `setFields` con nombres simples (para no arriesgar) dejaba además las etiquetas en inglés.
**Cómo se cazó:** usuario (captura)
**Arreglo aplicado:** v1 (ñapa): script que recorre el DOM sustituyendo textos. v2 (limpio): hook oficial `setOnFormCreation(fn)`, una sustitución por render, sin polling.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si la librería no tiene i18n, el hook de creación del formulario es el único punto legítimo de traducción.
**Traza:** #74, #77, #132, #133; `translateEditor`, `setOnFormCreation`, `setAddRelLabels`.

## [2026-07-06] — "Traducido" pero seguía poniendo Male y Female
**Categoría:** silencio falso
**Síntoma:** Tras anunciar la traducción completa, el selector de sexo seguía en inglés.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El asistente afirmó "Traducido. Ahora los textos internos del editor pasan al español… comprueba que ya sale todo en español", sin haber ejecutado nada.
**Causa raíz:** El script solo buscaba "elementos sin hijos con ese texto exacto"; "Male"/"Female" son nodos de texto sueltos dentro de un `<label>` que contiene un `<input radio>`.
**Cómo se cazó:** usuario ("sigue saliendo Male y Female")
**Arreglo aplicado:** Recorrer todos los nodos de texto (no elementos) y sustituir.
**Commit:** NO CONSTA
**Ley que sale de aquí:** No anuncies "hecho" sobre algo que no has visto ejecutarse.
**Traza:** #77–#79; `translateEditor`.

## [2026-07-06] — El secuestro del botón muñequito no funcionó
**Categoría:** rompe
**Síntoma:** El segundo icono (varitas) no se ocultaba y al pulsar el muñequito seguía saliendo el comportamiento por defecto de la librería, no el menú nuevo.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El asistente entregó reconociendo que enganchar el botón "es la parte con más probabilidad de necesitar ajuste", pero lo entregó igual.
**Causa raíz:** Se identificaba el botón con una heurística ("iconos sin texto de la cabecera") sin poder inspeccionar el DOM real. Además, el asistente se adelantó: montó el menú y el hijack a la vez, sin confirmar antes que podía engancharse.
**Cómo se cazó:** usuario ("no se comporta como debería sino que lo hace como estaba por defecto… no sé por qué te has adelantado antes a correr")
**Arreglo aplicado:** Se descartó el hijack y se puso un botón propio "+ Añadir familiar" dentro del formulario.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si no puedes inspeccionar el DOM que vas a interceptar, no lo interceptes.
**Traza:** #88–#96; `hijackFormButtons`, `currentOpenPersonId`.

## [2026-07-06] — Un botón "+ Añadir familiar" por cada campo del formulario
**Categoría:** rompe
**Síntoma:** El botón se insertó repetido, uno detrás de cada campo. "Un despropósito total" (palabras del asistente).
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El asistente entregó diciendo que el botón "lo inserto en el formulario detectándolo por contenido, que es fiable".
**Causa raíz:** La detección de "el formulario" consideraba formulario a cada bloque de campo (cada input contaba como "form-ish"), y se insertaba en todos.
**Cómo se cazó:** usuario (captura)
**Arreglo aplicado:** Insertar una sola vez, en el contenedor con más inputs, comprobando que no exista ya. Mismo criterio aplicado a `themeEditorForm`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** "Detectar por contenido" sin poder ver el DOM real es adivinar con otro nombre.
**Traza:** #100, #101; `themeEditorForm`, `currentOpenPersonId`, inserción del botón.

## [2026-07-06] — El arreglo del botón duplicado dejó un paréntesis suelto
**Categoría:** rompe
**Síntoma:** Al cambiar `forEach(f=>{` por `{` en `themeEditorForm`, el cierre `});` quedó con un `)` de más.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** Edición quirúrgica de un bloque sin ajustar su cierre.
**Cómo se cazó:** el propio asistente, al validar sintaxis
**Arreglo aplicado:** Corregir el cierre a `}`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Cada arreglo es código nuevo y merece la misma validación que el original.
**Traza:** #101; `themeEditorForm`.

## [2026-07-06] — El menú se cerraba en el mismo clic que lo abría
**Categoría:** rompe
**Síntoma:** El menú "+ Añadir familiar" salía pero ningún clic dentro respondía.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** El listener de "cerrar al hacer clic fuera" estaba en fase de captura (`addEventListener('click', …, true)`), así que se ejecutaba antes que los `onclick` de los botones y en el mismo evento que lo abría.
**Cómo se cazó:** usuario (captura) + razonamiento del asistente
**Arreglo aplicado:** Ignorar el clic que acaba de abrir el menú y quitar la fase de captura.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un listener global en captura es un cañón apuntando a tus propios botones.
**Traza:** #102; `relMenu`, `relMenuJustOpened`.

## [2026-07-06] — Se arregló el problema equivocado
**Categoría:** aviso falso
**Síntoma:** El asistente dedicó un ciclo entero a diagnosticar y arreglar el menú y su z-index, cuando lo que el usuario estaba reportando era que **Guardar no guardaba**.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Se declaró "Arreglado el problema de los clics" mientras el fallo real (la pérdida de datos al guardar) seguía intacto.
**Causa raíz:** Se interpretó la queja del usuario desde el contexto del último cambio propio, en vez de preguntar.
**Cómo se cazó:** usuario ("No me entendiste… cuando creo un nuevo miembro y meto los datos y guardo no funciona el guardar")
**Arreglo aplicado:** Reenfocar sobre el guardado.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Cuando el usuario dice "no funciona", pregúntale QUÉ no funciona antes de arreglar lo que tú tenías en la cabeza.
**Traza:** #102–#104.

## [2026-07-06] — Guardar no guardaba: dos almacenes de datos desincronizados
**Categoría:** datos
**Síntoma:** Se rellenaban los datos de una persona, se pulsaba Guardar, y el círculo seguía vacío. Los datos se perdían. (Este es el fallo raíz que motivó rehacer el proyecto desde cero; documentado también en `ESTADO.md` y `NOTAS-LIBRERIA.md`.)
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Nada del guardado se probó nunca: se entregó la edición como funcional durante varias iteraciones (menús, tarjetas, temas) sin que nadie hubiera comprobado un ciclo completo escribir→guardar→redibujar. El propio "Guardar" del editor de family-chart daba la operación por buena (escribía en su store interno) mientras el redibujado desde `people` la pisaba.
**Causa raíz:** Dos fuentes de verdad: el array `people` propio y el store interno de family-chart. El editor escribía en el store; `buildChart()` recreaba el chart entero desde `people` en cada cambio, tirando lo guardado.
**Cómo se cazó:** usuario
**Arreglo aplicado:** Se rehízo el proyecto desde cero con el store de la librería como única fuente de verdad (`store.getData()`, `store.updateData()`, `updateTree()`), sin array paralelo y sin recrear el chart.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Dos almacenes de datos = un bug esperando. Una sola fuente de verdad, siempre.
**Traza:** #103–#105, #121, #122, #125, #126, #131, #132; array `people`, `buildChart()`, `f3Chart.store`, `updateTree()`, `ESTADO.md` §"EL PROBLEMA GRANDE", `NOTAS-LIBRERIA.md` §"EL ERROR DE FONDO".

## [2026-07-06] — El diagnóstico escrito en ESTADO.md era erróneo
**Categoría:** aviso falso
**Síntoma:** El documento de traspaso afirmaba que el problema era "dos fuentes de datos desincronizadas"; al leer la fuente real, los métodos (`setOnChange`, `exportData`, `store.getData`, `updateTree`) sí existían y funcionaban: la causa era que `buildChart()` recreaba el chart entero desde `people` en cada cambio.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El diagnóstico se escribió y se entregó como contexto autorizado en `ESTADO.md` y `CLAUDE.md`, sin haberse verificado nunca en un navegador.
**Causa raíz:** Se dedujo la causa raíz sin poder ejecutar nada, y se documentó como si fuera un hecho.
**Cómo se cazó:** Claude Code, al leer la fuente de family-chart 0.9.0 y citar líneas concretas
**Arreglo aplicado:** Corregir el diagnóstico y atacar la causa real (no recrear el chart; redibujar con `updateTree()`).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un diagnóstico sin ejecutar es una hipótesis; escribirlo en un documento de traspaso lo convierte en un dogma falso.
**Traza:** #110, #112, #121, #122, #126; `ESTADO.md`, `buildChart()`, `setOnChange`, `exportData`, `updateTree()`.

## [2026-07-06] — Los try/catch que se tragaban los errores
**Categoría:** silencio falso
**Síntoma:** El código estaba blindado con try/catch "por si algún método no existe", de modo que los fallos reales del guardado no producían ningún error visible.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La app "no se rompía": precisamente por eso nadie vio que el guardado estaba muerto.
**Causa raíz:** Blindar llamadas a una API que no se había verificado, para que "al menos se vea el árbol".
**Cómo se cazó:** Claude Code (señaló la línea del `setOnChange`/`exportData` con try/catch silencioso como sospechoso principal)
**Arreglo aplicado:** Proyecto nuevo sin esos try/catch, con métodos verificados.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un try/catch que oculta que un método no existe no es robustez: es un bug con mordaza.
**Traza:** #73, #121, #126; `setOnChange`, `exportData`, try/catch de `index.html`.

## [2026-07-06] — El test del guardado pulsaba "Cancelar" en vez de "Guardar"
**Categoría:** silencio falso
**Síntoma:** El test automatizado de persistencia de Claude Code no probaba nada: enviaba el formulario por el botón equivocado.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El propio test de persistencia — el único que verificaba el fallo principal del proyecto.
**Causa raíz:** Selector/botón mal elegido en el guion CDP.
**Cómo se cazó:** el propio Claude Code, que lo confesó y lo corrigió
**Arreglo aplicado:** Corregir el test para pulsar Submit y volver a verificar (guardar → forzar redibujado → el dato sigue ahí).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un test verde que no llegó a ejercitar la acción es peor que ningún test.
**Traza:** #131, #132; test por CDP.

## [2026-07-06] — Líneas blancas invisibles otra vez, en el proyecto nuevo
**Categoría:** visual
**Síntoma:** Al rehacer desde cero, las líneas del árbol volvían a salir blancas y no se veían en tema claro.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** La librería pinta el trazo con `stroke="#fff"` como ATRIBUTO de presentación (prioridad mínima en CSS). No hay API de color de líneas.
**Cómo se cazó:** usuario
**Arreglo aplicado:** Una regla CSS sobre la clase oficial `.link`, que gana al atributo sin `!important`, sin JS y sin `setInterval`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Los atributos de presentación SVG pierden contra CSS; saberlo ahorra un `setInterval`.
**Traza:** #132, #134, #135; `.link`, `stroke="#fff"`.

## [2026-07-06] — Claude Code se adelantó e implementó el desplegable de "vincular existente"
**Categoría:** rompe
**Síntoma:** Se le pidió **investigar** si `setLinkExistingRelConfig` era viable; no solo lo investigó, lo implementó, y el desplegable apareció metido en el formulario de edición, donde el usuario no lo quería.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Se verificó a fondo por CDP (enlace bidireccional, no duplica personas, persiste, colocación correcta) y se dio por bueno: todo verde… sobre una funcionalidad que había que revertir entera.
**Causa raíz:** Ejecutar más allá de lo aprobado; el trato era investigar → decidir → implementar.
**Cómo se cazó:** usuario ("hiciste el estudio de si se podía hacer y no solo se hizo el estudio sino que se implementó")
**Arreglo aplicado:** Revertir `setLinkExistingRelConfig` y su CSS; anotar el pendiente. Más tarde se descartó la funcionalidad entera.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Verificar impecablemente algo que nadie pidió sigue siendo trabajo que hay que tirar.
**Traza:** #142–#158, #192, #193, #205, #206; `setLinkExistingRelConfig`, `.f3-link-existing-relative`, `PENDIENTES.md`.

## [2026-07-06] — Los huecos "Añadir X" no desaparecían tras añadir a alguien
**Categoría:** rompe
**Síntoma:** Tras añadir un hijo, seguían abiertos todos los huecos de los demás parentescos alrededor de la persona; el árbol no volvía a su vista limpia.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El paso de "añadir familiar" se había verificado y aprobado (#142: "Añadir funciona y persiste"), sin mirar el estado de la interfaz después.
**Causa raíz:** `postSubmitHandler` mantiene el "modo añadir" activo a propósito (`openWithoutRelCancel`, para encadenar altas).
**Cómo se cazó:** usuario (captura)
**Arreglo aplicado:** En `setOnChange`, si `isAddingRelative()`, salir del modo con el método oficial `addRelativeInstance.onCancel()` (que hace el `cleanUp()` de los placeholders) y redibujar. Nada de ocultar huecos a mano.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Verificar el dato guardado no es verificar el estado de la interfaz tras guardarlo.
**Traza:** #150, #158, #194, #195; `postSubmitHandler`, `setOnChange`, `isAddingRelative`, `addRelativeInstance.onCancel()`, `cleanUp()`.

## [2026-07-06] — Los huecos "Añadir X" salían negros sobre el tema claro
**Categoría:** visual
**Síntoma:** Recuadros oscuros de "Añadir padre/madre/cónyuge/hijo/hija" sobre el fondo crema.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Igual que arriba: el paso de añadir familiares se había aprobado sin mirar los huecos en tema claro.
**Causa raíz:** Los huecos son tarjetas placeholder que la librería pinta con `.card-inner { background-color: var(--background-color) }`, variable propia y oscura por defecto.
**Cómo se cazó:** usuario (captura)
**Arreglo aplicado:** Redefinir `--background-color` por tema dentro de `#FamilyChart`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Cada elemento nuevo que aparece en pantalla hay que verlo en los dos temas, siempre.
**Traza:** #150, #158, #194, #195; `.card-inner`, `--background-color`, `#FamilyChart`.

## [2026-07-06] — La pareja separada que seguía casada
**Categoría:** carencia
**Síntoma:** No estaba contemplado el caso de dos progenitores separados que comparten hijo: no había forma de quitar el vínculo de pareja sin tocar la paternidad.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** Modelo de relaciones incompleto; además, la vía nativa `removeRelative` obliga a reasignar los hijos a un solo progenitor, así que no servía.
**Cómo se cazó:** usuario (planteó el caso Iván/Mónica/Alex)
**Arreglo aplicado:** Botón "Separar" en la sección Pareja de la ficha → `separarPareja()`: vacía solo `spouses` de ambos, mantiene `parents` de los hijos, redibuja y registra en el historial (deshacible).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Los casos "raros" de la vida real (divorcios, medios hermanos, adopciones) son requisitos, no excepciones.
**Traza:** #159–#162, #200, #201; `separarPareja()`, `removeRelative`, `setLinkBreak`, `spouses`, `parents`.

## [2026-07-06] — La foto solo se podía pegar como URL
**Categoría:** carencia
**Síntoma:** El campo Foto del formulario nativo solo aceptaba una dirección de imagen; no se podía subir un archivo desde el ordenador, que era lo que el usuario necesitaba.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** `setFields` solo admite `text`, `textarea`, `select`, `rel_reference` y el sexo especial. No hay soporte nativo de subida de archivo.
**Cómo se cazó:** usuario
**Arreglo aplicado:** Controles propios inyectados por el hook oficial `setOnFormCreation`: vista previa + "Subir imagen" + "Quitar"; `reducirImagen()` escala a 256px y guarda dataURL JPEG en `avatar`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Cuando la librería no cubre un tipo de campo, el hook de creación del formulario es donde se inyecta el control propio.
**Traza:** #163, #196, #197; `setFields`, `setOnFormCreation`, `reducirImagen()`, `avatar`.

## [2026-07-06] — Solo se podía poner el año, no la fecha exacta
**Categoría:** carencia
**Síntoma:** No había forma de registrar fechas de nacimiento/fallecimiento completas, solo el año.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** `setFields` no soporta selector de fecha ni campos condicionales.
**Cómo se cazó:** usuario
**Arreglo aplicado:** Toggle "Año / Fecha exacta" inyectado con `setOnFormCreation`; el formato codifica el modo ("AAAA" vs "AAAA-MM-DD"), retrocompatible con los datos existentes.
**Commit:** NO CONSTA
**Ley que sale de aquí:** El formato del dato debe llevar dentro su propia precisión; si no, luego no sabes si "1950" es un año o una fecha perdida.
**Traza:** #165, #169, #196, #197; `setOnFormCreation`, `parseFechaPersona`.

## [2026-07-06] — Al pasar a círculos se perdió la navegación del árbol
**Categoría:** rompe
**Síntoma:** Tras personalizar las tarjetas como círculos, desaparecieron los "puntitos" que permitían adentrarse en una rama y volver atrás. No había forma de navegar.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Las tarjetas circulares se verificaron y aprobaron en las cuatro combinaciones (claro/oscuro × con foto/sin foto), sin que nadie notara que la navegación había desaparecido.
**Causa raíz:** El mini-árbol viene desactivado en `CardHtml` (está activado en `CardSvg`); al cambiar de tipo de tarjeta se perdió.
**Cómo se cazó:** usuario ("en las tarjetas se podía porque estaba visible como dos puntos pero en los círculos no")
**Arreglo aplicado:** `setMiniTree(true)` + `setOnCardUpdate` para que el clic del mini-árbol solo recentre (el círculo abre la ficha).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Cambiar de componente de la librería te hace heredar SUS defaults, no los del anterior. Compara qué perdiste.
**Traza:** #171, #173, #174, #200, #201; `setMiniTree`, `CardHtml`, `CardSvg`, `setOnCardUpdate`.

## [2026-07-06] — Al rehacer de cero desapareció la tarjeta de título
**Categoría:** carencia
**Síntoma:** El cuadro de título arriba a la izquierda (arbolito + título + subtítulo, al contraste del tema) no existía en la versión nueva.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** Reescritura desde cero sin inventario de lo que ya existía y funcionaba en la versión vieja.
**Cómo se cazó:** usuario ("recuerdas que teníamos un título arriba a la izquierda que desapareció")
**Arreglo aplicado:** Recuperada con variables `--title-bg/--title-fg/--title-sub` invertidas por tema; después, editable con lápiz solo en modo edición.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Empezar de cero exige una lista de lo que había, o se pierde en silencio.
**Traza:** #175, #176, #182, #183, #188–#191; `--title-bg`, `--title-fg`, `arbolMeta`.

## [2026-07-06] — Al rehacer de cero desapareció la botonera y el modo lectura/edición
**Categoría:** carencia
**Síntoma:** No estaban "Editar árbol / Finalizar edición", "Exportar" ni "Importar", ni el comportamiento de modo lectura vs edición.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** Misma reescritura desde cero sin inventario.
**Cómo se cazó:** usuario
**Arreglo aplicado:** Botonera recuperada; modo lectura/edición con la vía oficial `f3Edit.setNoEdit()` / `setEdit()`; Exportar con `exportData()`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** El "empezar limpio" borra funcionalidad si no la documentas primero.
**Traza:** #177, #178, #182, #183; `setNoEdit()`, `setEdit()`, `exportData()`, `.solo-edicion`.

## [2026-07-06] — La botonera dejaba inclicable el botón de añadir familiar
**Categoría:** rompe
**Síntoma:** La barra de botones tapaba el muñequito de "añadir familiar" del formulario: no se podía pulsar.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (lo cazó Claude Code antes de entregar)
**Causa raíz:** Solapamiento de la barra superior derecha con el panel del editor.
**Cómo se cazó:** test (`elementFromPoint` confirmó que quedaba inclicable)
**Arreglo aplicado:** v1: CSS `body:has(.f3-form-cont.opened) .toolbar { right: 366px }`. v2: mover la botonera abajo a la izquierda, eliminando ese CSS.
**Commit:** NO CONSTA
**Ley que sale de aquí:** "Se ve" no es "se puede pulsar": comprueba la clicabilidad, no solo la visibilidad.
**Traza:** #183–#187; `.toolbar`, `.f3-form-cont.opened`, `elementFromPoint`.

## [2026-07-06] — El sexo aparecía como "M" en la ficha
**Categoría:** datos
**Síntoma:** En la ficha de lectura el campo Sexo mostraba el valor crudo del store ("M") en vez de "Hombre"/"Mujer".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** Se pintaba el valor interno sin traducir en el render de solo lectura.
**Cómo se cazó:** Claude Code, durante su propia verificación
**Arreglo aplicado:** Traducción en el hook `setOnFormCreation`, apuntando solo al campo Sexo.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Nunca enseñes al usuario el valor interno de tu modelo de datos.
**Traza:** #183; `setOnFormCreation`.

## [2026-07-06] — El apaño CSS que apartaba la botonera hubo que tirarlo
**Categoría:** rompe
**Síntoma:** La regla `body:has(.f3-form-cont.opened) .toolbar { right: 366px }` y su `transition` quedaron como código muerto un paso después.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La regla se verificó y se aprobó (la botonera se apartaba correctamente).
**Causa raíz:** Se parcheó el síntoma (dos elementos peleando por la misma esquina) en vez de resolver la disposición.
**Cómo se cazó:** usuario ("¿y si pasamos esa botonera abajo a la izquierda junto al tema?")
**Arreglo aplicado:** Mover la botonera; eliminar la regla y la posición suelta de `#btnTema`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si dos elementos se pisan, muévelos; no inventes reglas que los esquiven.
**Traza:** #183–#187; `.toolbar`, `#btnTema`.

## [2026-07-06] — Ordenar las parejas por edad era imposible: la librería lo fija por sexo
**Categoría:** carencia
**Síntoma:** El requisito "más joven a la izquierda, más mayor a la derecha en todos los niveles" no se podía cumplir para las parejas.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (se investigó ANTES de implementar, a petición del usuario)
**Causa raíz:** family-chart tiene el lado hardcodeado por sexo en dos sitios: `setupSpouses` (`const side = d.data.data.gender === "M" ? -1 : 1`) y `hierarchyGetterParents` (`if (p1.gender === "F") parents.reverse()`). `setSortSpousesFunction` no sirve para esto.
**Cómo se cazó:** test / lectura de la fuente por Claude Code
**Arreglo aplicado:** Se implementó solo la ordenación de HERMANOS con `setSortChildrenFunction` (vía oficial) y se renunció a la de parejas, que habría exigido parchear la librería o recolocar tarjetas por JS.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Investigar antes de prometer evita prometer lo imposible. Este es el único caso del día en que se hizo así, y salió bien.
**Traza:** #209–#221; `setSortChildrenFunction`, `setSortSpousesFunction`, `setupSpouses`, `hierarchyGetterParents`, `comparaHermanosPorEdad`.

## [2026-07-06] — Las flechas de deshacer/rehacer, sueltas y sin contexto
**Categoría:** visual
**Síntoma:** Dos flechas aparecían arriba a la izquierda, sin integrar, sin que se entendiera qué eran, y también en modo lectura donde no tienen sentido.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** Controles nativos de la librería (`.f3-history-controls` dentro de `.f3-nav-cont`) que se pintaron solos y nunca se integraron.
**Cómo se cazó:** usuario (captura)
**Arreglo aplicado:** Reposicionadas como pastilla agrupada abajo a la izquierda, solo visibles con `body.editando`, con tooltips y color siguiendo el tema. Solo CSS, sin tocar la lógica nativa.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Todo lo que la librería pinta sin que se lo pidas es interfaz tuya: intégralo o quítalo.
**Traza:** #239, #241, #263, #264; `.f3-history-controls`, `.f3-back-button`, `.f3-forward-button`, `.f3-nav-cont`.

## [2026-07-06] — La ficha de "ver datos" era una lista cruda con campos vacíos
**Categoría:** visual
**Síntoma:** Al pulsar una persona en modo lectura salía una lista de campos, incluidos los vacíos (Nacimiento, Lugar, Profesión, Notas, Foto…) ocupando espacio en blanco. "Se ve lamentable."
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** Es el render por defecto de la librería (`infoField()`), que pinta todos los campos existan o no.
**Cómo se cazó:** usuario (captura)
**Arreglo aplicado:** En `setOnFormCreation`, detectar modo lectura (`form.classList.contains('non-editable')`), ocultar `.f3-info-field` y pintar `fichaLecturaBonita()` solo con campos con contenido.
**Commit:** NO CONSTA
**Ley que sale de aquí:** El render por defecto de una librería no es un diseño: es un placeholder.
**Traza:** #239, #241, #263, #265, #266; `infoField()`, `.f3-info-field`, `fichaLecturaBonita()`, `formatFecha()`.

## [2026-07-06] — La ficha rediseñada seguía siendo un panel lateral desangelado
**Categoría:** visual
**Síntoma:** Tras rehacer la ficha de lectura, el contenido era correcto pero el formato seguía siendo un cajón lateral pegado al borde, de arriba abajo. El usuario la rechazó.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Claude Code la verificó por CDP en las cuatro combinaciones (muchos datos / pocos datos × claro / oscuro), con tabla de resultados en verde, y la dio por terminada.
**Causa raíz:** El encargo definía QUÉ datos mostrar pero no CÓMO presentarlos; se heredó el contenedor lateral del editor.
**Cómo se cazó:** usuario
**Arreglo aplicado:** Convertirla en modal flotante centrado con backdrop en escritorio y a pantalla completa en móvil.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Verificar que los datos correctos están en pantalla no dice nada sobre si la pantalla es aceptable.
**Traza:** #266–#272, #275; `fichaLecturaBonita()`, `body.ficha-modal`.

## [2026-07-06] — "Hazla bonita" no es una especificación
**Categoría:** visual
**Síntoma:** El encargo decía "bonita" y "bien diseñada"; el resultado salió correcto pero soso, y hubo que rehacerlo con dirección de diseño explícita (jerarquía tipográfica, espaciado, tratamiento de etiquetas vs valores, agrupación).
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** Instrucción vaga en el prompt; se pidió una cualidad subjetiva sin criterios.
**Cómo se cazó:** usuario ("le has pedido en el prompt que lo haga con más gracia, el tamaño de fuentes, distribución y todo eso?")
**Arreglo aplicado:** Prompt reescrito con tamaños concretos, jerarquía, espaciado, agrupación por bloques e iconos.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si no puedes verificar "bonito", tampoco puedes pedirlo: convierte el diseño en reglas comprobables.
**Traza:** #272–#274.

## [2026-07-06] — El selector `:has()` no reevaluaba al cambiar de viewport
**Categoría:** rompe
**Síntoma:** La ficha modal no cambiaba de forma fiable entre escritorio y móvil al redimensionar.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (lo cazó Claude Code antes de entregar)
**Causa raíz:** `:has()` no se reevaluaba de forma fiable al cambiar el viewport.
**Cómo se cazó:** test (CDP, por Claude Code)
**Arreglo aplicado:** Clase `body.ficha-modal` sincronizada con un `MutationObserver` (event-driven, sin polling).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un selector CSS reactivo no siempre reevalúa lo que crees; si es crítico, sincronízalo por evento.
**Traza:** #275; `:has()`, `body.ficha-modal`, `MutationObserver`.

## [2026-07-06] — El cierre por clic en el fondo no funcionaba
**Categoría:** rompe
**Síntoma:** Pulsar el fondo oscurecido no cerraba la ficha modal.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (cazado por Claude Code)
**Causa raíz:** El handler se ataba a un panel que en ese momento aún no contenía el formulario.
**Cómo se cazó:** test (CDP)
**Arreglo aplicado:** Reatar el handler al elemento correcto.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un handler atado a un elemento que aún no existe no dispara; átalo cuando el elemento esté montado.
**Traza:** #275; handler de cierre por fondo.

## [2026-07-06] — La X de cerrar salía a la izquierda
**Categoría:** visual
**Síntoma:** El botón de cerrar del modal aparecía a la izquierda en vez de arriba a la derecha.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (cazado por Claude Code)
**Causa raíz:** La librería fija `left:10px` en ese botón.
**Cómo se cazó:** test (CDP)
**Arreglo aplicado:** Anular esa posición.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Las posiciones fijas de la librería hay que anularlas explícitamente, no confiar en que "quedará bien".
**Traza:** #275; botón de cierre del formulario de la librería.

## [2026-07-06] — El árbol se desplazaba solo al abrir una ficha
**Categoría:** rompe
**Síntoma:** Al pulsar una persona para ver sus datos, el árbol entero se movía a la izquierda sin motivo.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La apertura de la ficha se había verificado y aprobado varias veces (contenido, temas, tamaños) sin que nadie mirara si el árbol se movía.
**Causa raíz:** `setCardClickOpen` ejecuta `onCardClickDefault` = `updateMainId` + `updateTree()`, y `updateTree()` reencuadra por defecto con `tree_position:'fit'`.
**Cómo se cazó:** usuario
**Arreglo aplicado:** Sustituir por `f3Card.setOnCardClick((e,d) => f3Edit.open(d.data))`: abre la ficha sin recentrar. Verificado con `updateTree` llamado 0 veces y misma X de la tarjeta (397→397). El recentrado sigue disponible en el mini-árbol.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un método de conveniencia de la librería puede traer efectos que no pediste; mira qué hace por dentro antes de usarlo.
**Traza:** #277, #279–#281; `setCardClickOpen`, `onCardClickDefault`, `updateMainId`, `updateTree({tree_position:'fit'})`, `setOnCardClick`.

## [2026-07-06] — La ficha modal seguía sin jerarquía visual
**Categoría:** visual
**Síntoma:** Ya como modal, seguía "sosa": la edad demasiado pequeña, los tres bloques indistinguibles entre sí (sin títulos ni iconos), y en Familia solo salían padres/pareja/hijos, faltando nietos, bisnietos, sobrinos y primos.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Claude Code verificó por CDP (card 400px, centrada, X derecha, cierre por X y por fondo, móvil 375×720, solo campos con datos) y lo dio por completo.
**Causa raíz:** Se cumplieron los requisitos literales de comportamiento sin criterio de diseño ni de completitud de los parentescos.
**Cómo se cazó:** usuario
**Arreglo aplicado:** Tres bloques con cabecera (icono + título), edad destacada, y cálculo de todos los parentescos deducibles (nietos = hijos de hijos; bisnietos = hijos de nietos; sobrinos = hijos de hermanos; primos = hijos de hermanos de los padres), con deduplicación.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Cumplir la letra del requisito no es cumplirlo.
**Traza:** #275, #277–#280; `fichaLecturaBonita()`.

## [2026-07-06] — La tarjeta de título seguía saliendo en móvil pese a lo acordado
**Categoría:** carencia
**Síntoma:** Se había acordado ocultar el cuadro "Nuestro árbol" en móvil y tablet, y seguía apareciendo.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El paso de la ficha modal se verificó en móvil (375px) y se aprobó, sin detectar que el título seguía ahí.
**Causa raíz:** El acuerdo se anotó "para el bloque F3" y quedó fuera de la verificación del paso en curso, que sí incluía pantallas de móvil.
**Cómo se cazó:** usuario
**Arreglo aplicado:** `@media (max-width:768px)` ocultando la tarjeta de título; verificado (`titlecard_visible: false` en móvil).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si estás verificando en móvil, verifica TODO lo pendiente de móvil que ya sabes que está mal.
**Traza:** #268, #269, #277, #279, #280; media query de la tarjeta de título.

## [2026-07-06] — Iconos de sección con colores propios que no pegaban
**Categoría:** visual
**Síntoma:** Los iconos de los tres bloques salieron en verde, ámbar y violeta, chocando con el resto de la ficha, que va toda en el color del sexo.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Se verificaron los tres bloques con icono+título (3/3 ✓) y se dio por bueno.
**Causa raíz:** Se dejó libertad de color al icono ("elige colores que queden bien") sin fijar el esquema de acento del componente.
**Cómo se cazó:** usuario
**Arreglo aplicado:** `fill: currentColor` en los SVG, para que hereden el color por sexo del título de sección.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un componente tiene UN color de acento; todo lo demás lo hereda.
**Traza:** #285, #287; iconos SVG de sección.

## [2026-07-06] — Divisorias en gris neutro rompiendo la coherencia de color
**Categoría:** visual
**Síntoma:** Las líneas que separan las secciones de la ficha eran grises, ajenas al azul/rosa del resto.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Las divisorias se verificaron como "separación clara entre bloques" y se aprobaron.
**Causa raíz:** Elemento decorativo no incluido en el esquema de color por sexo.
**Cómo se cazó:** usuario
**Arreglo aplicado:** `border-top-color: color-mix(in srgb, var(--male/--female) 28%, transparent)`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Los elementos decorativos también entran en el esquema de color; el gris neutro delata que se olvidaron.
**Traza:** #286, #287; divisorias de la ficha de lectura.

## [2026-07-06] — Se borraba una persona sin preguntar
**Categoría:** carencia
**Síntoma:** El botón Eliminar del formulario borraba directamente, sin confirmación ni aviso, con riesgo de cargarse una rama entera de un clic.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (el borrado nunca se cuestionó hasta que el asistente hizo de "abogado del diablo")
**Causa raíz:** No se contempló la confirmación destructiva al montar el editor.
**Cómo se cazó:** ojo humano (repaso de "qué se nos puede olvidar") + usuario, que lo eligió de la lista
**Arreglo aplicado:** Hook oficial `f3Edit.setOnDelete(datum, borrar, postSubmit)` con diálogo propio; aviso reforzado si hay descendencia, usando el predictor oficial `f3.handlers.checkIfRelativesConnectedWithoutPerson` para decir la verdad de lo que va a pasar (los hijos siguen por el otro progenitor, o la persona queda como tarjeta anónima para no soltar la rama).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Toda acción destructiva necesita confirmación, y la confirmación debe decir la verdad de lo que va a pasar, no una fórmula genérica.
**Traza:** #257, #258, #288, #289; `setOnDelete`, `checkIfRelativesConnectedWithoutPerson`, `closeForm()`.

## [2026-07-06] — El buscador no encontraba por año
**Categoría:** carencia
**Síntoma:** El buscador mostraba los años en los resultados pero no permitía buscar "1950" y encontrar a quien nació o falleció ese año.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El buscador se verificó por CDP (nombre, apellido, acentos, centrado exacto, halo) y se presentó como cerrado.
**Causa raíz:** El requisito original solo hablaba de nombre; nadie contempló las fechas.
**Cómo se cazó:** usuario ("¿es capaz de buscar por año o fechas?")
**Arreglo aplicado:** Detectar 4 dígitos → modo año; comparar con `anioDe()`, que extrae el año de "AAAA" y de "AAAA-MM-DD"; mostrar el motivo ("Nació en 1950" / "Falleció en 1950").
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un requisito descrito en términos de un caso ("buscar por nombre") deja fuera los demás ejes hasta que alguien pregunta.
**Traza:** #290–#295; `anioDe()`, `store.updateMainId`, `updateTree({tree_position:'main_to_middle'})`.

## [2026-07-06] — Al exportar, las líneas del árbol desaparecían
**Categoría:** visual
**Síntoma:** En el PNG/PDF exportado con fondo claro, las líneas de unión entre personas salían blancas, casi invisibles, aunque en pantalla se veían negras.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La exportación se verificó por CDP en ambos temas con tabla completa en verde: "PNG descargado (firma válida) ✅ / PDF (firma %PDF-) ✅ / Árbol completo ✅ / Foto exportada ✅ / Ambos temas ✅". Se comprobó que el archivo EXISTÍA y era válido; nadie miró su contenido.
**Causa raíz:** La librería pinta cada línea con `.attr("stroke", "#fff")` (atributo de presentación). En pantalla el CSS `.link { stroke: var(--line) }` lo tapa, pero `html-to-image` no aplica las reglas de la hoja de estilos a los elementos SVG al rasterizar, así que tomaba el `#fff` del atributo.
**Cómo se cazó:** usuario (miró el archivo exportado)
**Arreglo aplicado:** Antes de capturar, fijar `style.stroke` en línea en cada `.link` con el color leído de `getComputedStyle(link).stroke` (el estilo en línea gana al atributo y sí lo captura la herramienta), y restaurarlo en el `finally`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Verificar que un archivo se genera no es verificar que el archivo está bien. Abre el archivo.
**Traza:** #296–#301; `html-to-image`, `jsPDF`, `.links_view .link`, `getComputedStyle`, `store.getTree().dim`.

## [2026-07-06] — Las fotos en base64 dentro del JSON no escalaban
**Categoría:** datos
**Síntoma:** Las fotos viajaban incrustadas en base64 dentro del `datos.json`, lo que engordaría el archivo hasta hacerlo pesado de cargar en cada arranque.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Se entregó como solución en #14 ("las fotos van dentro del JSON reducidas a 256 px, lo cual está bien para familia normal") y se construyó todo el bloque C sobre esa base.
**Causa raíz:** Se eligió la opción sencilla sin preguntar por el volumen previsto ni por la existencia de base de datos en el hosting.
**Cómo se cazó:** usuario (preguntó dónde se ubicarían las fotos en el servidor y qué protección tendrían)
**Arreglo aplicado:** Decisión de guardar las fotos como archivos separados en el servidor, con el JSON guardando solo la referencia; y con protección contra acceso por URL directa.
**Commit:** NO CONSTA
**Ley que sale de aquí:** "Simple ahora" que no escala es deuda con fecha de vencimiento; pregunta el volumen antes de elegir el formato.
**Traza:** #14, #242–#245; `avatar`, `reducirImagen()`, `datos.json`.

## [2026-07-06] — Todo el plan JSON + PHP se descartó tras haberlo construido
**Categoría:** rompe
**Síntoma:** Se diseñó, se escribió y se entregó un paquete completo (`datos.json`, `guardar.php` con contraseña y backup, `LEEME.md`), y todo el trabajo posterior se planificó sobre él… hasta descubrir que el usuario quería acabar en base de datos, con lo que se tiró el plan entero.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ En #14 se declaró: "su sintaxis y la lógica del HTML están validadas; en tu servidor, que sí tiene PHP, funcionará" (sin poder ejecutar PHP en el entorno). El plan se dio por bueno y se repitió en al menos cuatro prompts posteriores.
**Causa raíz:** Nunca se preguntó si el hosting tenía base de datos ni cuál era la meta a medio plazo (usuarios, fotos, seguridad), pese a que el usuario mencionó el hosting desde el principio.
**Cómo se cazó:** usuario ("¿está preparado el sistema para poderlo montar contra una BD?")
**Arreglo aplicado:** Decisión de ir directo a MySQL/MariaDB para datos, fotos y usuarios, saltándose la fase JSON.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Pregunta la meta final antes de diseñar la arquitectura intermedia; una pregunta de 10 segundos habría ahorrado un plan entero.
**Traza:** #13, #14, #147, #206, #246–#251; `datos.json`, `guardar.php`, `LEEME.md`.

## [2026-07-06] — Un login en el navegador no es un login
**Categoría:** seguridad
**Síntoma:** El usuario planteó una pantalla de acceso (nombre + fecha + clave) que, tal como estaba diseñada la app (todo en el HTML/JS), cualquiera podría saltarse mirando el código.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (se avisó antes de implementarlo)
**Causa raíz:** Toda la aplicación era front-end puro; no había ninguna capa de servidor que validara nada.
**Cómo se cazó:** ojo humano (el asistente lo advirtió al recibir el requisito)
**Arreglo aplicado:** Se decidió que la validación y las claves vivan como variables del PHP en el servidor, que solo devuelve los datos tras validar; el nivel de acceso (lectura/edición) lo determina el servidor, no el navegador.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si el secreto llega al navegador, no es un secreto.
**Traza:** #229–#236; PHP de servidor.

## [2026-07-06] — Los datos del árbol quedaban accesibles por URL directa
**Categoría:** seguridad
**Síntoma:** En el diseño original, cualquiera podía pedir el fichero de datos por su URL y verlo entero (fechas, notas, fotos de la familia) sin pasar por ninguna clave; lo mismo ocurriría con las fotos si se guardaban como archivos sueltos en una carpeta pública. La única protección prevista era una contraseña para ESCRIBIR, ninguna para LEER.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ En #14 se entregó el paquete diciendo "Cualquiera puede ver el árbol, pero solo quien tenga la contraseña puede guardar", presentándolo como el comportamiento correcto.
**Causa raíz:** Se protegió la escritura y se olvidó que los datos son personales y de terceros (familiares vivos).
**Cómo se cazó:** usuario (al plantear el login y preguntar por la protección de las fotos)
**Arreglo aplicado:** Decisión de que los datos y las fotos solo se sirvan a través del PHP tras validar el acceso, nunca por URL directa.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Datos personales de terceros: protege la LECTURA, no solo la escritura.
**Traza:** #14, #230, #234, #243, #245; `datos.json`, carpeta de fotos, PHP de servidor.

---

# 2026-07-07 — Reorganización, arranque del backend y los primeros bugs de datos

## [2026-07-07] — El servidor de pruebas se había caído a mitad de la verificación (y F3 se aprobó igual)
**Categoría:** despliegue
**Síntoma:** Claude Code entrega la verificación del bloque F3 (móvil/tablet) con toda la tabla en verde y, al final, admite que "durante la verificación se me había caído el servidor local (python -m http.server); lo relancé".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La tabla completa de verificación de F3 (dispositivo-lectura, sin botón Editar, título oculto, buscador, gestos, ambos temas, móvil/tablet en vertical y horizontal) se presentó en verde pese a que el servidor local se había caído en algún punto del proceso — y el asistente la **aprobó** en #305 ("Aprobado, F3 completo… Con esto has cerrado todo el frontend") sin que constara que la tabla se repitiera con el servidor vivo.
**Causa raíz:** NO CONSTA (no se explica por qué se cayó el servidor ni qué comprobaciones concretas se hicieron con él caído).
**Cómo se cazó:** casualidad (lo confiesa el propio Claude Code al final del informe; nadie lo persiguió).
**Arreglo aplicado:** Relanzar el servidor. Verificado en el volcado (#304, #305 y siguientes): la tabla de F3 NO se repitió con el servidor vivo; el bloque se dio por cerrado con esa única verificación.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Una tabla en verde no vale nada si no consta que el entorno estuviera vivo durante toda la prueba. Y si aparece esa nota, la tabla se repite antes de aprobar — no se aprueba con la nota dentro.
**Traza:** #304, #305; `python -m http.server`, http://localhost:8000.

## [2026-07-07] — Todo el proyecto seguía siendo un único index.html
**Categoría:** carencia
**Síntoma:** El usuario pregunta "ahora está todo en un único archivo sin el CSS o el JS separado, ¿verdad?" y descubre que sí: HTML, CSS y JS todo junto, justo cuando se iba a empezar el backend encima.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Todos los bloques de frontend (A–E, F1, F2, F3) se habían dado por "completos y verificados" sin que nadie señalara que la estructura del proyecto era insostenible para lo que venía.
**Causa raíz:** Se construyó todo el frontend en modo "archivo único" para ir rápido y no se planificó el punto de separación.
**Cómo se cazó:** usuario
**Arreglo aplicado:** Se antepone un paso de reorganización (PASO 2) antes del backend: CSS a `public/assets/css/estilos.css`, JS a 10 módulos en `public/assets/js/`, librerías a local, `public/` como document root.
**Commit:** NO CONSTA
**Ley que sale de aquí:** "Funciona" no es lo mismo que "es construible encima"; la estructura hay que decidirla antes de que crezca.
**Traza:** #306, #307, #309, #313, #410, #415; `index.html`, `public/index.html`, `public/assets/`, `index-monolitico-respaldo.html`.

## [2026-07-07] — Archivos viejos, backups y notas sin política de repositorio
**Categoría:** carencia
**Síntoma:** El usuario avisa de que hay "archivos viejos y doc de claude e historial" sueltos en la carpeta y que nadie ha decidido qué se sube a GitHub, qué se archiva y qué se tira.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** Se acumularon versiones antiguas (`index.viejo.html`, backups, `PENDIENTES.md`, `NOTAS-LIBRERIA.md`) sin criterio, y el repositorio iba a ser público de portafolio.
**Cómo se cazó:** usuario
**Arreglo aplicado:** Se mete en el plan de estructura profesional que Claude Code proponga qué se conserva/archiva/descarta. Queda `index.viejo.html` sin borrar todavía.
**Commit:** NO CONSTA
**Ley que sale de aquí:** La basura del proceso hay que decidirla mientras se genera, no cuando el repo ya es público.
**Traza:** #310, #311, #313, #419; `index.viejo.html`, `PENDIENTES.md`, `NOTAS-LIBRERIA.md`, `ESTADO.md`.

## [2026-07-07] — El "archivo maestro de conexiones" que habría filtrado todo de golpe
**Categoría:** seguridad
**Síntoma:** El usuario pide que en la raíz del portafolio (`01_PROYECTOS`) haya "el archivo de conexiones, de total de bases de datos y de lo que haga falta".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (no llegó a implementarse)
**Causa raíz:** Confusión entre "índice/documentación del portafolio" y "almacén central de credenciales".
**Cómo se cazó:** ojo humano (el asistente lo corta antes de que se construya)
**Arreglo aplicado:** Se decide que en la raíz solo va índice/documentación; las credenciales viven aisladas en cada proyecto y en el servidor, nunca centralizadas ni en GitHub.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un fichero cómodo que junta todas las credenciales es un único punto de fuga total.
**Traza:** #350–#353; raíz `01_PROYECTOS`.

## [2026-07-07] — "Nombre de BD enrevesado para evitar hackeos"
**Categoría:** seguridad
**Síntoma:** El usuario asume que poner un nombre raro a la base de datos de producción es una medida de seguridad.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** Creencia falsa (seguridad por oscuridad) sobre dónde reside la protección real.
**Cómo se cazó:** ojo humano (el asistente lo corrige al aprobar el Paso 3)
**Arreglo aplicado:** Se explica que la seguridad está en contraseña fuerte, credenciales fuera de GitHub, solo `public/` accesible y consultas preparadas; el nombre se deja simple en local.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Esconder el nombre no protege nada; lo que protege es la clave, el aislamiento y las consultas preparadas.
**Traza:** #421, #422; `config/config.php`, `config/config.example.php`.

## [2026-07-07] — No existía .gitignore y el patrón no cubría el fichero de credenciales real
**Categoría:** seguridad
**Síntoma:** Al preparar el Paso 3, Claude Code detecta que el `.gitignore` (que ni siquiera existía hasta poco antes) contemplaba `config.local.php` pero quizá no `config/config.php`, que es donde iban a vivir las credenciales reales.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El mensaje #392 dio por "Hecho y verificado ✅" la creación del `.gitignore`; aun así hubo que volver a comprobar expresamente que el patrón cubría el fichero de configuración real.
**Causa raíz:** El `.gitignore` se creó de memoria, con nombres de fichero que no coincidían con los que luego se usaron de verdad.
**Cómo se cazó:** ojo humano (el propio Claude Code, al describir el Paso 3)
**Arreglo aplicado:** Se exige confirmar/añadir `config/config.php` al `.gitignore` antes de escribir credenciales en él.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un `.gitignore` solo protege si sus patrones coinciden con los nombres reales; hay que verificarlo contra el fichero, no contra la intención.
**Traza:** #392, #419, #422; `.gitignore`, `config/config.php`, `config.local.php`, `.claude/settings.local.json`.

## [2026-07-07] — La carpeta que no se podía renombrar y la decisión que hubo que revertir
**Categoría:** despliegue
**Síntoma:** Se decide pasar los prefijos de carpeta a tres dígitos (`00_` → `000_`) y el renombrado falla dos veces: "El proceso no puede obtener acceso al archivo porque está siendo utilizado en otro proceso".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El primer intento se dio por preparado ("paro el servidor, renombro, arranco"); tras pararlo y confirmar que no quedaban procesos Python/Node, el renombrado siguió fallando.
**Causa raíz:** VS Code tenía la carpeta abierta como workspace (watchers) y las propias terminales del agente tenían esa carpeta como cwd (el entorno las resetea a ella tras cada comando): el agente bloqueaba desde dentro la carpeta que quería renombrar.
**Cómo se cazó:** test (falla el comando) + diagnóstico de procesos
**Arreglo aplicado:** Ninguno técnico. Se **revierte la decisión**: se aparca el renombrado hasta la fase de GitHub y se blinda el contexto en `ESTADO-Y-DECISIONES.md` por si se perdía el historial.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un agente no puede serrar la rama en la que está sentado: no puede renombrar su propio directorio de trabajo.
**Traza:** #358–#365, #370–#383; `Rename-Item`, `ESTADO-Y-DECISIONES.md`, `PENDIENTES.md`.

## [2026-07-07] — El asistente dio por hecho un renombrado que no había ocurrido
**Categoría:** aviso falso
**Síntoma:** El asistente saluda con "¿el renombrado funcionó y ahora tienes VS Code abierto sobre `000_GENEALOGIA`?" y prepara un prompt de post-renombrado; el usuario responde: "no he renombrado nada".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** El asistente infirió el estado del sistema a partir de un "vale ya he vuelto" del usuario, sin confirmarlo.
**Cómo se cazó:** usuario
**Arreglo aplicado:** Se rehace el prompt para reintentar el renombrado desde cero.
**Commit:** NO CONSTA
**Ley que sale de aquí:** "Ya he vuelto" no significa "ya lo he hecho": el estado del sistema se pregunta, no se deduce.
**Traza:** #374–#377.

## [2026-07-07] — El acceptEdits "hecho y verificado" que seguía preguntando 20 veces
**Categoría:** aviso falso
**Síntoma:** Tras configurar el modo `acceptEdits`, el usuario estalla: "No entiendo porque cojones sigo teniendo que darle a yes 20 veces cada vez que lanzo algo cuando se suponía que lo habíamos 'arreglado'".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El mensaje #392 declaró "Hecho y verificado. ✅ Modo `acceptEdits` permanente… JSON validado correctamente", y el asistente lo celebró ("con esto ya trabajarás mucho más fluido"). El fallo (seguir preguntando en cada comando) estaba vivo desde ese mismo instante.
**Causa raíz:** Doble: (a) `defaultMode` solo aplica a sesiones nuevas, y la sesión en curso siguió en Manual; (b) y sobre todo, `acceptEdits` **no auto-acepta comandos de terminal** (mysql, curl, php, node…), que era justo el 100% de lo que se estaba lanzando en la fase de backend. La solución elegida no atacaba el problema real.
**Cómo se cazó:** usuario (quejándose del síntoma persistente)
**Arreglo aplicado:** Se pasa a modo permisivo total (`bypassPermissions` en el settings local) y se cambia el modo en la sesión en curso desde el selector.
**Commit:** NO CONSTA
**Ley que sale de aquí:** "Configurado y validado el JSON" no es "el problema resuelto"; hay que verificar el síntoma del usuario, no el fichero.
**Traza:** #386–#392, #507, #508, #513, #514, #516, #517; `.claude/settings.local.json`, `permissions.defaultMode`, `acceptEdits`, `bypassPermissions`.

## [2026-07-07] — "Auto Mode es el equivalente a bypass permissions": falso
**Categoría:** aviso falso
**Síntoma:** La interfaz del usuario no ofrecía el modo `bypass permissions` (solo Manual, Edit Automatically, Plan Mode y Auto Mode), pese a que en el settings se había escrito `bypassPermissions`.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Claude Code afirmó en firme: "Auto Mode… Es el equivalente en tu interfaz a 'bypass permissions'. O sea: es el de cero preguntas… No hay conflicto real". El asistente lo dio por "resuelto del todo" y "tema zanjado".
**Causa raíz:** Se afirmó una equivalencia entre el nombre interno del setting y la etiqueta de la interfaz sin poder consultarla; la propia descripción del Auto Mode (visible después en pantalla) decía que "se detendrá para cualquier cosa arriesgada", es decir, NO es bypass.
**Cómo se cazó:** ojo humano (el usuario enseña la descripción del modo en pantalla y el asistente rectifica en #534)
**Arreglo aplicado:** Se rectifica la explicación (Auto Mode ≠ bypass; para en lo arriesgado) y se acepta ese comportamiento como el bueno.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si el agente no puede consultar su propio modo, no debe afirmarlo; "no hay conflicto real" dicho sin poder mirar es una invención.
**Traza:** #516–#522, #534; `.claude/settings.local.json`, `defaultMode: bypassPermissions`.

## [2026-07-07] — El entorno local no replicaba la base de datos de producción
**Categoría:** despliegue
**Síntoma:** Laragon instala MySQL 8.4.3, pero el hosting de producción usa MariaDB 10.5. Todo el backend se iba a construir y verificar sobre un motor distinto al de producción.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La página `prueba-php.php` salió "todo en verde" salvo la fila "El servidor es MariaDB", que salió en **rojo** y se decidió **ignorarla** por "esperada". Se normalizó un rojo.
**Causa raíz:** Laragon Full trae MySQL, no MariaDB, y cambiarlo tiene un paso delicado (reinicializar el directorio de datos).
**Cómo se cazó:** ojo humano (se ve la versión en la ventana de Laragon)
**Arreglo aplicado:** Se asume el riesgo (MySQL 8 es más estricto que MariaDB), se escribe el SQL evitando lo exclusivo de cada motor, y se promete validar el esquema contra la MariaDB real antes de desplegar (pendiente al cierre del volcado).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un rojo "esperado" en la verificación sigue siendo una divergencia entre local y producción; hay que apuntar cuándo se cierra, no solo por qué se ignora.
**Traza:** #403–#408; `public/prueba-php.php`, `db/esquema.sql`, Laragon.

## [2026-07-07] — Mojibake: los acentos se corrompían al cargar los datos por línea de comandos
**Categoría:** datos
**Síntoma:** Al restaurar el demo con `mysql < archivo`, los acentos se guardaban corruptos en la base de datos (Andrés, Joaquín…).
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El esquema se había verificado con collation `utf8mb4_unicode_ci` en las 5 tablas (#441, verificación por línea de comandos: "todas InnoDB + utf8mb4_unicode_ci") y el JSON del endpoint se dio por bueno diciendo expresamente "los **acentos salen bien** ('Joaquín', 'María')" (#451). La collation correcta no impedía la corrupción al cargar.
**Causa raíz:** Los ficheros `esquema.sql` y `datos-demo.sql` no declaraban `SET NAMES utf8mb4`, así que el cliente de línea de comandos cargaba con otra codificación.
**Cómo se cazó:** casualidad (Claude Code lo encuentra al restaurar el demo mientras construía el login)
**Arreglo aplicado:** Añadir `SET NAMES utf8mb4;` a `db/esquema.sql` y `db/datos-demo.sql`, para que la carga sea correcta con cualquier cliente.
**Commit:** NO CONSTA
**Ley que sale de aquí:** La collation de la tabla no protege del cliente: la codificación hay que fijarla también en el fichero que se carga.
**Traza:** #435, #509, #510; `db/esquema.sql`, `db/datos-demo.sql`.

## [2026-07-07] — Las fotos no se guardaban y nadie lo había notado
**Categoría:** datos
**Síntoma:** Al construir la escritura en BD se descubre que el campo `avatar` **se ignoraba a propósito** en el guardado: si el usuario ponía una foto y recargaba, la foto desaparecía.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El Paso 6 (escritura) se verificó "de punta a punta" con tabla de resultados (editar, crear+vínculo, separar pareja, borrar, fallo→revierte) y todo ✓, con el campo foto excluido del guardado sin que eso rompiera ninguna prueba.
**Causa raíz:** En el frontend la foto era un dataURL embebido, que no cabe en `avatar VARCHAR(255)`. El backend optó por ignorar el campo en silencio.
**Cómo se cazó:** ojo humano (lo declara Claude Code al entregar el sub-paso de escritura)
**Arreglo aplicado:** PASO 7: subida de la imagen como archivo a `almacen/fotos/` (redimensión GD, reencode JPEG, nombre aleatorio), guardar solo el nombre en `avatar`, y servirla por el portero `public/foto.php?persona=<id>`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un campo que se ignora "a propósito" es un dato que se pierde en silencio; hay que probar el campo, no solo el flujo.
**Traza:** #465, #469, #480, #486, #488; `src/Personas.php`, `src/Fotos.php`, `public/api/foto.php`, `public/foto.php`, `avatar VARCHAR(255)`.

## [2026-07-07] — Fotos huérfanas: subes una imagen, cierras sin guardar, y el archivo se queda
**Categoría:** datos
**Síntoma:** Si se sube una foto y se cierra el formulario sin guardar, el archivo queda en `almacen/fotos/` sin dueño.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La verificación del PASO 7 salió toda en verde (subir, recargar y persiste, reemplazar borra el antiguo, quitar borra el archivo, URL directa a `almacen/` → 404, demo restaurado limpio) — la limpieza solo cubría fotos **ya guardadas**.
**Causa raíz:** La subida es *stateless* (guarda el archivo y devuelve el nombre); la asociación y la limpieza las hace el guardado de la persona, que puede no llegar nunca.
**Cómo se cazó:** ojo humano (lo declara Claude Code como "nota honesta / limitación menor")
**Arreglo aplicado:** Ninguno en su momento. Se anota como pendiente (limpieza periódica de huérfanas). Resuelto más tarde como SEC-15 (ver 2026-07-09).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Todo recurso que se crea antes de confirmar la operación necesita su recogedor de basura, o se acumula.
**Traza:** #488, #489; `src/Fotos.php`, `public/api/foto.php`, `almacen/fotos/`, `PENDIENTES.md`.

## [2026-07-07] — Exportar/Importar JSON quedó incoherente con las fotos como archivo
**Categoría:** carencia
**Síntoma:** Al pasar las fotos de dataURL embebido a archivo en servidor, el Exportar/Importar JSON deja de ser coherente: o embebe imágenes que ya no tiene, o exporta nombres de archivo que no viajan.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Se cerró el PASO 7 con "el Export/Import no se ha tocado y **sigue funcionando**", sin resolver el fondo del problema.
**Causa raíz:** El formato de exportación se diseñó cuando la foto vivía dentro del JSON; el cambio de almacenamiento lo dejó desalineado.
**Cómo se cazó:** ojo humano (lo señala Claude Code en el punto 6 de su plan del Paso 7)
**Arreglo aplicado:** Ninguno. Decisión aplazada explícitamente y anotada en `PENDIENTES.md`. (Se retomó el 2026-07-10.)
**Commit:** NO CONSTA
**Ley que sale de aquí:** Cambiar dónde vive un dato rompe todo lo que lo serializaba; el export es parte del contrato.
**Traza:** #482–#485, #488; `PENDIENTES.md`, exportación JSON, `avatar`.

## [2026-07-07] — Los documentos pegados llegaban en blanco y se aprobó a ciegas
**Categoría:** rompe
**Síntoma:** Repetidas veces el usuario adjunta la respuesta de Claude Code como documento y llega vacía: "Sigue llegando vacío, no viene ningún contenido en el documento".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ En #472–#473 el asistente **aprobó el sub-paso 2 del PASO 6 (la escritura en BD, "el corazón del backend") sin haber leído nunca la respuesta de Claude Code**, apoyándose solo en un "sí, lo probé" del usuario. Volvió a ocurrir con el Paso 8 (#496), con el diseño del demo (#641) y con el Paso 11 (#684).
**Causa raíz:** NO CONSTA (se atribuye a "cómo se copia desde la interfaz de Claude Code en VS Code").
**Cómo se cazó:** ojo humano (el asistente ve el documento vacío)
**Arreglo aplicado:** Apaño manual: pegar el texto directamente en el chat, partido en dos o tres bloques.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si el canal por el que llega la evidencia falla, no se aprueba: se para hasta ver el contenido.
**Traza:** #471, #473, #475–#478, #496, #641, #684.

## [2026-07-07] — El login se cerró con "nombre + apellidos" y hubo que revertirlo en caliente
**Categoría:** carencia
**Síntoma:** El usuario responde en el cuestionario "Nombre + apellidos, como recomienda", el asistente cierra la decisión y monta el prompt; acto seguido el usuario frena: "frena frena que conteste mal… nombre es solo nombre sin apellidos porque sino esto es un sindios".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (se paró antes de implementar)
**Causa raíz:** Decisión cerrada sobre una respuesta rápida a un cuestionario, sin comprobar que el usuario había entendido/querido esa opción.
**Cómo se cazó:** usuario
**Arreglo aplicado:** Se revierte a "nombre + primer apellido + fecha" y se rehace el prompt del PASO 8.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Las decisiones que llegan por un clic en un cuestionario hay que leerlas en voz alta antes de convertirlas en código.
**Traza:** #501–#503, #505, #506; `api/login.php`, `src/Auth.php`.

## [2026-07-07] — Borrar dos personas seguidas resucitaba a la primera (y la duplicaba)
**Categoría:** datos
**Síntoma:** "Borré un hijo: bien, desapareció. Borré un segundo hijo: entonces el PRIMERO que ya había borrado REAPARECIÓ en el árbol, y pude volver a borrarlo → duplicidad."
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Claude Code entregó el PASO 10 "construido y **verificado por completo**", con tabla en verde: "Borrar persona con vínculos ✓, Restaurar ✓, Eliminar definitivo ✓, Vaciar ✓, Acceso ✓, Modal en claro/oscuro ✓ … sin errores de consola". El asistente lo dio por bien resuelto. El bug estaba vivo y era de integridad de datos.
**Causa raíz:** Las mutaciones del store de family-chart son síncronas al clic, pero la cola de persistencia era asíncrona: al terminar el guardado #1 se llamaba a `recargarDesdeBD()` con una foto de la BD tomada **antes** del segundo borrado, lo que resucitaba a la segunda persona y fijaba una instantánea contaminada; el guardado #2 comparaba estado-con-Diego contra instantánea-con-Diego → sin diferencia → **borrado perdido**. Dos defectos: `enviarCambios` leía `exportData()` en el momento asíncrono, y `recargarDesdeBD` reemplazaba el store en mitad de una ráfaga.
**Cómo se cazó:** usuario (probando a mano) → reproducido de forma determinista con un arnés que fuerza latencia de red
**Arreglo aplicado:** Reescritura de `persistir.js`: capturar el estado deseado **síncronamente en el instante del clic**, y releer la BD **una sola vez cuando la cola queda vacía**, nunca entre cambios en curso; mapa de ids temporales→reales entre cambios encolados.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si el estado se lee después de la acción y no en el instante de la acción, una ráfaga de clics acabará guardando una realidad que ya no existe.
**Traza:** #547, #549, #551–#554; `persistir.js` (`colaPersist`, `enviarCambios`, `recargarDesdeBD`), `store.updateData()`, `f3Edit.exportData()`.

## [2026-07-07] — El diálogo de confirmación salía por debajo del modal de la papelera
**Categoría:** visual
**Síntoma:** Al pulsar "Eliminar definitivamente" desde la papelera, el diálogo de confirmación se solapaba con el modal de la papelera que quedaba delante: "dos capas encimadas, se ve fatal".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La verificación del PASO 10 incluía la fila "Modal en claro/oscuro → correcto ✓" y "Vaciar (doble confirmación) → ✓". El apilamiento no lo detectó ninguna de las pruebas automatizadas.
**Causa raíz:** z-index del diálogo en 1000 y el de la papelera en 2500 → el diálogo quedaba detrás.
**Cómo se cazó:** usuario (probando a mano, con captura)
**Arreglo aplicado:** Diálogo a z-index 3500, por encima de todo.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Una prueba automatizada que comprueba "el modal existe" no ve que el modal está debajo de otro.
**Traza:** #549, #552; `papelera.js`, `borrar.js`, `dialogo.js`.

## [2026-07-07] — Los botones se podían machacar y apilaban capas sin límite
**Categoría:** rompe
**Síntoma:** "Puedo darle a eliminar tantas veces como yo quiera y la pantalla de fondo se me generará tantas veces como le dé": cada pulsación apilaba otro diálogo/fondo, sin tope.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Todo el PASO 10 se había entregado verificado (API + navegador, ambos temas, sin errores de consola) sin ninguna prueba que pulsara un botón dos veces.
**Causa raíz:** Ninguna protección anti-doble-disparo: los botones de acción no se bloqueaban al pulsarlos y cada clic instanciaba su propio diálogo (había además dos implementaciones duplicadas de diálogo, en `borrar.js` y `papelera.js`).
**Cómo se cazó:** usuario
**Arreglo aplicado:** `dialogo.js` nuevo y compartido: un solo diálogo a la vez (el segundo clic se ignora) y auto-bloqueo del botón mientras procesa. Se refactorizan `borrar.js` y `papelera.js` para usarlo; auditoría del resto de botones de la app.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si el usuario puede pulsar dos veces, lo hará; todo botón que dispara una escritura tiene que bloquearse solo.
**Traza:** #550–#553; `dialogo.js`, `borrar.js`, `papelera.js`.

## [2026-07-07] — Bug latente: restaurar de la papelera y editar duplicaba a la persona restaurada
**Categoría:** datos
**Síntoma:** No lo llegó a ver el usuario: se destapó al arreglar la desincronización de los borrados consecutivos.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La verificación completa del PASO 10 daba "Restaurar → vuelve al árbol conectada… persiste tras recargar ✓". El caso "restaurar y **después editar**" no se probaba, y ahí estaba el fallo.
**Causa raíz:** `recargarDesdeBD` no re-fijaba la instantánea tras restaurar, así que la instantánea quedaba obsoleta y una edición posterior interpretaba a la persona restaurada como nueva → duplicado.
**Cómo se cazó:** casualidad (aparece mientras se reescribe `persistir.js` por otro bug)
**Arreglo aplicado:** `recargarDesdeBD` ahora re-fija siempre la instantánea.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Las pruebas de una operación aislada no ven los bugs de la operación **siguiente**; hay que probar secuencias.
**Traza:** #552, #553; `persistir.js` (`recargarDesdeBD`, instantánea).

## [2026-07-07] — Borrar a un cónyuge con hijos blanqueaba a la persona: ni en el árbol ni en la papelera
**Categoría:** datos
**Síntoma:** "Cuando tenemos marido y mujer y cuelgan hijos y borro a uno de los dos, se queda como 'sin nombre' y no va a papelera ni a ningún lado". La fila de la BD se quedaba en blanco: nombre sobrescrito e irrecuperable.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Toda la verificación del PASO 10 (papelera, restaurar, eliminar definitivo, roles) salió en verde; el propio Claude Code lo mencionó al final como "observación honesta… no es parte de los problemas 1-3 y no lo he tocado". Se estuvo a punto de cerrar el paso con una vía de **pérdida de datos** abierta.
**Causa raíz:** family-chart, por diseño, no deja romper una rama: si borrar a alguien desconectaría a familiares, en vez de eliminarlo lo convierte en tarjeta anónima; nuestra persistencia replicaba eso escribiendo la ficha en blanco en la BD.
**Cómo se cazó:** usuario (y en paralelo, observación del propio Claude Code)
**Arreglo aplicado:** Usar el método oficial `f3Edit.setCanDelete(fn)` con el predictor `checkIfRelativesConnectedWithoutPerson` para **deshabilitar** el botón Eliminar en todas las casuísticas donde la persona quedaría anónima, con nota explicativa y sugerencia de "Separar pareja"; más salvaguarda en `setOnDelete`. Después se añade la red de seguridad A.2: si aun así alguien quedara `unknown`, se manda a la papelera conservando fila y datos.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si una acción de la interfaz puede dejar un dato irrecuperable, no se avisa: se deshabilita.
**Traza:** #554, #556–#561, #564, #598, #600, #606, #655; `setCanDelete`, `checkIfRelativesConnectedWithoutPerson`, `setOnDelete`, `persistir.js`, `ficha.js`.

## [2026-07-07] — Dos botones para lo mismo: "Separar" y el nativo "Quitar relación"
**Categoría:** visual
**Síntoma:** En la sección Pareja del formulario convivían el botón propio "Separar" y un botón nativo de la librería "Quitar relación", redundante y confuso.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (ninguna verificación previa lo señaló, pese a haberse construido el "Separar" como sustituto)
**Causa raíz:** Se añadió el botón propio sin retirar el nativo que venía de family-chart.
**Cómo se cazó:** usuario (mirando su propia captura del formulario)
**Arreglo aplicado:** Se retira en `ficha.js` el `<div>` contenedor del botón nativo (sin dejar `<hr>` colgando). Verificado: 0 botones "Quitar relación" en tres personas y ambos temas.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Cuando sustituyes un control de la librería, hay que **quitar** el original, no dejarlo al lado.
**Traza:** #566, #594; `ficha.js`.

## [2026-07-07] — El botón "Separar" se veía como texto plano
**Categoría:** visual
**Síntoma:** El botón "Separar" desentonaba: no parecía un botón, salía como texto.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** Conflicto de especificidad CSS: la regla `.f3-form button { border:none }` de la librería ganaba a la del proyecto.
**Cómo se cazó:** usuario
**Arreglo aplicado:** Escopar la regla a `.separar-pareja .separar-btn`; botón con borde redondeado y fuente 14px, correcto en claro y oscuro.
**Commit:** NO CONSTA
**Ley que sale de aquí:** El CSS de la librería compite con el tuyo; si tu estilo "no se aplica", casi siempre es especificidad, no un typo.
**Traza:** #566, #594; `estilos.css`, `.f3-form button`, `.separar-pareja .separar-btn`.

## [2026-07-07] — El asistente inventó la razón por la que Carmen no se podía borrar
**Categoría:** aviso falso
**Síntoma:** El asistente explica con seguridad: "Carmen es pareja de Emilio (que ya está anónimo)… por eso el botón está deshabilitado. Correcto." y lo usa para justificar el comportamiento ante el usuario.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El propio asistente lo declaró "el sistema está distinguiendo bien los dos casos… No es un fallo, es el predictor haciendo su trabajo", y sobre esa explicación falsa el usuario tomó decisiones de diseño.
**Causa raíz:** El asistente dedujo el algoritmo del predictor en vez de leerlo. Claude Code, leyendo el código real, encontró el motivo verdadero: Carmen está bloqueada **porque sus padres (Tomás y Pilar) solo llegan al árbol a través de ella**, no por Emilio ni por sus hijos.
**Cómo se cazó:** test (Claude Code lee el código fuente de `checkIfRelativesConnectedWithoutPerson`)
**Arreglo aplicado:** Se corrige la explicación y se reescribe la nota del botón deshabilitado en términos generales ("parte de la familia quedaría suelta") en vez de nombrar familiares concretos.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Explicar el comportamiento de una librería "por lógica" es inventarlo; se lee el código o no se afirma.
**Traza:** #566, #577, #580, #594, #595; `checkIfRelativesConnectedWithoutPerson`, `ficha.js`.

## [2026-07-07] — La regla "si tiene hijos no se puede borrar" se acordó y hubo que revertirla
**Categoría:** carencia
**Síntoma:** Tras ver que a Lucía sí la dejaba borrar y a Carmen no, se acuerda una regla nueva: "si en la BD cuelgan hijos de ese contacto no se puede eliminar así de simple", y el asistente la da por buena ("sustituye/refina la regla que acabamos de poner").
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La regla se anotó como "fallo/mejora a corregir" en la lista del siguiente prompt y estuvo a punto de sustituir a la lógica correcta.
**Causa raíz:** La regla se dedujo del síntoma (dos madres, una borrable y otra no) apoyándose en la explicación falsa del asistente; la regla real de la librería (conectividad) es más precisa y prohibiría menos borrados seguros.
**Cómo se cazó:** test (Claude Code, leyendo la librería, demuestra que la regla propuesta es peor)
**Arreglo aplicado:** Se **revierte** la regla acordada; se mantiene la lógica del predictor ("que nadie quede desconectado") y se mejora la nota explicativa para que no despiste.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Una regla de negocio deducida de dos casos observados suele ser peor que la que ya implementaba la librería.
**Traza:** #576–#582, #594–#596; `setCanDelete`, `checkIfRelativesConnectedWithoutPerson`.

## [2026-07-07] — La nota de "no se puede eliminar" se colaba en la ficha de lectura
**Categoría:** visual
**Síntoma:** El texto explicativo del botón Eliminar deshabilitado aparecía también en modo lectura, donde no hay edición posible.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** La nota se pintaba sin condicionarla al modo edición.
**Cómo se cazó:** test (lo detecta y corrige Claude Code durante su propia verificación)
**Arreglo aplicado:** La nota solo se muestra en edición.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Todo texto nuevo de la interfaz de edición hay que comprobarlo también en el modo lectura.
**Traza:** #594; `ficha.js`.

## [2026-07-07] — La ficha solo calculaba parentescos hacia abajo
**Categoría:** carencia
**Síntoma:** En la ficha de una persona de arriba (Joaquín) se veían pareja, hijos, nietos y bisnietos; en la de una hoja (Marta) **solo aparecían "Padres"**: faltaban abuelos, bisabuelos, hermanos, tíos, primos, todo lo ascendente y colateral.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La ficha de lectura se había dado por buena varias veces, incluida la verificación del PASO 5: "Ficha de lectura (Emilio) → Fechas DD/MM/AAAA ✓ · **parentescos calculados ✓**". La función solo miraba hacia abajo y nadie lo notó porque siempre se probaba con personas de generaciones altas.
**Causa raíz:** `parentescos()` solo calculaba descendencia (hijos/nietos/bisnietos) + Padres + sobrinos/primos; no había cálculo ascendente.
**Cómo se cazó:** usuario (comparando la ficha de Joaquín con la de Marta)
**Arreglo aplicado:** Reescritura de `parentescos()`: 18 tipos (directa, ascendientes, descendientes, colaterales, políticos separados por sexo) + genéricos "N.º abuelos/nietos", sin repetir persona (gana la categoría más cercana), presentados en subsecciones.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si siempre pruebas la ficha con la misma persona, solo verificas la mitad del grafo; hay que probar desde la raíz, desde el medio y desde la hoja.
**Traza:** #583–#589, #612, #613, #620, #655; `ficha.js` (`parentescos()`).

## [2026-07-07] — Se iba a hacer el backtesting de parentescos sobre un demo que no tenía esos parentescos
**Categoría:** carencia
**Síntoma:** El macro prompt exigía "backtesting exhaustivo 10/10" de primos hermanos, tíos abuelos, sobrinos nietos, cuñados, suegros y nueras… sobre un demo de 12 personas donde **no existía ninguno de esos parentescos**.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El prompt ya estaba montado y aprobado por el asistente, con su tabla de verificación exhaustiva. Habría dado 100% de aciertos verificando parentescos inexistentes ("comprar una red para pescar y probarla en una piscina vacía").
**Causa raíz:** Se especificó la verificación sin comprobar que los datos de prueba contenían los casos a verificar.
**Cómo se cazó:** usuario ("convendría hacer crecer bastante más el árbol… y de esa manera probar todo el potencial")
**Arreglo aplicado:** Se antepone el diseño y la carga de un demo nuevo ("Familia Gil", 34 personas, 7 generaciones, con dos casos de segundas nupcias) con una tabla explícita "parentesco → persona con la que se prueba" que cubre los 18 tipos; se verifica la cobertura **antes** de implementar.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un test verde sobre un caso que no existe en los datos es un test vacío; primero el banco de pruebas, después la prueba.
**Traza:** #621–#624, #632, #642, #644, #646, #647, #655; `db/datos-demo.sql`.

## [2026-07-07] — La tarjeta "Sin nombre" no era una persona perdida: era un placeholder de la librería
**Categoría:** aviso falso
**Síntoma:** Una tarjeta "Sin nombre" aparecía como pareja de Joaquín y persistía al recargar. Se diagnosticó como "una persona en estado inconsistente: ni bien borrada, ni bien presente", residuo de un blanqueo, y se llegó a temer pérdida de datos.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El asistente afirmó que "esa tarjeta 'Sin nombre' es seguramente un residuo de cuando se 'blanqueaba' a alguien… puede que tengas ahí una persona en un estado inconsistente" y lo anotó como fallo de datos. La BD, comprobada después, tenía **0 filas activas en blanco**.
**Causa raíz:** family-chart dibuja por defecto una tarjeta hueca del progenitor ausente (`single_parent_empty_card`, activada por defecto = "Unknown"); se regenera en cada render. No era una fila de la BD ni había pérdida de datos.
**Cómo se cazó:** test (Claude Code investiga y encuentra la opción de la librería)
**Arreglo aplicado:** `setSingleParentEmptyCard(false)` en la configuración del chart. Cero tarjetas "Sin nombre" por cualquier vía (re-verificado 18/18).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Antes de dar por corrupto un dato, mira si lo que ves es solo pintura de la librería: el síntoma visual no prueba el estado de la BD.
**Traza:** #569, #572, #598, #600, #606, #607; `setSingleParentEmptyCard`, `persistir.js`.

## [2026-07-07] — Exportar el árbol grande cortaba las líneas y dejaba personas sueltas
**Categoría:** visual
**Síntoma:** Al exportar a PNG/PDF con el demo nuevo, las personas de la zona derecha (Iker, Nerea, Laura, Vera) salían **sin sus líneas de conexión**, sueltas.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La exportación PNG/PDF llevaba dada por buena desde el frontend y se re-verificó tras la separación en módulos ("Menú Exportar (PNG/PDF) ✅"). Con el demo pequeño (12 personas) siempre salía bien; el fallo solo aparece con un árbol ancho.
**Causa raíz:** family-chart revela las tarjetas de forma perezosa (deja `.card_cont` con `opacity:0` fuera del encuadre visible) y guarda "fantasmas" de transiciones; al rasterizar, lo que estaba fuera de vista salía invisible. No era recorte de tamaño.
**Cómo se cazó:** usuario (mirando el PNG/PDF exportado)
**Arreglo aplicado:** Antes de rasterizar, encuadrar todo el árbol sin animación (`fit`, transition 0) para revelar todas las tarjetas y líneas de una vez; agrandar la cadena de contenedores y ocultar los puntitos del mini-árbol. El lado mayor se limita (~13000 px) por el máximo de canvas del navegador: en árboles enormes baja resolución, no recorta.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Los bugs de escala no aparecen en el dato pequeño; si el producto tiene que aguantar 300 personas, hay que probarlo con muchas más de 12.
**Traza:** #652–#654, #668–#670; `exportar.js`, `.card_cont`, `updateTree({tree_position:'fit'})`, html-to-image, jsPDF.

## [2026-07-07] — La exportación no exporta a todos: solo al árbol de la persona central
**Categoría:** carencia
**Síntoma:** Tras arreglar la exportación, sigue sin salir el árbol completo: centrado en la persona principal salen 30 de 34 personas; las 4 restantes (la familia política del cónyuge) solo aparecen centrando en él.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Se cerró el arreglo de la exportación como "sale entero, con todas las líneas, sin fantasmas" y verificado en PNG (claro y oscuro) y PDF; la pérdida de 4 de 34 personas va en una "nota" al margen.
**Causa raíz:** family-chart es egocéntrico: siempre dibuja el vecindario de una persona central, así que "exportar todo" no existe como tal.
**Cómo se cazó:** ojo humano (lo declara Claude Code como nota en su entrega)
**Arreglo aplicado:** Ninguno; se asume y se explica al usuario como naturaleza de la librería.
**Commit:** NO CONSTA
**Ley que sale de aquí:** "Sale entero" hay que definirlo: entero desde quién. Con una librería egocéntrica, la exportación completa no existe.
**Traza:** #669, #670; `exportar.js`, `main_id`.

## [2026-07-07] — Solo una subsección de la ficha llevaba el filete de color
**Categoría:** visual
**Síntoma:** De las cinco subsecciones de parentescos, solo "Política" llevaba el filete/barra de color a la izquierda; las otras cuatro salían planas y desiguales.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La entrega de los parentescos se cerró con "35/35 PASS… subsecciones bien maquetadas" y capturas revisadas en ambos temas.
**Causa raíz:** El filete se introdujo solo como marca diferenciadora de "familia política" (afinidad vs sangre), sin unificar el tratamiento visual del resto.
**Cómo se cazó:** usuario
**Arreglo aplicado:** Filete de color (por sexo) en las cinco subsecciones, coherente en claro y oscuro.
**Commit:** NO CONSTA
**Ley que sale de aquí:** "Maquetado correcto" en una tabla de verificación no significa "coherente"; la coherencia visual no la ve un test.
**Traza:** #659–#661, #668, #669; `ficha.js`, `estilos.css`.

## [2026-07-07] — El botón "ver todo el árbol" no hacía lo que se quería (y se entregó verificado)
**Categoría:** carencia
**Síntoma:** "Cuando le doy al botón quiero que vuelva como estábamos en el paso 1, que es el árbol total de inicio, y en vez de eso solamente me centra la posición de donde estoy ahora".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Claude Code entregó el botón como verificado, 6/6 PASS: "todas las tarjetas dentro del viewport tras pulsarlo". La prueba comprobaba exactamente lo que decía la especificación… que era la especificación equivocada.
**Causa raíz:** La especificación la escribió mal el asistente: mezcló "encuadrar la vista" con "volver al inicio" y ofreció al usuario una opción ("encuadrar TODO el árbol") que no era lo que quería. El asistente lo admite: "antes te dije mal las opciones: mezclé 'encuadrar' con 'volver al inicio'".
**Cómo se cazó:** usuario (explicándolo paso a paso con capturas)
**Arreglo aplicado:** El botón pasa a "Volver al inicio": `updateMainId(persona de inicio)` + `updateTree({tree_position:'fit'})`, con `window.personaInicio` estable frente a la navegación.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un test verde sobre una especificación equivocada es una mentira eficiente; el fallo estaba en la pregunta, no en el código.
**Traza:** #662, #664, #665, #668, #669, #671–#680; `updateTree({tree_position:'fit'})`, `updateMainId`, `window.personaInicio`.

## [2026-07-07] — El id como cadena colapsaba el árbol a la línea directa
**Categoría:** rompe
**Síntoma:** Al pulsar el botón corregido, la vista volvía a la persona principal pero **reducida**: solo la línea directa (16 personas de 30), sin hermanos, tíos ni primos.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (se detecta durante la propia implementación del arreglo)
**Causa raíz:** family-chart reconstruye el árbol con todos los colaterales **solo si el `main_id` se le pasa como NÚMERO** (así lo hace su arranque); con un id de cadena colapsa a la línea directa. El proyecto había decidido explícitamente enviar los ids de la BD **como texto** al frontend ("IDs enteros de la BD enviados como texto").
**Cómo se cazó:** test (Claude Code lo persigue tras descartar recargar datos, `initial:true` y reforzar profundidades)
**Arreglo aplicado:** Convertir el id a entero antes de `updateMainId`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Una decisión de tipos tomada "para encajar sin fricción" (ids como texto) puede cambiar el comportamiento de la librería en silencio meses después.
**Traza:** #447, #680, #681; `updateMainId`, `public/api/arbol.php` (ids como `(string)$p['id']`).

## [2026-07-07] — El asistente lanzaba prompts como un poseso y cortaba al usuario
**Categoría:** carencia
**Síntoma:** El usuario, dos veces: "y sigues adelantándote a todo me cago en sandios" / "en lanzarte a los prompt como un poseso torpedeando la conversación y dejándome a medias" / "grábatelo a fuego".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA
**Causa raíz:** El asistente montaba y disparaba el prompt en cuanto intuía la dirección, sin esperar a que el usuario terminase de pensar ni de enumerar los fallos que estaba viendo.
**Cómo se cazó:** usuario (queja explícita)
**Arreglo aplicado:** Norma nueva: no preparar ningún prompt hasta que el usuario lo pida explícitamente.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Adelantarse al usuario no es eficiencia: es perder la mitad de lo que te iba a contar.
**Traza:** #625–#630.

## [2026-07-07] — Se iba a implementar los parentescos sin haber definido cómo se muestran
**Categoría:** carencia
**Síntoma:** El prompt de parentescos ya estaba montado y a punto de lanzarse cuando el usuario señala que no se ha definido la presentación. El asistente lo admite: "Tienes toda la razón, me he adelantado otra vez. Culpa mía."
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (se paró antes de implementar)
**Causa raíz:** Se especificó el **cálculo** de 18 parentescos sin especificar la **maquetación**, cuando la lista plana existente iba a pasar de 4 grupos a más de una docena y quedaría ilegible.
**Cómo se cazó:** usuario
**Arreglo aplicado:** Se define la presentación antes de implementar: subsecciones (Familia directa / Ascendientes / Descendientes / Colaterales / Política), orden de más cercano a más lejano, etiqueta en negrita + nombres, ocultar las vacías.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Multiplicar por tres la cantidad de información que muestra una pantalla es un cambio de diseño, no solo de cálculo.
**Traza:** #616–#620; `ficha.js`.

---

# 2026-07-08 — Instalador, panel de administración y el día del "Deshacer" que vaciaba el árbol

## [2026-07-08] — El instalador "ignoraba" las claves… era la sesión vieja del navegador
**Categoría:** aviso falso
**Síntoma:** Tras instalar eligiendo "Proteger el árbol con claves", al entrar no pedía login y metía directo.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Aquí lo que dio ROJO fue una prueba manual sobre código sano. La consulta a `arb_ajustes` mostró `acceso_activo=1` y ambos hashes guardados: el instalador había hecho su trabajo.
**Causa raíz:** El navegador conservaba la cookie de sesión del demo anterior; el usuario ya estaba autenticado. No había fallo de aplicación.
**Cómo se cazó:** usuario (lo reportó) + comprobación en BD y prueba en ventana de incógnito.
**Arreglo aplicado:** Ninguno en código. Se descartó abriendo en incógnito (allí sí pedía login).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Toda prueba de "¿pide login?" se hace en sesión limpia (incógnito). Una sesión heredada convierte una app sana en un falso agujero de seguridad.
**Traza:** #770–#778; `arb_ajustes` (`acceso_activo`, hashes), `Auth::controlActivo()`.

## [2026-07-08] — ZipArchive deshabilitado: el formato de copia planeado no se podía construir
**Categoría:** despliegue
**Síntoma:** El PASO 11 (copias de seguridad) se había planeado en ZIP; ZipArchive estaba deshabilitado en el PHP local y podía faltar en el hosting.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (se detectó antes de construir).
**Causa raíz:** Se planificó un formato de copia que dependía de una extensión de PHP no garantizada en el entorno ni en el hosting de destino.
**Cómo se cazó:** casualidad (al ir a implementarlo).
**Arreglo aplicado:** Se abandonó ZIP; el formato de copia pasó a ser un único JSON (manifest + 5 tablas + fotos en base64), sin dependencias.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Antes de diseñar sobre una extensión de PHP, comprobar que existe en local Y en el hosting de destino.
**Traza:** #697, #698, #707; `src/Backup.php`, `api/backup*.php`, `salud.php`.

## [2026-07-08] — Las claves de acceso vivían en un fichero que el auto-deploy iba a pisar
**Categoría:** despliegue
**Síntoma:** Los hashes de las dos claves y el interruptor de control de acceso estaban en `config/config.php`, y `Auth::controlActivo()` devolvía `true` fijo.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA. El login funcionaba y estaba dado por bueno desde el PASO 8; el defecto era estructural, no funcional.
**Causa raíz:** Estado mutable (claves, interruptor) guardado en un fichero de código: no se puede reescribir en caliente desde un panel y el auto-deploy de Git lo resubiría, pisando cualquier cambio hecho desde la app.
**Cómo se cazó:** casualidad (al diseñar el instalador, PASO 12).
**Arreglo aplicado:** Refactor 12.1: hashes + `acceso_activo` a `arb_ajustes`, tras un punto único `src/Acceso.php`; `api/login.php` valida con `Acceso::verificarClave()`; `Auth::controlActivo()` lee el ajuste. Las credenciales de BD se quedan en `config.php`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Lo que el usuario puede cambiar en caliente no vive en un fichero versionado. El deploy lo pisa.
**Traza:** #728, #729, #734, #736, #742; `config/config.php`, `src/Acceso.php`, `api/login.php`, `Auth::controlActivo()`, `arb_ajustes`.

## [2026-07-08] — Hasta el PASO 12 no había forma de cambiar la clave desde la app
**Categoría:** carencia
**Síntoma:** Cambiar las claves o el interruptor de acceso solo era posible durante la instalación o tocando la BD a mano.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Los PASOS 8 y 12 (login e instalador) se cerraron como "aprobados" sin que nadie notara que el admin no podía cambiar su propia contraseña.
**Causa raíz:** Se construyó la autenticación sin la contraparte de administración; el hueco solo salió al hacer el inventario de pendientes.
**Cómo se cazó:** ojo humano (Claude Code, al listar pendientes a petición del usuario).
**Arreglo aplicado:** Bloque "Seguridad" del panel de administración: `api/seguridad.php` (cambiar clave de edición/lectura, interruptor on/off) con reautenticación por clave actual.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si construyes un login, construye también el cambio de clave. Un secreto que no se puede rotar es un secreto muerto.
**Traza:** #803, #805, #806, #870; `api/seguridad.php`, `src/Acceso.php`, `PENDIENTES.md`.

## [2026-07-08] — La guía de terminal no contemplaba el cambio de unidad en Windows
**Categoría:** despliegue
**Síntoma:** El usuario ejecutó el `cd` a la carpeta del proyecto (otra unidad) y la terminal siguió en la ruta anterior; los comandos posteriores habrían fallado.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El asistente dio el paso por hecho ("Perfecto, ya estás en la carpeta", #749) antes de ver la salida real de la terminal.
**Causa raíz:** Instrucciones escritas sin tener en cuenta que en Windows `cd` no cambia de unidad; hace falta `f:` primero.
**Cómo se cazó:** usuario (pegó la salida de la terminal).
**Arreglo aplicado:** Se corrigió la guía: primero `f:`, luego `cd`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** No des por confirmado un paso que no has visto ejecutado. Y en Windows, cambiar de unidad no es un `cd`.
**Traza:** #748–#752; terminal de Laragon.

## [2026-07-08] — El desplegable de "Añadir familiar" se quedaba pegado en pantalla
**Categoría:** rompe
**Síntoma:** Al pulsar el muñeco de añadir familiar se abría el menú de huecos (madre/padre/cónyuge/hijo) y no desaparecía ni al Cancelar, ni al salir del modo edición, ni al hacer clic fuera.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA prueba automática sobre esto; el flujo de "añadir familiar" se venía dando por bueno.
**Causa raíz:** El botón Cancelar nativo de family-chart cierra el formulario pero **no** desactiva el modo: `isAddingRelative()` seguía en `true` y los huecos quedaban dibujados.
**Cómo se cazó:** usuario (probando el instalador).
**Arreglo aplicado:** Vía oficial `f3Edit.addRelativeInstance.onCancel()` (que hace `cleanUp()` de los huecos), envuelto en `cancelarAnadirFamiliar()` y enganchado a los tres casos: Cancelar (ficha.js), salir de edición (dispositivo.js) y clic fuera.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Cerrar el formulario no es salir del modo. Cada modo necesita su cancelación explícita en todas las salidas posibles.
**Traza:** #780, #781, #789, #802, #803; `ficha.js`, `dispositivo.js`, `f3Edit.addRelativeInstance.onCancel()`, `isAddingRelative()`.

## [2026-07-08] — El halo del buscador era siempre azul, mintiendo sobre el sexo
**Categoría:** visual
**Síntoma:** Al buscar y seleccionar a alguien, el halo temporal de localización era azul incluso sobre una mujer, chocando con el código azul=hombre / rosa=mujer.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA. El buscador se había verificado como funcional; nadie miró la semántica del color.
**Causa raíz:** El halo se pintó con el color de acento de la app, que era el mismo azul que el del sexo masculino (raíz confirmada más tarde, #960: `--accent` == `--male`).
**Cómo se cazó:** ojo humano (usuario).
**Arreglo aplicado:** Halo a ámbar/dorado neutro (`rgba(245,176,32)`) en el keyframe `pulso-localizado`, verificado en claro y oscuro.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si un color tiene significado semántico en la app, no puede usarse como decoración en ningún otro sitio.
**Traza:** #786–#789, #802, #803, #960; keyframe `pulso-localizado`.

## [2026-07-08] — "Añadir familiar" saltaba a los tatarabuelos: el main_id era número y los ids, cadenas
**Categoría:** rompe
**Síntoma:** Al seleccionar a Vera (hoja del borde, 2024) y pulsar "Añadir familiar", el árbol saltaba a otra rama en vez de mostrar los huecos alrededor de ella.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ **La vista completa del árbol.** Como en vista completa se dibuja todo igual, el fallo era invisible: afectaba a **las 34 personas**, pero solo se notaba en las lejanas al vértice. Todos los backtests anteriores del árbol pasaron con el bug vivo.
**Causa raíz:** En `CalculateTree`, family-chart resuelve la raíz con `t && x.find(e => e.id === t) || (t = x[0].id)`. Comparación estricta `===`: los ids de los nodos son cadenas ("34"); si `main_id` era número, `find` fallaba y caía en `data[0]` (el vértice del árbol). Al activar "añadir familiar" se enciende `one_level_rels`, que dibuja solo el entorno del main → el árbol saltaba al vértice.
**Cómo se cazó:** usuario, probando el caso extremo (Vera, hoja del borde).
**Arreglo aplicado:** Listener en fase de captura sobre `.f3-add-relative-btn` que fija `main = String(id_persona)` antes de que la librería procese el clic (ficha.js). Backtesting 34 personas × 4 sub-opciones × 2 temas.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un `===` en una librería obliga a que tus tipos coincidan exactamente. Y un bug que solo se ve en los bordes existe también en el centro: probar el rango completo, no un caso.
**Traza:** #807, #808, #811–#814; `CalculateTree` (family-chart), `ficha.js`, `.f3-add-relative-btn`, `one_level_rels`, `main_id`.

## [2026-07-08] — "Volver al inicio" no respondía al primer clic
**Categoría:** rompe
**Síntoma:** Con el modo añadir activo, pulsar "Volver al inicio" no hacía nada; había que pulsarlo dos veces.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA. Es un fallo encadenado al anterior y nunca se había probado en ese estado.
**Causa raíz:** Al recentrar con el modo añadir activo, la cancelación interna de la librería dispara `updateTree()` y **secuestra el `main_id`**, dejándolo en la persona editada como cadena; el primer clic se perdía.
**Cómo se cazó:** usuario (mismo reporte que el anterior).
**Arreglo aplicado:** "Volver al inicio" cancela el modo añadir **antes** de recentrar (arbol.js). Verificado 34/34 en ambos temas.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Dos fallos que comparten causa raíz se arreglan en dos sitios distintos; parchear uno solo deja el otro vivo.
**Traza:** #807, #808, #813, #814; `arbol.js`, `updateTree()`, `main_id`.

## [2026-07-08] — El centrado por login también caía en data[0] con main_id numérico
**Categoría:** rompe
**Síntoma:** Según el informe del arreglo, al iniciar sesión el árbol no enraizaba en la persona del login si el id iba como número: caía en `data[0]`.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA que se reportara síntoma: el centrado por login se venía dando por funcional desde que se construyó (#822 lo describe como algo que "ya funciona").
**Causa raíz:** La misma que en "Añadir familiar": `main_id` numérico contra ids de nodo tipo cadena.
**Cómo se cazó:** casualidad (al construir el halo de entrada y backtestear el ciclo de login).
**Arreglo aplicado:** `updateMainId(String(...))` al centrar tras el login. Backtesting: 108/108 casos en claro + 28/28 en oscuro.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un bug de tipos no se manifiesta en un sitio: hay que buscar TODAS las llamadas que pasan el mismo valor.
**Traza:** #838, #839; `updateMainId()`, `window.personaInicio`.

## [2026-07-08] — Se podía guardar un hijo nacido antes que sus padres
**Categoría:** datos
**Síntoma:** No existía ninguna validación que impidiera fechas de nacimiento imposibles entre progenitor e hijo.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Todo el CRUD de personas estaba "aprobado y cerrado" (PASOS 1–8) con este agujero de integridad abierto.
**Causa raíz:** Se construyó el guardado sin reglas de coherencia del dominio genealógico.
**Cómo se cazó:** ojo humano (usuario, "el hijo/hija de alguien no puede ser de un año de nacimiento anterior a sus progenitores").
**Arreglo aplicado:** `src/Fechas.php` como autoridad en servidor: valida al vincular (`Relaciones::anadirFiliacion`) y al editar nacimiento (`Personas::editar`, contra progenitores E hijos), rechazo con HTTP 400. Cliente: `validarFechasArbol` en ficha.js + mensajes de `persistir.js`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un formulario que acepta datos imposibles es una base de datos que ya está corrupta, aunque nadie se haya dado cuenta todavía.
**Traza:** #815–#818, #835, #838; `src/Fechas.php`, `Relaciones::anadirFiliacion`, `Personas::editar`, `ficha.js`, `persistir.js`.

## [2026-07-08] — La validación de fechas se hizo con un popup de Chrome y hubo que rehacerla
**Categoría:** visual
**Síntoma:** La primera versión de la validación avisaba con un diálogo del navegador, intrusivo y ajeno al formulario.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ **La validación se entregó y verificó como correcta** ("servidor y cliente verificados… mensajes claros", #836/#838). Funcionalmente pasaba; la UX era inaceptable.
**Causa raíz:** Se implementó el aviso por la vía rápida (diálogo nativo) sin diseñarlo como validación de formulario.
**Cómo se cazó:** usuario (pidió cambiarlo justo después de aprobarlo).
**Arreglo aplicado:** Validación en vivo: fechas de progenitores e hijos cargadas en memoria al abrir la ficha; campo en rojo al instante con el motivo debajo; bloqueo de guardado; popup eliminado. Servidor intacto como autoridad.
**Commit:** NO CONSTA
**Ley que sale de aquí:** "Funciona" y "está bien hecho" no son lo mismo. Un `alert()` es deuda, no una validación.
**Traza:** #840–#843, #849, #850; `ficha.js`, `src/Fechas.php`.

## [2026-07-08] — Se podían crear personas sin nombre y sin sexo
**Categoría:** carencia
**Síntoma:** No había campos obligatorios: se podía guardar una persona dejando casi todo vacío; y el selector de sexo del instalador ofrecía "sin especificar".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El instalador se probó de punta a punta y se **aprobó** (#785, #798) con el selector "— sin especificar —" activo; y existía ya una herramienta pendiente ("Personas sin nombre") para limpiar precisamente los restos que este agujero produce.
**Causa raíz:** El formulario de persona nunca definió un mínimo obligatorio; el sexo determina color de tarjeta y cálculo de parentescos, así que su ausencia deja la app en estado neutro/roto.
**Cómo se cazó:** usuario ("tenemos que marcar qué campos son obligatorios como mínimo").
**Arreglo aplicado:** Nombre y Sexo obligatorios con asterisco, marcado en rojo y bloqueo de guardado. Instalador: selector `required` y `Instalador::finalizar` rechaza sexo vacío (autoridad servidor).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si un campo gobierna la lógica (colores, parentescos), no puede ser opcional. Y las reglas del formulario principal deben regir también en el instalador.
**Traza:** #766, #844–#847, #849, #850; `ficha.js`, `Instalador::finalizar`.

## [2026-07-08] — api/arbol.php enviaba los hashes de las claves al navegador
**Categoría:** seguridad
**Síntoma:** El endpoint que sirve el árbol volcaba **todos** los ajustes al cliente, incluidos los hashes de las claves de edición y lectura.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ **Todo el PASO 8 (login) y el PASO 12 (instalador) se cerraron como verificados y aprobados** con esta fuga activa. Ningún backtest de login, roles o cerrojo la detectó, porque todos comprobaban comportamiento, no qué datos salían por el cable.
**Causa raíz:** Serialización indiscriminada de la tabla de ajustes hacia el frontend, sin lista blanca. Al mover las claves a `arb_ajustes` (12.1), los hashes entraron en ese volcado.
**Cómo se cazó:** casualidad (Claude Code, mientras construía el panel; lo llamó "fuga tapada de regalo").
**Arreglo aplicado:** Lista blanca en `api/arbol.php`: solo salen los ajustes de pantalla.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un refactor que mueve secretos a una tabla debe auditar todo lo que serializa esa tabla. Y nunca se serializa "todo": lista blanca siempre.
**Traza:** #870, #871; `api/arbol.php`, `arb_ajustes`, `src/Acceso.php`.

## [2026-07-08] — La botonera reorganizada se salía de la pantalla en móvil
**Categoría:** visual
**Síntoma:** "No ha contemplado bien la botonera en tablet y móvil porque se la come": la barra no cabía y se salía por el borde.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ **El panel se entregó con 20/20 en edición y 5/5 en lectura**, incluyendo explícitamente la comprobación de que "el botón ⚙ está oculto en lectura" — es decir, se verificó la lógica de qué botones salen en móvil, pero **no se miró el layout** en ningún ancho pequeño.
**Causa raíz:** La barra estaba anclada solo por la izquierda (`left:20`, sin `right`, sin wrap real): a 320 px medía 310 px y se desbordaba. Los botones tampoco tenían tamaño táctil.
**Cómo se cazó:** usuario.
**Arreglo aplicado:** Anclaje `left/right/bottom: 12px` acotado a `body.dispositivo-lectura`, `flex-wrap`, objetivo táctil de 44 px, elipsis en el botón más ancho. Matriz responsive 25/25 (320/360/414/768/1024 + horizontal, ambos temas).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Verificar que "aparece el botón correcto" no es verificar que "cabe en la pantalla". Responsive se prueba midiendo píxeles, no contando botones.
**Traza:** #872–#875, #888; `body.dispositivo-lectura`, media query `≤600px`, `admin.js`.

## [2026-07-08] — La documentación llamó "PASO 13" al panel de administración
**Categoría:** datos
**Síntoma:** Claude Code etiquetó repetidamente el panel de administración como "PASO 13" en sus informes y estuvo a punto de cerrarlo así en la documentación, cuando el PASO 13 (endurecimiento de seguridad) ni se había empezado.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Los propios informes de verificación decían "todo el panel del PASO 13 sigue funcionando igual" (#888) — el error se coló dentro de los mensajes de verificación.
**Causa raíz:** Numeración interna del plan desincronizada con la del usuario.
**Cómo se cazó:** ojo humano (el asistente lo detectó al leer el informe).
**Arreglo aplicado:** Se le ordenó explícitamente corregirlo; `PENDIENTES.md` y `ESTADO-Y-DECISIONES.md` dejan escrito que el panel NO es el Paso 13 y que este sigue pendiente.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un paso mal numerado en la documentación es un paso que se dará por hecho sin haberse hecho.
**Traza:** #870, #871, #888, #892, #895, #896; `PENDIENTES.md`, `ESTADO-Y-DECISIONES.md`.

## [2026-07-08] — "Demo Gil limpio" era falso: la BD arrastraba restos de las pruebas en paralelo
**Categoría:** datos
**Síntoma:** Al ir a restaurar el demo, `arb_ajustes` tenía título "Familia Blánquez" y ajustes de pantalla que Claude Code no había hecho. Se repitió con `tema_defecto=oscuro` y con una persona "Leti" suelta.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ **Varios informes anteriores cerraron con "Demo Gil limpio / idéntico al original"** mientras la tabla de ajustes estaba contaminada; la comprobación miraba el recuento de personas (34/0), no los ajustes.
**Causa raíz:** El usuario probaba en vivo en la misma base de datos que Claude Code usaba como banco de pruebas, sin ningún aislamiento ni bloqueo entre ambos.
**Cómo se cazó:** ojo humano (Claude Code detectó cambios que no había hecho y **paró a preguntar** en vez de sobrescribir).
**Arreglo aplicado:** Restauración explícita del demo (título, subtítulo, `main_id`, ajustes por defecto, 34/0) tras confirmación del usuario. Aviso de recargar (F5) la pestaña con el estado viejo.
**Commit:** NO CONSTA
**Ley que sale de aquí:** "Entorno limpio" hay que verificarlo campo a campo, no por el recuento de filas. Y dos actores escribiendo en la misma BD contaminan las verificaciones de ambos.
**Traza:** #890–#892, #895, #896, #944, #980; `arb_ajustes`, `db/datos-demo.sql`, `db/esquema.sql`.

## [2026-07-08] — Falso fallo: "en móvil como editor no aparece el botón de edición"
**Categoría:** aviso falso
**Síntoma:** El usuario reportó que entrando como editor en móvil no salía el botón de editar.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA: era el comportamiento diseñado.
**Causa raíz:** Decisión de diseño tomada tiempo atrás (móvil y tablet = solo lectura, aunque se entre con clave de edición), olvidada por el usuario.
**Cómo se cazó:** usuario; se aclaró en la conversación.
**Arreglo aplicado:** Ninguno. Se confirmó explícitamente que no se toca. (Quedó apuntado que quizá convendría informar en móvil de que la edición solo está en escritorio — no se hizo.)
**Commit:** NO CONSTA
**Ley que sale de aquí:** Una decisión de diseño que sorprende al propio dueño del proyecto necesita un cartel en la interfaz.
**Traza:** #892–#895; `matchMedia` coarse / ≤1024px, `dispositivo.js`.

## [2026-07-08] — "Importar datos (JSON)" no importaba nada: solo pintaba en pantalla
**Categoría:** datos
**Síntoma:** El botón "Importar datos" del panel cargaba el JSON en la vista pero no lo guardaba en BD, al lado de "Copias → Restaurar" que sí hace la recuperación real. Confusión directa con riesgo de creer que se ha restaurado.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Import/Export figuraba como pieza "✅ existe / solo hay que traerla al panel" en la tabla de inventario del panel (#860), y se integró y verificó como tal (20/20).
**Causa raíz:** Función heredada de una etapa anterior del proyecto que quedó obsoleta al construirse el sistema de copias, y nadie la retiró.
**Cómo se cazó:** ojo humano (Claude Code, al explicar el panel "en cristiano" a petición del usuario; lo señaló como punto a pulir).
**Arreglo aplicado:** "Importar datos" eliminado del panel y del código (`importarDatos`, `#ficheroImportar`, sus manejadores). "Exportar datos (JSON)" se queda con nota que remite a Copias → Restaurar desde archivo.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Explicar la app en cristiano a un humano destapa lo que ningún test detecta: funciones que mienten sobre lo que hacen.
**Traza:** #910, #911, #924, #926, #930; `datos.js` (`importarDatos`, `#ficheroImportar`), bloque Datos de `admin.js`.

## [2026-07-08] — El panel de administración daba saltos de tamaño al cambiar de pestaña
**Categoría:** visual
**Síntoma:** En escritorio, la ventana del panel se agrandaba o encogía según el contenido de cada pestaña; el marco "saltaba".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ **El panel se entregó con 20/20 en edición**, incluida la comprobación de "panel abre/cierra bien" y "los 5 bloques" — se probó que cada pestaña funciona, no que el marco se quede quieto entre ellas.
**Causa raíz:** La caja del modal se dimensionaba al contenido, sin altura fija.
**Cómo se cazó:** ojo humano (usuario).
**Arreglo aplicado:** `.admin-caja` con altura fija `min(640px, 88vh)` + scroll interno donde hace falta. Verificado: las 5 pestañas miden 640 px exactos.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un modal con pestañas necesita altura fija. Si el marco respira, el diseño se lee como improvisado.
**Traza:** #936, #937, #943, #944; `.admin-caja`, `admin.js`.

## [2026-07-08] — La ficha de escritorio se dio por "afinada" y no había cambiado nada
**Categoría:** silencio falso
**Síntoma:** "En el modo vista de una persona de escritorio no hizo nada, está como siempre".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ **La Tanda C se entregó con ✅ y 22/22 en la prueba principal**, declarando que la ficha de escritorio se había "afinado hacia el look del panel". El backtest verificaba que el contenido seguía intacto (raíz/intermedia/hoja) — es decir, comprobaba que **no había cambiado**, y lo daba por verde.
**Causa raíz:** Claude Code, al investigar, vio que la ficha "ya era un modal centrado por secciones" y redujo el trabajo a un retoque imperceptible; el criterio de verificación no incluía "¿se nota el rediseño?".
**Cómo se cazó:** ojo humano (usuario, al mirarlo).
**Arreglo aplicado:** Rediseño real: secciones como tarjetas con borde redondeado y cabecera de barra tintada del color del sexo, con icono y título, en el lenguaje del panel.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un test que solo comprueba que nada se ha roto da verde cuando no has hecho nada. Los cambios visuales se verifican mirándolos.
**Traza:** #938–#941, #943, #944; `fichaLecturaBonita`, `body.ficha-modal`, `.fl-header`.

## [2026-07-08] — El color de acento de la app era literalmente el mismo azul que "hombre"
**Categoría:** visual
**Síntoma:** Los botones del formulario de persona y del panel eran azules, chocando con el código azul=masculino / rosa=femenino.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Todos los backtests visuales previos ("ambos temas, 0 errores de consola", capturas revisadas) pasaron durante días de trabajo con la colisión de color viva. Ya había aflorado antes disfrazada de "halo azul del buscador" (#786) y se parcheó **solo el halo**, sin buscar la raíz.
**Causa raíz:** `--accent` valía exactamente `#3b93d6`, el mismo valor que `--male`. Todo lo "de acción" heredaba el color del sexo masculino.
**Cómo se cazó:** ojo humano (usuario, aplicando su propio criterio de coherencia por segunda vez).
**Arreglo aplicado:** `--accent` repuntado a teal (`#0f857d` claro / `#148f84` oscuro, con `--accent-hover` y `--accent-ring`): cambia de golpe Guardar, Subir imagen, toggle de fecha, "Editar árbol" activo, pestañas del panel, "Vista", copias, título, focos. `--male`/`--female` reservados solo para sexo.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Cuando un síntoma de color aparece dos veces, la causa está en las variables, no en el sitio. Arreglar el halo sin mirar `--accent` fue parchear el síntoma.
**Traza:** #952–#960; variables CSS `--accent`, `--male`, `--female`, `--accent-hover`, `--accent-ring`.

## [2026-07-08] — Un lápiz nativo de la librería colgando junto al botón de añadir familiar
**Categoría:** visual
**Síntoma:** "Al lado del muñequito con el más hay otro icono que hay que ocultar porque no pinta nada".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA. El formulario de edición se había verificado repetidamente (41/41 en D-b, y antes) sin que nadie mirara ese icono.
**Causa raíz:** `.f3-edit-btn`, botón nativo de family-chart, redundante porque el modo edición se controla con el toggle propio "Editar árbol".
**Cómo se cazó:** ojo humano (usuario).
**Arreglo aplicado:** `.f3-form-cont .f3-edit-btn { display:none }`, sin tocar `.f3-add-relative-btn`. Verificado que añadir familiar sigue funcionando.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Los restos de la interfaz nativa de una librería que has envuelto se quedan visibles hasta que alguien los mira.
**Traza:** #960, #961, #965, #966; `.f3-edit-btn`, `.f3-add-relative-btn`, `.f3-form-cont`.

## [2026-07-08] — La ficha de lectura crecía y encogía al abrir el acordeón (el mismo fallo ya corregido en el panel)
**Categoría:** visual
**Síntoma:** La ventana de la ficha "salta" según abres o cierras secciones del acordeón.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ **El acordeón de escritorio se entregó con 29/29 verde**, verificando que "el cuerpo colapsa a 0 y vuelve" — la prueba medía el colapso de la sección, no la altura de la ventana contenedora.
**Causa raíz:** La ficha se dimensionaba al contenido. Es exactamente el mismo defecto que se había arreglado días antes en el panel (#937) y no se generalizó.
**Cómo se cazó:** ojo humano (usuario, que además señaló "este problema ya lo corregimos en las ventanas de configuración").
**Arreglo aplicado:** Mismo patrón que el panel: `height: min(450px, 86vh)` + scroll interno (luego reajustado, ver siguiente entrada).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un arreglo que solo se aplica en un sitio no es un arreglo, es un parche. Si el patrón vale para el panel, vale para toda ventana con contenido variable.
**Traza:** #960, #961, #965, #966; `body.ficha-modal`, `.admin-caja` (patrón de referencia).

## [2026-07-08] — La altura fija de la ficha dejaba media pantalla vacía
**Categoría:** visual
**Síntoma:** Con la altura fija recién puesta (450 px), sobraba ~50% de pantalla en márgenes superior e inferior.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ **17/17 verde**, incluyendo "busqué una altura cómoda" — el test comprobaba que la altura era **estable** (450,450,450,450,450), no que fuera **adecuada**.
**Causa raíz:** Se eligió un valor de altura sin contrastarlo con la proporción real en pantalla.
**Cómo se cazó:** ojo humano (usuario, midiendo a ojo el sobrante).
**Arreglo aplicado:** `min(450px, 86vh)` → `min(720px, 74vh)`: márgenes de ~26%, altura estable (629 px constantes), scroll interno cuando toca.
**Commit:** NO CONSTA
**Ley que sale de aquí:** "Estable" no es "bien proporcionado". Un test de estabilidad no valida una decisión de diseño.
**Traza:** #966, #967, #968; `body.ficha-modal`.

## [2026-07-08] — Una raya de más cruzando la ficha
**Categoría:** visual
**Síntoma:** Línea horizontal que recorría la ventana de izquierda a derecha justo encima del acordeón de "Nacimiento y fallecimiento".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La ficha rediseñada se había entregado con capturas "correctas" revisadas y 12/12 (#944) con la raya dentro de la captura.
**Causa raíz:** `border-bottom` de `.fl-header`, sobrante tras el rediseño de la cabecera.
**Cómo se cazó:** ojo humano (usuario).
**Arreglo aplicado:** Eliminado el `border-bottom` de `.fl-header`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Revisar una captura no es mirarla. Un humano ve la raya que el que la generó no vio.
**Traza:** #966, #967, #968; `.fl-header`.

## [2026-07-08] — El test de tooltips dio 2 fallos que no existían
**Categoría:** aviso falso
**Síntoma:** "Verificación funcional: 11/13 aserciones automáticas; los 2 'fallos' eran errores de mi test (orden invertido en un tooltip y comparé con el nombre del contenedor)".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Invertido: el test dio ROJO sobre código correcto. Dos aserciones mal escritas.
**Causa raíz:** Aserciones del test mal construidas (esperaban el texto en otro orden y apuntaban al contenedor en vez de al botón).
**Cómo se cazó:** el propio Claude Code, al investigar los fallos en vez de reportarlos.
**Arreglo aplicado:** Ninguno en la app; se corrigió el criterio del test.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un test rojo puede ser un test roto. Investiga siempre antes de "arreglar" la app.
**Traza:** #890; botonera de iconos (`title` + `aria-label`).

## [2026-07-08] — El backtest de D-a dio 7 fallos falsos por una comprobación auxiliar mal hecha
**Categoría:** aviso falso
**Síntoma:** En la primera corrida del backtesting de 136 flujos salieron 7 "fallos" en huecos de "hijo" al borde del árbol.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Invertido: dio ROJO sobre comportamiento correcto. El formulario en `display:none` NO tapaba el hueco y el clic sí abría la fase B.
**Causa raíz:** La comprobación auxiliar usaba `elementFromPoint` y devolvía falsos negativos en los huecos del borde.
**Cómo se cazó:** el propio Claude Code, diagnosticando en lugar de aceptar el resultado.
**Arreglo aplicado:** Corregido el criterio del test → 98/98 (los 38 N/A eran padre/madre estructuralmente imposibles).
**Commit:** NO CONSTA
**Ley que sale de aquí:** El instrumento de medida también falla. Un fallo que no puedes reproducir a mano probablemente sea del test.
**Traza:** #976, #977; backtest de la coreografía de "añadir familiar", `elementFromPoint`.

## [2026-07-08] — Pulsar "Deshacer" una vez vaciaba el árbol: 33 personas a la papelera
**Categoría:** datos
**Síntoma:** El primer "Deshacer" (↶) tras cargar el árbol lo colapsaba a 1 persona y mandaba las otras 33 a la papelera.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ **TODO.** El bug era **pre-existente** y sobrevivió a: 18/18 del PASO 11 (copias/restauración), 108/108 del ciclo de entrada, 98/98 de la coreografía de añadir familiar, 20/20 + 5/5 del panel, 22/22 de las tandas A-B-C, 41/41 de D-b, y todos los "smoke tests de no romper". Ninguna suite había pulsado nunca el botón Deshacer.
**Causa raíz:** El historial de family-chart tiene como base el nodo de arranque (`__arranque__`, 1 nodo) creado al montar el chart. Cargar el árbol real con `updateData` NO registra un punto de historial → el primer undo revierte a ese estado de 1 nodo, y `persistir.js` interpreta que faltan 33 personas y las manda a la papelera.
**Cómo se cazó:** casualidad (Claude Code, backtesteando la lógica colgada de D-b se topó con la mina).
**Arreglo aplicado:** Dos capas. (1) Raíz: `reiniciarHistorial()` en arbol.js — tras cargar, re-crear el historial con `f3Edit.createHistory()` para que su base sea el árbol completo; el botón Deshacer queda deshabilitado hasta que haya un cambio real. (2) Red de seguridad en `persistir.js`: si el árbol colapsa a ≤1 persona teniendo ≥5 previas, no persistir y recargar de la BD. Backtest 11/11 con recuento real en BD.
**Commit:** NO CONSTA
**Ley que sale de aquí:** El botón que nadie prueba es el que borra la base de datos. Toda función destructiva entra en la suite, aunque "lleve ahí desde el principio".
**Traza:** #980–#985; `arbol.js` (`reiniciarHistorial`), `app.js`, `persistir.js`, `f3Edit.createHistory()`, `updateData`, `exportData`, `.f3-history-controls`; `PENDIENTES.md` §"ARREGLO CRÍTICO", `ESTADO-Y-DECISIONES.md` §2.

## [2026-07-08] — "Cambié el sexo de una persona, le di a deshacer y se descojonó todo"
**Categoría:** rompe
**Síntoma:** Minutos después de dar por arreglado el deshacer, el usuario cambió el sexo de una persona, pulsó Deshacer y el árbol se destruyó: no cargaba nada. Estado real: 1 persona activa (id 35, **nombre vacío**, sexo M) + las 34 en papelera.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ **El arreglo del deshacer se había entregado con 11/11 verde, con recuento real en la BD, y con el mensaje "Tus datos ya están protegidos".** El código estaba bien; lo que estaba mal era lo que corría en el navegador del usuario.
**Causa raíz:** El navegador servía el **JavaScript viejo cacheado** (anterior al arreglo). No había cache-busting en los assets, así que la app publicada y la app probada eran versiones distintas. El id 35 con nombre vacío era el nodo `__arranque__` persistido como persona real.
**Cómo se cazó:** usuario (destruyendo el demo con sus propias manos); Claude Code inspeccionó el estado roto **antes** de recuperarlo y reprodujo el flujo en navegador limpio, donde funcionaba.
**Arreglo aplicado:** (1) `index.html` → `index.php` con helper `asset()` basado en `filemtime` → `?v=<mtime>` en todos los CSS/JS propios y librerías locales. (2) Red de seguridad de `persistir.js` reforzada: rechaza cualquier estado con el nodo `__arranque__`, colapso a ≤1 con ≥5 previas, o borrado masivo (>3 personas y >⅓ del árbol). (3) Backtest brutal 24/24: cada campo (incl. SEXO) × editar/deshacer/rehacer, con recuento real en BD.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Verificar en tu navegador limpio no verifica el navegador del usuario. Sin cache-busting, cada despliegue es una lotería de versiones — y un bug ya arreglado puede seguir destruyendo datos.
**Traza:** #986–#996, #1000; `index.php`, `asset()`, `filemtime`, `persistir.js`; `PENDIENTES.md` §"CACHE-BUSTING".

## [2026-07-08] — Diagnóstico erróneo: se culpó a la red de seguridad cuando era la caché
**Categoría:** aviso falso
**Síntoma:** Ante el desastre del cambio de sexo, se afirmó que era "otro caso del mismo bug que la red de seguridad no cubría" y se mandó un prompt entero partiendo de esa premisa falsa.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA: fue una hipótesis emitida sin comprobar nada.
**Causa raíz:** Se dedujo la causa desde el síntoma sin inspeccionar el estado ni preguntar por la caché, teniendo un arreglo recién desplegado que el usuario no había recargado.
**Cómo se cazó:** Claude Code, al inspeccionar el estado roto y reproducir en navegador limpio: "el cambio de sexo + deshacer FUNCIONA… tu navegador estaba ejecutando el JavaScript VIEJO cacheado".
**Arreglo aplicado:** Se corrigió el diagnóstico. El refuerzo de la red de seguridad se aplicó igualmente (era pedido y útil), pero por el motivo equivocado.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Antes de acusar al código, pregunta qué versión del código se está ejecutando. Ctrl+Shift+R es el primer paso del diagnóstico, no el último.
**Traza:** #986–#989, #992, #993; `persistir.js`.

## [2026-07-08] — PENDIENTES.md arrastra casillas sin marcar de cosas ya hechas
**Categoría:** datos
**Síntoma:** "El bloque G de PENDIENTES.md arrastra checkboxes antiguos sin marcar que en realidad ya están hechos (login, papelera, backups, título/subtítulo, fotos, centrado por login)"; seguía igual al final del día.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Los documentos se "actualizaban" al cierre de cada paso (#896, #1002) sin reconciliar las secciones históricas.
**Causa raíz:** Documentación de estado mantenida por adición, sin pasada de reconciliación.
**Cómo se cazó:** ojo humano (Claude Code lo declaró al dar el inventario, ofreciendo "la foto real y reconciliada, no la literal").
**Arreglo aplicado:** **Ninguno.** Sigue pendiente al cierre del volcado (ofrecido, no hecho). Reconciliado el 2026-07-09.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un documento de estado que miente en la mitad de sus casillas no es un documento de estado.
**Traza:** #803, #1002; `PENDIENTES.md`, `ESTADO-Y-DECISIONES.md`.

## [2026-07-08] — Subir una foto y cerrar sin guardar deja el archivo colgado en el servidor
**Categoría:** carencia
**Síntoma:** Ficheros huérfanos acumulándose en `almacen/fotos/`.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El paso de "fotos como archivo" está cerrado y aprobado, y todos los backtests de foto ("subir foto", "control + vista previa") dieron verde: comprobaban el caso feliz, no el abandono.
**Causa raíz:** La subida escribe en disco antes de que el usuario confirme el guardado, y no hay compensación si cancela.
**Cómo se cazó:** ojo humano (inventario de pendientes).
**Arreglo aplicado:** **Ninguno** en su momento. Anotado como "higiene, recomendable antes de publicar". Resuelto como SEC-15 (2026-07-09).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Toda escritura previa a una confirmación necesita su compensación. Si no, gotea.
**Traza:** #803, #805, #917, #1002; `almacen/fotos/`, formulario de persona.

## [2026-07-08] — Exportar JSON dejó de incluir las fotos y nadie lo decidió
**Categoría:** datos
**Síntoma:** Al pasar las fotos a archivo en disco, el JSON exportado ya no las embebe como antes; el export quedó incompleto sin que se tomara una decisión.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ "Exportar datos (JSON)" siguió publicitándose y verificándose como función válida (se integró en el panel y se comprobó que "sigue descargando"), sin comprobar **qué** descarga.
**Causa raíz:** Cambio de almacenamiento de fotos (base64 → archivo) que rompió el contrato del exportador, sin revisar el exportador.
**Cómo se cazó:** ojo humano (inventario de pendientes).
**Arreglo aplicado:** **Ninguno** en su momento. Decisión aplazada. Retomado el 2026-07-10.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Cambiar dónde se guarda un dato rompe a todo el que lo lee. Hay que ir a buscarlos uno por uno.
**Traza:** #803, #917, #1002; `datos.js` (exportar JSON), `almacen/fotos/`, `src/Backup.php`.

## [2026-07-08] — Personas activas sin nombre: restos de datos corruptos en el árbol
**Categoría:** datos
**Síntoma:** Existen (o pueden existir) personas activas con el nombre vacío, "restos raros de importaciones o versiones antiguas".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ NO CONSTA prueba sobre esto; y el bug del `__arranque__` (#992) demostró que la app **seguía siendo capaz de generarlos** (el id 35 con nombre vacío que apareció al romperse el árbol).
**Causa raíz:** Ausencia histórica de campos obligatorios y el nodo de arranque persistiéndose como persona.
**Cómo se cazó:** ojo humano (inventario).
**Arreglo aplicado:** Herramienta "Personas sin nombre" en el bloque Datos del panel (`api/mantenimiento.php` + `Personas::sinNombre/renombrar`): listar, renombrar o mandar a papelera. Es una **limpieza**, no una prevención (la prevención llegó con los campos obligatorios y la red de seguridad).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Cuando construyes una herramienta para limpiar datos corruptos, pregúntate primero quién los está creando.
**Traza:** #803, #858, #870, #910, #992; `api/mantenimiento.php`, `Personas::sinNombre`, `Personas::renombrar`.

## [2026-07-08] — Los rectángulos del mini-árbol siguen con colores de la librería, fuera del código azul/rosa
**Categoría:** visual
**Síntoma:** Tras unificar la paleta, los rectángulos del mini-árbol sobre las tarjetas siguen usando las variables propias de family-chart (`--male-color`/`--female-color`, azul-gris y rosa apagados), distintas del azul/rosa vivo de la app.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ **El backtest del cambio de color dio 29/29**, incluyendo "azul/rosa de sexo preservados" — la comprobación miraba las variables de la app, no las de la librería.
**Causa raíz:** La librería pinta con su propio juego de variables, que el repunte de la paleta propia no toca.
**Cómo se cazó:** el propio Claude Code lo declaró como "nota honesta" al entregar.
**Arreglo aplicado:** **Ninguno.** Dejado como ajuste aparte.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Una app que envuelve una librería tiene dos paletas. Unificar la tuya deja la otra fuera de sitio.
**Traza:** #960, #961; `--male-color`, `--female-color` (family-chart), clases `card-male`/`card-female`.

## [2026-07-08] — El asistente se adelantaba y preparaba prompts antes de escuchar
**Categoría:** carencia
**Síntoma:** "Hay más cosas, no solo eso, que has corrido mucho"; el asistente reconoce: "Tienes razón, me he adelantado **otra vez**".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** El asistente cerraba listas y montaba prompts cuando el usuario había dicho que tenía más cosas que contar; se saltaba requisitos.
**Cómo se cazó:** usuario (parándolo, dos veces).
**Arreglo aplicado:** Corrección de comportamiento en la conversación ("no preparo nada hasta que me lo pidas"), sin garantía estructural.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Cerrar la lista de requisitos antes de que el usuario haya terminado de hablar es construir sobre requisitos incompletos.
**Traza:** #844, #845, #847, #902, #903.

## [2026-07-08] — Se olvidaba pedir el backtesting en los prompts
**Categoría:** silencio falso
**Síntoma:** "Pídele también backtesting, que se te olvida siempre".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Los prompts anteriores (tandas A-B-C, entre otros) se lanzaron **sin exigir backtesting exhaustivo**, y sus resultados se aceptaron como verificados. Justo en ese bloque es donde aparecieron después los tres fallos de pulido no detectados (#936, #938) y donde dormía el bug del deshacer.
**Causa raíz:** Omisión sistemática del asistente al redactar los prompts: la verificación no era una parte obligatoria de la petición, sino algo que se pedía "si se acordaba".
**Cómo se cazó:** usuario.
**Arreglo aplicado:** El asistente se comprometió a incluir la exigencia de backtesting en todos los prompts a partir de ese punto (y lo hizo: #943, #959, #965, #975, #983).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si la verificación no está escrita en la petición, no ha existido. "Verifícalo" es parte del requisito, no un extra.
**Traza:** #928, #929.

---

# 2026-07-09 — El día de la auditoría (QA + seguridad: los hallazgos SEC/INT/VAL/CONC/CAL/HIG)

> Los fallos con código (SEC-01…, INT-01…, etc.) cruzan dos fuentes: la conversación
> del día y `docs/PLAN-QA-SEGURIDAD.md`. Donde el volcado juntó varios en uno, aquí van
> separados (una entrada por fallo), con el detalle de la documentación.

## [2026-07-09] — El "Cancelar" que no cancelaba
**Categoría:** rompe
**Síntoma:** El botón "Cancelar" nativo del formulario de edición no cerraba el formulario: pasaba a vista de lectura.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El backtesting del mensaje #1012 declaró "COREOGRAFÍA (A→B→cancelar): 98 OK / 0 fallos" y "CASOS LÍMITE: 4/4 (Escape · clic tarjeta real · volver al inicio · **salir de edición**)" — todo verde, 0 errores de consola, con el Cancelar sin cerrar de verdad.
**Causa raíz:** El botón nativo de family-chart no invoca `closeForm`; su comportamiento por defecto es volver a la vista de lectura, y nadie lo había comprobado.
**Cómo se cazó:** casualidad — salió al hacer la mejora de quitar la X, no como objetivo de la tarea.
**Arreglo aplicado:** Cerrar de verdad vía `closeForm` oficial; en modo "añadir familiar" además sale del modo y limpia los huecos.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un test que dice "cancelar OK" 98 veces sin comprobar que el formulario DESAPARECE no está probando cancelar, está probando que no explota.
**Traza:** #1017–#1019; `ficha.js`, `estilos.css`, `closeForm`.

## [2026-07-09] — La normalización de botones del panel que no hacía falta
**Categoría:** aviso falso
**Síntoma:** El usuario reporta "en el menú de configuraciones hay botones en gris y otros en el verde... habría que normalizar todos esos botones".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (no había fallo: la auditoría posterior demostró que el panel ya cumplía el criterio estándar).
**Causa raíz:** El criterio de color SÍ estaba aplicado (teal=confirmar, rojo=destructivo, gris=navegar), pero no estaba explicado, así que se leyó como "mezclados sin criterio".
**Cómo se cazó:** test — la auditoría de las 5 pestañas en claro y oscuro concluyó "sin cambios de código en el panel (ya conforme)".
**Arreglo aplicado:** Ninguno en código. Se confirmó el criterio y se dejó como estaba.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Antes de "normalizar" algo, audita si ya está normalizado: media tarea puede ser cero código.
**Traza:** #1014, #1017–#1019; `.admin-*` (no tocados).

## [2026-07-09] — Una sección bonita y cuatro planas
**Categoría:** visual
**Síntoma:** Tras crear la sección enmarcada "Añadir familiar", el resto de secciones del formulario (Datos personales · Fechas · Familia/pareja · Foto) quedaron planas, sin marco.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El backtest de #1012 dio "19/19" al botón nuevo y "24/24" de regresión del formulario sin detectar la incoherencia visual que dejaba.
**Causa raíz:** Se estilizó solo la sección nueva, sin propagar el patrón al resto del formulario.
**Cómo se cazó:** usuario ("el resto de secciones dentro de ese formulario tienen que tener la misma estética").
**Arreglo aplicado:** Reforzar el marco de `.form-seccion` (borde + cabecera) para todas.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Introducir un patrón visual nuevo en un sitio es crear deuda de incoherencia en todos los demás.
**Traza:** #1014, #1017, #1018; `estilos.css`, `.form-seccion`, `.seccion-anadir`.

## [2026-07-09] — La cruz redundante del formulario de edición
**Categoría:** visual
**Síntoma:** El formulario de edición tenía una X de cerrar arriba a la izquierda pese a existir ya un botón "Cancelar" abajo.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** La X venía de la librería (family-chart) y se arrastró sin revisarla al añadir los botones propios.
**Cómo se cazó:** usuario ("qué necesidad tiene el tener arriba a la izquierda una cruz teniendo abajo el botón de cancelar").
**Arreglo aplicado:** Ocultar la X solo en modo edición (en lectura sigue su × redondo).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Los controles heredados de la librería también son interfaz tuya: si no los revisas, se quedan duplicando funciones.
**Traza:** #1014, #1017, #1018; `estilos.css`, `ficha.js`.

## [2026-07-09] — El neutro "recomendado" que el usuario no quería
**Categoría:** visual
**Síntoma:** Se unificaron las secciones de datos en NEUTRO (marco gris) y "Añadir familiar" en teal; el usuario dijo "no está todo normalizado" y pidió TODAS en teal.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ "4/4 en el backtest · claro y oscuro (`sx-form-claro/oscuro.png`)" en #1018 — verde sobre un resultado que no era el pedido.
**Causa raíz:** El asistente impuso su criterio ("el teal se reserva para acciones") y lo marcó como "Recomendado" en la pregunta, y el usuario lo aceptó sin que fuera lo que realmente quería.
**Cómo se cazó:** usuario, al ver el resultado en pantalla.
**Arreglo aplicado:** `.form-seccion` pasa al mismo teal que "Añadir familiar"; se eliminan los overrides de `.seccion-anadir`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Marcar tu propia opción como "Recomendado" en un dilema de gusto sesga la respuesta y luego hay que rehacerlo.
**Traza:** #1015–#1018, #1020–#1024; `estilos.css`, `.form-seccion`, `.seccion-anadir`.

## [2026-07-09] — No se podía cerrar el formulario clicando fuera
**Categoría:** carencia
**Síntoma:** Estando en la ventana de edición, un clic fuera no cerraba nada; solo "Cancelar".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** El formulario no es un modal con fondo oscuro (a propósito, para ver el árbol detrás), y nunca se implementó el handler de clic-fuera.
**Cómo se cazó:** usuario.
**Arreglo aplicado:** Handler de clic en documento que llama a `closeForm`, con guard: sale de inmediato si `isAddingRelative()` o `body.anadiendo-familiar` (para no cerrar al elegir hueco).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un formulario sin overlay hereda el problema de que "fuera" sigue siendo zona interactiva: hay que decidir explícitamente qué hace el clic-fuera.
**Traza:** #1020–#1024; `ficha.js`, `closeForm`, `isAddingRelative()`, `body.anadiendo-familiar`.

## [2026-07-09] — El plan de QA solo miraba el comportamiento
**Categoría:** carencia
**Síntoma:** El plan de pruebas de estrés se había redactado entero como caja negra (dar botones), sin auditar el código fuente.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** El asistente enfocó "romper la app" como pruebas de comportamiento y no contempló el Frente B (caja blanca).
**Cómo se cazó:** usuario ("estás fijándote solamente en el comportamiento... que revise también el código fuente en busca de bugs o de arquitectura mala").
**Arreglo aplicado:** Se añadió el FRENTE B — auditoría del código fuente (bugs latentes, manejo de errores, arquitectura frágil, concurrencia).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Caja negra y caja blanca cazan bugs distintos; un plan de QA con una sola de las dos está a medias por definición.
**Traza:** #1032, #1033, #1035, #1036; prompt de `docs/PLAN-QA-SEGURIDAD.md`.

## [2026-07-09] — El plan de QA no contemplaba inyección ni fotos maliciosas
**Categoría:** carencia
**Síntoma:** El plan no incluía anti-inyección (SQL/XSS) ni el vector de subir una "foto" con código ejecutable dentro.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** El plan se enfocó en robustez funcional, no en seguridad de entradas, pese a que la app se iba a publicar en internet.
**Cómo se cazó:** usuario ("hay que evitar también que nos puedan inyectar código... imagina que suben una foto y realmente lleva un código que revienta la BD").
**Arreglo aplicado:** Bloque prioritario de SEGURIDAD DE ENTRADAS / ANTI-INYECCIÓN integrado en el prompt del plan.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si la app acepta subidas de archivos y se publica, el vector de fichero malicioso va en el plan desde la primera versión, no como parche.
**Traza:** #1037–#1040; `Fotos.php`, `foto.php`.

## [2026-07-09] — El instalador se quedó fuera de la auditoría
**Categoría:** carencia
**Síntoma:** El plan de auditoría cubría las ventanas de la app pero no el asistente de instalación (`public/instalar/`), que es donde se manejan credenciales de BD y se crea el admin.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** Omisión del asistente al redactar el plan; lo reconoce explícitamente ("Se me había pasado incluirlo").
**Cómo se cazó:** usuario ("también tiene que repasar a fondo las ventanas de cuando se instala").
**Arreglo aplicado:** Se añadió el punto 7 (INSTALADOR, crítico) al Frente A y `public/instalar/` a la revisión de código.
**Commit:** NO CONSTA
**Ley que sale de aquí:** El instalador es la parte más sensible en seguridad y la primera que se olvida auditar porque "solo se usa una vez".
**Traza:** #1053, #1054; `public/instalar/`, `Instalador.php`.

## [2026-07-09] — El plan no probaba las reglas lógicas del árbol
**Categoría:** carencia
**Síntoma:** El plan de QA no incluía comprobar que la app impide cosas genealógicamente imposibles (segundo padre a quien ya tiene padre, bucles, emparejarse consigo mismo).
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El backtest de #1012 interpretó los 38 casos "N/A" de la coreografía como "a quien ya tiene padre y madre no se le ofrece añadir más" — se dio por bueno que la regla existía porque la UI no ofrecía el botón, cuando la regla NO existía en el servidor (después confirmado como INT-02).
**Causa raíz:** Se confundió "la interfaz no lo ofrece" con "el sistema lo impide".
**Cómo se cazó:** usuario ("ver por ejemplo si alguien tiene ya un padre que no deje volver a poner un padre").
**Arreglo aplicado:** Área 1 del plan (VALIDACIONES LÓGICAS DEL ÁRBOL, crítico) añadida; derivó en INT-01/INT-02/INT-04.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Que la UI no ofrezca el camino no significa que la regla exista. Las reglas de dominio se prueban contra el servidor, no contra los botones.
**Traza:** #1030, #1031; `Relaciones.php`, `Fechas.php`.

## [2026-07-09] — Seis mensajes puliendo un prompt sin enviarlo nunca
**Categoría:** carencia
**Síntoma:** El usuario tuvo que preguntar "vale pues ahora qué le mandamos a claude code porque no hemos mandado nada aún".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** El asistente reescribió el prompt del plan de QA en #1029, #1031, #1033, #1036, #1038, #1040 sin que ninguno llegara a ejecutarse.
**Cómo se cazó:** usuario.
**Arreglo aplicado:** Se montó el prompt completo definitivo y se envió.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Iterar el enunciado seis veces sin ejecutarlo una es procrastinación disfrazada de rigor.
**Traza:** #1045, #1046.

## [2026-07-09] — El listón del plan: "histeria" → "proporcionado" → "máximo"
**Categoría:** carencia
**Síntoma:** El nivel de exigencia del plan de QA se fijó tres veces contradiciéndose.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** Se ajustó el listón a la baja ("riguroso y proporcionado, no histérico", #1048) sin conocer un dato crítico que el usuario dio dos mensajes después: el proyecto se publica en GitHub abierto y podría comercializarse (#1051), lo que obligó a subirlo al máximo (#1053).
**Cómo se cazó:** usuario.
**Arreglo aplicado:** Prompt final con "NIVEL DE EXIGENCIA — MÁXIMO PROFESIONAL" + contexto de código público y auditable.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Calibrar el rigor antes de saber quién va a leer el código lleva a calibrarlo mal.
**Traza:** #1047, #1048, #1051–#1054; prompt de `docs/PLAN-QA-SEGURIDAD.md`.

## [2026-07-09] — QA y "Paso 13" eran lo mismo y estaban dispersos
**Categoría:** carencia
**Síntoma:** El bloque de seguridad del plan de QA solapaba con el "Paso 13" (endurecimiento) que llevaba tiempo planificado aparte.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** Planificación acumulada: la seguridad se fue metiendo por trozos en dos sitios distintos sin unificarla.
**Cómo se cazó:** usuario ("no se qué querías aplicar en seguridad... pero no entroncaría todo en un macro bloque?").
**Arreglo aplicado:** Macrobloque "Endurecimiento y Seguridad" en dos fases (auditar → corregir+blindar), fundiendo el Paso 13.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si dos bloques del plan tocan la misma superficie, están destinados a duplicarse o a dejar huecos entre ellos.
**Traza:** #1041–#1044; `PENDIENTES.md`, `ESTADO-Y-DECISIONES.md`.

## [2026-07-09] — SEC-01: el instalador se reabría sin contraseña si caía la BD
**Categoría:** seguridad
**Síntoma:** Si la base de datos deja de responder un momento (normal en hosting compartido), el instalador se reabre SIN contraseña y cualquiera puede tomar el control de la app.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Toda la fase de interfaz se cerró "todo verde" (#1012: 98/0; #1018: 98/98+4/4; #1024: 14/14 + 98/98 + 24/24, 0 errores de consola) con este agujero crítico vivo. Con la BD arriba el cerrojo cerraba → parecía seguro; solo con la BD inalcanzable `estaInstalado()` devolvía false. Confirmado explotable en vivo en el Frente A: "cero falsos positivos".
**Causa raíz:** La marca de "instalado" se comprobaba consultando la BD (fail-open): si la BD no responde, `estaInstalado()` devolvía falso; `escribirConfig` sobrescribía el `config.php` existente mirando solo `is_writable`.
**Cómo se cazó:** ojo humano (auditoría de código, Frente B) + confirmado en el Frente A.
**Arreglo aplicado:** Flag `'instalado' => true` en `config.php` leído ANTES de tocar la BD; `escribirConfig()` se niega a sobrescribir un config existente; `finalizar()` graba el flag; `config.php` en 0600.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un cerrojo de seguridad que depende de un servicio que puede caerse es un cerrojo que se abre solo. Fail-closed, siempre.
**Traza:** #1055, #1060–#1062; `src/Instalador.php:224-233` (`estaInstalado`, `escribirConfig`, `finalizar`), `public/instalar/index.php`; PLAN-QA §1.1/§4.1 SEC-01.

## [2026-07-09] — SEC-02: el "modo abierto" era un secuestro, no una lectura pública
**Categoría:** seguridad
**Síntoma:** Con el árbol en modo abierto (`acceso_activo=0`), cualquier anónimo podía administrar: cambiar las claves y echar al dueño de su propia app.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El modo abierto era una función construida y "verificada" en fases anteriores; nada la marcó como agujero hasta la auditoría. Confirmado explotable en el Frente A (POST anónimo a `seguridad.php` → `{ok:true}`; login con la clave del atacante → `rol:edicion`).
**Causa raíz:** El modelo estaba mal concebido: "abierto" significaba "sin control de acceso" en vez de "lectura pública"; `exigirEdicion()` daba rol edición a todos, `exigirCsrf()` no comprobaba y `reautenticar()` era no-op.
**Cómo se cazó:** ojo humano (auditoría de código) + confirmado en Frente A.
**Arreglo aplicado:** Modelo replanteado: abierto = LECTURA pública; administrar SIEMPRE exige sesión de edición. `exigirEdicion`/`exigirCsrf` exigen sesión real también en abierto; `reautenticar` exige la clave actual siempre.
**Commit:** NO CONSTA
**Ley que sale de aquí:** "Sin login" no puede significar "sin permisos". Público es un rol de lectura, no la ausencia de control.
**Traza:** #1055, #1060–#1062; `src/Auth.php:107,117`, `src/Acceso.php:103` (`reautenticar`), `seguridad.php`; PLAN-QA §1ter/§4.2 SEC-02.

## [2026-07-09] — El botón "Acceder para administrar" que no existía (pero el test decía que sí)
**Categoría:** aviso falso
**Síntoma:** Tras el arreglo de SEC-02, en modo abierto el visitante NO veía por ningún lado el botón "Acceder para administrar" que se había anunciado. El dueño que abriera su árbol y cerrase el navegador se quedaba SIN forma visible de volver a administrar (tendría que tocar la BD a mano).
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐⭐ ESTE ES EL CASO EJEMPLAR. El backtest de S1 declaró el botón como hecho y verificado ("Frontend: en modo abierto se entra en solo-lectura pública con un botón 'Acceder para administrar'"), 10/10 en uso legítimo. Y el propio Claude Code confesó después por qué mintió: *"reutilicé el botón 'Salir' cambiándole solo el `title`; (mi test anterior comprobó el `title` del elemento —que existe aunque el icono engañe—, un punto ciego)"*. El test comprobaba la existencia de un atributo, no la visibilidad ni el significado; además el botón "Salir" está OCULTO en solo-lectura, así que no se veía.
**Causa raíz:** Se reutilizó el botón de "Salir" (icono de puerta) cambiándole solo el `title`; el test se escribió contra el `title`, así que pasaba.
**Cómo se cazó:** usuario, en incógnito, preguntando "cómo cojones recupero el panel de control entonces porque al no estar logado desaparece".
**Arreglo aplicado:** Botón dedicado y siempre visible (píldora teal con icono de llave, texto claro), solo en `body.modo-abierto-anon`, ocultando "Salir". Y se mejoró el método de test para comprobar visibilidad real (display + área en pantalla), no solo existencia del elemento. (Los dos primeros diseños del botón —icono de salir, píldora fija arriba a la derecha— también fallaron antes de dar con este.)
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un test que comprueba `title="Acceder"` sobre un icono de puerta de salida da verde sobre un botón que ningún humano encontraría. Los tests de UI se escriben contra lo que el ojo ve, no contra lo que el DOM dice.
**Traza:** #1073–#1080; `public/index.php`, `estilos.css`, `app.js`, `body.modo-abierto-anon`; PLAN-QA §1ter SEC-02 (nota).

## [2026-07-09] — La llave teal se colaba fuera del modo abierto (especificidad CSS)
**Categoría:** visual
**Síntoma:** El botón de llave "Acceder para administrar" aparecía también fuera del modo abierto anónimo.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (se cazó dentro del propio backtest del cambio, antes de entregarlo).
**Causa raíz:** `.fab-acceder { display:none }` perdía por especificidad contra `.fab { display:flex }`, que va más abajo en el archivo.
**Cómo se cazó:** test (backtest del propio cambio).
**Arreglo aplicado:** Subir la especificidad a `.toolbar .fab-acceder` (0,2,0).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Ocultar con `display:none` en CSS es una apuesta contra la cascada; si la regla que compite va después, pierdes.
**Traza:** #1087, #1088; `estilos.css`, `.fab-acceder`, `.fab`, `.toolbar`.

## [2026-07-09] — SEC-03a: restaurar una copia manipulada permitía inyección SQL
**Categoría:** seguridad
**Síntoma:** El restore de una copia de seguridad tomaba los nombres de columna del propio JSON, así que un fichero manipulado podía inyectar SQL.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El plan de auditoría declaró "la inyección SQL está cerrada en la app" (consultas preparadas) — y aun así el restore la tenía abierta por la vía de los nombres de columna, que no son parametrizables. Confirmado en Frente A (columna `` evil`x `` → `SQLSTATE 1064`).
**Causa raíz:** Nombres de columna venidos del JSON del usuario concatenados entre backticks en la sentencia.
**Cómo se cazó:** ojo humano (auditoría de código) + confirmado en Frente A.
**Arreglo aplicado:** Lista BLANCA de columnas por tabla, con los nombres desde constantes, nunca del JSON.
**Commit:** NO CONSTA
**Ley que sale de aquí:** "Usamos consultas preparadas" no cubre los identificadores (tablas/columnas). Ahí solo protege una lista blanca.
**Traza:** #1055, #1060, #1061; `src/Backup.php:301-311` (`Backup::COLUMNAS`), `backup-restaurar.php`; PLAN-QA §1bis/§4.4 SEC-03b.

## [2026-07-09] — SEC-03b: una copia ajena podía robarte el admin
**Categoría:** seguridad
**Síntoma:** Restaurar una copia sobrescribía las credenciales de administrador y el interruptor de acceso: con un backup preparado, el atacante entraba y el dueño no.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La función "restaurar copia" se daba por buena de fases previas; confirmado en Frente A (backup con hash ajeno → login del atacante como edición).
**Causa raíz:** El restore trataba las tablas de credenciales/ajustes como datos más, restaurándolos desde un fichero controlado por el usuario.
**Cómo se cazó:** ojo humano (auditoría de código) + confirmado en Frente A.
**Arreglo aplicado:** Credenciales e interruptor de acceso PRESERVADOS del árbol actual (`clave_*_hash`, `acceso_activo`, `instalado`, `version_esquema`): un backup nunca cambia las claves de admin ni abre el árbol; `password_hash` de usuarios importados neutralizado.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Los datos de autenticación no son datos de negocio: nunca deben viajar dentro de una copia restaurable.
**Traza:** #1055, #1060–#1062; `src/Backup.php:37,273-276` (`AJUSTES_PROTEGIDOS`); PLAN-QA §1bis/§4.4 SEC-03a.

## [2026-07-09] — SEC-03c: el restore no validaba integridad referencial (INT-08)
**Categoría:** datos
**Síntoma:** Una copia con relaciones apuntando a personas inexistentes, sexo/rol fuera del ENUM, fechas imposibles o auto-referencias se restauraba tal cual, dejando huérfanos.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** El restore inserta con las comprobaciones de claves foráneas desactivadas y no validaba nada después.
**Cómo se cazó:** ojo humano (auditoría de código) + Frente A.
**Arreglo aplicado:** `validarIntegridad` exige FKs consistentes, sexo/rol del ENUM, fechas de calendario real, `nac≤fall` y sin auto-referencias, antes de tocar la BD.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si desactivas las FK para insertar rápido, el que valida la integridad eres tú, y por escrito.
**Traza:** #1055, #1060, #1061, #1102; `src/Backup.php:404-408` (`validarIntegridad`); PLAN-QA §1quinquies/§3.1 INT-08.

## [2026-07-09] — SEC-04a: el freno de fuerza bruta vivía en la sesión del atacante
**Categoría:** seguridad
**Síntoma:** El rate-limit del login se contabilizaba en `$_SESSION`: no enviando la cookie, nunca se acumulaban intentos → fuerza bruta ilimitada.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El freno "funcionaba" en las pruebas normales (con navegador que sí manda cookie): 401×5 → 429. Sin cookie: 10 fallos → siempre 401. Confirmado en Frente A como explotable.
**Causa raíz:** Poner el contador en un estado que el propio atacante controla (su cookie de sesión).
**Cómo se cazó:** ojo humano (auditoría de código) + confirmado en Frente A.
**Arreglo aplicado:** Rate-limit por IP persistido en disco (1 archivo por IP con `flock`, `bloqueado_hasta`), con 429 y backoff exponencial (60s→1h); no evadible cambiando cookie.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un contador anti-abuso guardado en algo que el abusador puede tirar a la basura no es un contador.
**Traza:** #1112, #1113, #1126; `src/LimiteAcceso.php`, `login.php:55-68`, carpeta `ratelimit/`; PLAN-QA §1septies/§4.2 SEC-04.

## [2026-07-09] — SEC-04b: la clave mínima eran 4 caracteres
**Categoría:** seguridad
**Síntoma:** El sistema aceptaba claves de administración de 4 caracteres.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** Longitud mínima puesta pensando en comodidad de demo, nunca revisada de cara a producción.
**Cómo se cazó:** ojo humano (auditoría de código).
**Arreglo aplicado:** Longitud mínima subida de 4 a 8 caracteres.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Los valores cómodos para el demo se quedan puestos en producción salvo que alguien los busque a propósito.
**Traza:** #1112, #1113; `src/Auth.php`, `seguridad.php`; PLAN-QA §4.2 SEC-04.

## [2026-07-09] — SEC-05: los errores 500 contaban la estructura interna
**Categoría:** seguridad
**Síntoma:** Los errores 500 devolvían al cliente el mensaje de la excepción: SQL, `SQLSTATE`, rutas y estructura del sistema.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (confirmado en Frente A: el 500 del restore devolvía el SQL).
**Causa raíz:** `getMessage()` volcado directamente en la respuesta HTTP, en múltiples endpoints.
**Cómo se cazó:** ojo humano (auditoría de código) + confirmado en Frente A.
**Arreglo aplicado:** `Seguridad::registrarError` manda el detalle al log y al cliente solo un mensaje genérico + código `ref`; `display_errors=Off` en producción.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un stack trace en la respuesta HTTP es documentación gratis para el atacante.
**Traza:** #1112, #1113, #1126; `http.php`, `arbol.php`, `backup*.php`, `foto.php`, `sistema.php`, `instalar/index.php`; PLAN-QA §1septies/§4.2 SEC-05.

## [2026-07-09] — SEC-06: salud.php contaba la vida sin pedir credenciales
**Categoría:** seguridad
**Síntoma:** `salud.php` exponía versiones de PHP, extensiones y diagnóstico sin autenticar.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** Endpoint de diagnóstico creado para desarrollo y dejado accesible sin control.
**Cómo se cazó:** ojo humano (auditoría de código) + Frente A.
**Arreglo aplicado:** Protegido por rol (401/403 sin sesión de edición); el diagnóstico sigue en el panel y el instalador.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Todo endpoint de diagnóstico nace público y muere público si nadie lo revisa antes de publicar.
**Traza:** #1112, #1113, #1126; `salud.php`, `api/sistema.php`; PLAN-QA §1septies/§4.2 SEC-06.

## [2026-07-09] — SEC-08: bomba de descompresión en la subida de fotos
**Categoría:** seguridad
**Síntoma:** Un PNG pequeño de 30000×30000 se decodificaba y agotaba la memoria del servidor.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La auditoría declaró "la subida de fotos es sólida (el reprocesado mata los payloads)" — la defensa contra código malicioso era buena, pero la defensa contra agotamiento de recursos no existía.
**Causa raíz:** Se validaba tipo real y se reprocesaba la imagen, pero no se comprobaban las DIMENSIONES antes de decodificar (`imagecreatefromstring`).
**Cómo se cazó:** ojo humano (auditoría de código).
**Arreglo aplicado:** `getimagesize` ANTES de decodificar, con límite de dimensiones (máx 10000 px/lado, 25 MP; rechazo 400).
**Commit:** NO CONSTA
**Ley que sale de aquí:** "La imagen es segura" y "la imagen no me tumba el servidor" son dos comprobaciones distintas, y la segunda va antes de decodificar.
**Traza:** #1112, #1113, #1126; `Fotos::guardarDesdeArchivo`, `foto.php`; PLAN-QA §1septies/§4.3 SEC-08.

## [2026-07-09] — SEC-09: la app salía a internet sin ninguna cabecera de seguridad
**Categoría:** seguridad
**Síntoma:** No había CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy ni HSTS.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** Nunca se añadieron; el proyecto se desarrolló entero en local sin pensar en el perímetro.
**Cómo se cazó:** ojo humano (auditoría de código).
**Arreglo aplicado:** CSP estricta (`script-src 'self'`, posible porque todo el JS es local) + X-Frame-Options DENY, X-Content-Type-Options, Referrer-Policy y HSTS; verificado en navegador real: 16/16 pantallas, CERO violaciones.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Las cabeceras de seguridad no se "notan" hasta que faltan, así que hay que ponerlas por checklist, no por síntoma.
**Traza:** #1112, #1113, #1126; `Seguridad::cabecerasHtml/Base`, `http.php`, `.htaccess`; PLAN-QA §1septies/§4.2 SEC-09.

## [2026-07-09] — SEC-10: el avatar por URL externa filtraba a los lectores
**Categoría:** seguridad
**Síntoma:** El campo de avatar por URL externa (o dataURL) se servía a todos los lectores: fuga de privacidad/tracking hacia el dominio externo y contenido mixto bajo HTTPS.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (confirmado por inspección; avatar URL → 400 tras el arreglo).
**Causa raíz:** El campo de avatar aceptaba una URL arbitraria sin validar ni proxear.
**Cómo se cazó:** ojo humano (auditoría de código).
**Arreglo aplicado:** Solo admite fotos subidas (`<32hex>.jpg`) o vacío; el campo del cliente pasa a oculto y `urlFoto` no sirve URLs externas.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Cada URL externa que tu página carga es una tercera parte a la que estás entregando a tus visitantes.
**Traza:** #1112, #1113, #1126; `Fotos.php`, `foto.php`, `urlFoto`; PLAN-QA §1septies/§4.3 SEC-10.

## [2026-07-09] — SEC-11: fuga del error PDO en el instalador (oráculo de red / SSRF)
**Categoría:** seguridad
**Síntoma:** El instalador devolvía el detalle de los errores PDO al fallar la conexión, sirviendo de oráculo de host/puerto (SSRF/enumeración).
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El instalador nunca se había auditado (se añadió al plan por petición del usuario en #1053) y el propio Claude Code reconoció que no puede recorrerlo en vivo sin reinstalar: se verificó "de forma estática". Confirmado como SEC-11 en la Fase 1 dinámica (mensaje PDO crudo en el camino fail-open).
**Causa raíz:** Se mostraba `$e->getMessage()` crudo en el fallo de conexión.
**Cómo se cazó:** ojo humano (auditoría de código) + confirmado en test.
**Arreglo aplicado:** Mensaje genérico + log; no distingue host abierto/cerrado.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un formulario que prueba una conexión no debe revelar si el host/puerto interno respondió.
**Traza:** #1112, #1113; `Instalador.php:115-117`, `instalar/index.php:77,158`; PLAN-QA §1bis/§4.1 SEC-11.

## [2026-07-09] — SEC-12: catch genérico del instalador expone getMessage()
**Categoría:** seguridad
**Síntoma:** Errores de estructura/finalizar del instalador mostrados tal cual al cliente.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (verificación estática).
**Causa raíz:** El catch genérico mostraba `$e->getMessage()`.
**Cómo se cazó:** ojo humano (auditoría de código).
**Arreglo aplicado:** Registrar en log y mostrar mensaje genérico.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Ningún catch de cara al usuario debe imprimir el mensaje de la excepción.
**Traza:** #1112, #1113; `instalar/index.php:158-160`; PLAN-QA §4.1 SEC-12.

## [2026-07-09] — SEC-13: las credenciales de BD quedaban en el fichero de sesión del instalador
**Categoría:** seguridad
**Síntoma:** `$_SESSION['config_manual']` guardaba el `config.php` completo (contraseña de BD en claro), legible en hosting compartido, y no siempre se limpiaba.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (verificación estática).
**Causa raíz:** El wizard persistía las credenciales en el almacén de sesiones para pasarlas entre pasos.
**Cómo se cazó:** ojo humano (auditoría de código).
**Arreglo aplicado:** Las credenciales viajan en un campo oculto del formulario, no en `$_SESSION`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un secreto no debe reposar en un almacén (sesión) más laxo que su destino.
**Traza:** #1112, #1113; `instalar/index.php:88,101,178`; PLAN-QA §1septies/§4.1 SEC-13.

## [2026-07-09] — SEC-14: IDOR — un lector veía las fotos de la papelera iterando ids
**Categoría:** seguridad
**Síntoma:** Un usuario con rol de LECTURA podía ver fotos de personas que estaban en la papelera llamando a `?persona=N` con distintos N.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ "La autorización por endpoint es uniforme" fue una de las conclusiones tranquilizadoras de la auditoría — uniforme en cuanto a rol, pero sin filtrar el estado de borrado.
**Causa raíz:** `foto.php` no filtraba `borrado_en` ni cruzaba el rol contra el estado de la persona.
**Cómo se cazó:** ojo humano (auditoría de código).
**Arreglo aplicado:** Filtrado de `borrado_en` y del rol: lectura → 404 en papelera; edición → 200.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Autorizar por rol no basta: también hay que autorizar por el estado del recurso (borrado, papelera, borrador).
**Traza:** #1112, #1113, #1126; `foto.php`; PLAN-QA §1septies/§4.2 SEC-14.

## [2026-07-09] — SEC-15: las fotos huérfanas crecían sin límite
**Categoría:** datos
**Síntoma:** Subir una foto sin guardar, o re-subir otra, dejaba archivos en disco que nunca se borraban: crecimiento ilimitado.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (constaba desde el 07-jul como "pendiente de las fotos sueltas").
**Causa raíz:** La foto se sube y se persiste en disco antes de que exista el vínculo con la persona guardada; si el guardado no llega, nadie limpia.
**Cómo se cazó:** ojo humano (auditoría de código).
**Arreglo aplicado:** `Fotos::purgarHuerfanas` (`mantenimiento.php?accion=limpiar_fotos`) borra las no referenciadas con >24 h de gracia; verificado purgado en disco Y BD.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Todo fichero que se escribe antes de confirmarse la transacción necesita un recolector, o es una fuga de disco garantizada.
**Traza:** #1112, #1113, #1126; `Fotos::purgarHuerfanas`, `mantenimiento.php`; PLAN-QA §1septies/§4.3 SEC-15.

## [2026-07-09] — SEC-16: carrera del administrador inicial (instalador sin cerrar)
**Categoría:** seguridad
**Síntoma:** Si la app queda subida y sin instalar, el primer visitante que complete el asistente fija el admin (toma la app).
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** Inherente a instaladores web: el primer visitante que instala se convierte en dueño.
**Cómo se cazó:** ojo humano (auditoría de código).
**Arreglo aplicado:** Documentado (no código): instalar inmediatamente tras desplegar; el cerrojo cierra al terminar.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Una superficie de instalación abierta es una ventana de secuestro; ciérrala en la misma sesión de despliegue.
**Traza:** #1130, #1131; `public/instalar/`, flag `instalado`; DESPLIEGUE-Y-SEGURIDAD §"Puesta en marcha", PLAN-QA §4.1 SEC-16.

## [2026-07-09] — SEC-17: la pantalla de requisitos revela PHP/extensiones sin auth
**Categoría:** seguridad
**Síntoma:** El asistente muestra versión de PHP y extensiones antes de instalar y sin autenticación.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** La pantalla de requisitos es pre-instalación y no puede autenticar.
**Cómo se cazó:** ojo humano (auditoría de código).
**Arreglo aplicado:** Documentado; opción de retirar `public/instalar/` tras instalar para eliminar la fuga.
**Commit:** NO CONSTA
**Ley que sale de aquí:** La información de diagnóstico pre-auth es de bajo valor pero no nulo; retírala cuando ya no se necesite.
**Traza:** #1130, #1131; `public/instalar/`; DESPLIEGUE-Y-SEGURIDAD §5, PLAN-QA §4.1 SEC-17.

## [2026-07-09] — SEC-18: config.php se escribía sin restringir permisos
**Categoría:** seguridad
**Síntoma:** El fichero de config con credenciales quedaba con permisos por defecto (umask).
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** El instalador no aplicaba permisos restrictivos tras escribir el config.
**Cómo se cazó:** ojo humano (auditoría de código).
**Arreglo aplicado:** El instalador deja `config/config.php` en 0600 (no-op en Windows; protege en Linux).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un fichero con secretos se crea ya con permisos mínimos, no se deja al umask.
**Traza:** #1130, #1131; `Instalador` (escritura de config); PLAN-QA §1decies/§4.1 SEC-18.

## [2026-07-09] — SEC-19: login por "identidad" no secreta, sin documentar
**Categoría:** seguridad
**Síntoma:** La identidad de login (nombre + apellido + fecha) es pública para cualquier lector del árbol; toda la seguridad recae en la clave de rol, y eso no estaba documentado.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** Modelo de acceso "Forma 1": identidad pública + clave compartida por rol; un `409` por homónimos es esperado.
**Cómo se cazó:** ojo humano (auditoría de código).
**Arreglo aplicado:** Documentado como característica del modelo: tratar la clave de edición como la contraseña de admin real.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si un factor de login es público, no cuenta como secreto; toda la seguridad vive en el otro factor.
**Traza:** #1130, #1131; `api/login.php`; DESPLIEGUE-Y-SEGURIDAD §"Modelo de acceso", PLAN-QA §4.2 SEC-19.

## [2026-07-09] — SEC-20: cookie no-Secure tras proxy TLS, sin HSTS y logout sin CSRF
**Categoría:** seguridad
**Síntoma:** La cookie de sesión no se marcaba Secure de forma robusta tras un proxy TLS, no había HSTS, y el logout no exigía CSRF.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La auditoría concluyó que "las sesiones [están] bien" y "el CSRF y las sesiones se exigen bien" — y sin embargo el logout no lo exigía y la cookie no era Secure.
**Causa raíz:** La detección de HTTPS no contemplaba `X-Forwarded-Proto` (hosting tras proxy TLS); el logout se consideró acción inocua y quedó fuera de la protección CSRF.
**Cómo se cazó:** ojo humano (auditoría de código).
**Arreglo aplicado:** Cookie Secure robusta vía `X-Forwarded-Proto`, `session.use_strict_mode`, HSTS, y logout con CSRF (403/200).
**Commit:** NO CONSTA
**Ley que sale de aquí:** "El CSRF está bien" es falso mientras exista UNA acción que cambia estado sin token, aunque parezca inofensiva.
**Traza:** #1112, #1113, #1126; `src/Auth.php`, `http.php`, `logout.php`; PLAN-QA §1septies/§4.2 SEC-20.

## [2026-07-09] — INT-01: bucles de ascendencia → el árbol entra en recursión infinita y se cuelga
**Categoría:** rompe
**Síntoma:** Si se creaba que A es antepasado de B y B de A (o cadenas más largas), el árbol entraba en recursión infinita y se colgaba.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Solo se bloqueaba A→A directo, y eso bastó para dar la validación por existente. La coreografía de añadir familiar se probó 98/98 sin que ningún test intentara cerrar un ciclo. El servidor ACEPTÓ el cierre del ciclo (2-ciclo y 3-ciclo).
**Causa raíz:** No había detección de ciclos en la creación de la arista de filiación; ninguna longitud >1 estaba cubierta.
**Cómo se cazó:** ojo humano (auditoría de código) + confirmado explotable en Frente A.
**Arreglo aplicado:** BFS con visitados sobre la descendencia del hijo, rechazando la arista si el progenitor ya es descendiente. Cualquier longitud. Respaldo por cerrojo `FOR UPDATE`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Bloquear el caso trivial (A→A) crea la ilusión de que la clase entera de bugs está cubierta.
**Traza:** #1055, #1060, #1090–#1092; `Relaciones.php:31-45` (`validarSinCiclo`); PLAN-QA §1quater/§3.1 INT-01.

## [2026-07-09] — INT-02: se podían poner 3 progenitores, o dos padres varones
**Categoría:** datos
**Síntoma:** No había límite de progenitores: se podía añadir un tercero, o un segundo del mismo sexo.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Los "38 N/A" del backtest de la coreografía (#1012) se interpretaron como que la regla existía ("a quien ya tiene padre y madre no se le ofrece añadir más progenitores"). La UI no lo ofrecía; el servidor sí lo aceptaba (confirmado en Frente A: 3er progenitor y dos padres varones creados sin error).
**Causa raíz:** La regla vivía solo como ausencia de botón en el cliente; solo existía el UNIQUE del par progenitor-hijo, ninguna regla de cardinalidad ni de sexo.
**Cómo se cazó:** usuario (lo propuso como caso de prueba en #1030) → auditoría → confirmado en Frente A (TA-1.1/1.2).
**Arreglo aplicado:** `Relaciones::validarProgenitores` (máx 2, no dos del mismo sexo conocido) + `validarSexoAlEditar` contra evasión por edición; guarda por cerrojo.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un "N/A" en un test no es un "bloqueado". Es un camino que el test no recorrió.
**Traza:** #1030, #1055, #1060, #1090, #1091; `Relaciones.php:31` (`validarProgenitores`, `validarSexoAlEditar`), `esquema.sql:104`; PLAN-QA §1quater/§3.1 INT-02.

## [2026-07-09] — INT-04: la validación de fechas era solo de cliente y evadible
**Categoría:** datos
**Síntoma:** La regla estricta (hijo no puede nacer el mismo año ni antes que su progenitor) solo existía en el cliente, y se evadía llamando al servidor o montando el árbol sin años y editando después. Cadenas con años parciales (A 2000 → B sin año → C 1990) colaban un imposible.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La validación en rojo en vivo funcionaba y se daba por buena; la de servidor no existía. El servidor ACEPTÓ la cadena 2000→sin año→1990 (confirmado Frente A, TA-1.6).
**Causa raíz:** Validación implementada como aviso de UI, no como regla de dominio en la autoridad (servidor); `Fechas.php` validaba cada arista aislada, solo por año.
**Cómo se cazó:** ojo humano (auditoría de código) + confirmado en Frente A.
**Arreglo aplicado:** Validación transitiva en el servidor recorriendo toda la cadena (ascendencia/descendencia), cubriendo años parciales y la evasión "montar sin años y editar después".
**Commit:** NO CONSTA
**Ley que sale de aquí:** Una validación que solo pinta en rojo es una sugerencia. La regla vive en el servidor o no vive.
**Traza:** #1058, #1090–#1092; `Fechas.php`, `ficha.js`; PLAN-QA §1quater/§3.1 INT-04.

## [2026-07-09] — CONC-02: TOCTOU — dos peticiones simultáneas se saltaban las reglas juntas
**Categoría:** datos
**Síntoma:** Las reglas nuevas (INT-01, INT-02) hacían "comprobar-luego-insertar" con SELECT sin bloqueo: dos peticiones simultáneas pasaban cada una su comprobación y violaban la regla entre las dos (3er progenitor, o cerrar un ciclo).
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La contraprueba lo demostró: desactivando el cerrojo, la carrera rompía la regla en 8/12 rondas (3 progenitores) y 8/12 (ciclo). Sin el cerrojo, las reglas "funcionaban" en las pruebas secuenciales y fallaban 2 de cada 3 veces bajo concurrencia.
**Causa raíz:** Comprobación y escritura en dos pasos no atómicos, sin bloqueo de fila.
**Cómo se cazó:** usuario (pidió explícitamente respaldo en BD, CONC-02) + test de concurrencia real.
**Arreglo aplicado:** Cerrojo de árbol `SELECT id FROM arb_arboles WHERE id=1 FOR UPDATE` como primera sentencia de toda escritura estructural (mutex serializador). Las lecturas no piden el cerrojo → ver el árbol nunca se bloquea.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Una regla de negocio validada con SELECT+INSERT sin bloqueo no es una regla, es una probabilidad.
**Traza:** #1090–#1092; `Arbol.php` (`bloquear`), `anadirFiliacion`, `editar`; PLAN-QA §1quater/§3.3 CONC-02.

## [2026-07-09] — VAL-01: el servidor aceptaba personas sin nombre
**Categoría:** datos
**Síntoma:** El nombre obligatorio solo lo imponía el cliente; el servidor aceptaba nombre vacío o solo espacios.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El backtest de #1012 verificó "guardar sin nombre queda bloqueado (marca error, no crea persona)" — verde por el cliente, con el servidor aceptándolo (confirmado en Frente A, TA-2.1).
**Causa raíz:** Obligatoriedad implementada solo como validación de formulario.
**Cómo se cazó:** ojo humano (auditoría de código) + Frente A.
**Arreglo aplicado:** `Personas::columnasDesde` rechaza nombre vacío/espacios en crear y editar.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si el test de "campo obligatorio" pulsa el botón en vez de llamar al endpoint, está probando el navegador, no tu app.
**Traza:** #1102, #1107; `Personas::columnasDesde`, `guardar.php`; PLAN-QA §1quinquies/§3.2 VAL-01.

## [2026-07-09] — VAL-02: se podía guardar gente que muere antes de nacer
**Categoría:** datos
**Síntoma:** El servidor aceptaba una fecha de fallecimiento anterior a la de nacimiento.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El servidor ACEPTÓ "morir antes de nacer" (nacimiento 1950 / fallecimiento 1900). NO CONSTA prueba previa que lo cubriera.
**Causa raíz:** Nunca se validó la relación nacimiento ≤ fallecimiento en servidor.
**Cómo se cazó:** ojo humano (auditoría de código) + confirmado en Frente A (TA-2.4).
**Arreglo aplicado:** Validación de servidor de nac ≤ fall con la misma lógica de años/años parciales.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Las validaciones cruzadas entre dos campos son las que siempre se olvidan; cada una hay que enumerarla.
**Traza:** #1102, #1107; `Personas::columnasDesde`, `Fechas.php`; PLAN-QA §1quinquies/§3.2 VAL-02.

## [2026-07-09] — VAL-03: se aceptaban fechas como 2020-13-45 y el año 0000
**Categoría:** datos
**Síntoma:** El servidor aceptaba fechas de calendario imposible: `2020-13-45`, `2020-00-00`, `0000`, `3000`, `1999-02-30`.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El servidor ACEPTÓ `2020-13-45` y `0000`. NO CONSTA prueba previa.
**Causa raíz:** No se usaba `checkdate` ni equivalente; solo se comprobaba el formato.
**Cómo se cazó:** ojo humano (auditoría de código) + Frente A.
**Arreglo aplicado:** `Fechas::esFechaCalendario` (formato + `checkdate` + año plausible), reutilizada por Personas y por el restore.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Validar el formato de una fecha no valida que la fecha exista.
**Traza:** #1102, #1107; `Fechas::esFechaCalendario`; PLAN-QA §1quinquies/§3.2 VAL-03.

## [2026-07-09] — INT-03: crear persona + vincularla eran dos transacciones, y se quedaban a medias
**Categoría:** datos
**Síntoma:** "Crear persona + filiación + pareja" eran endpoints separados con transacciones distintas: si la persona se creaba y la filiación fallaba, quedaba una persona suelta.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El backtest de #1012 declaró "Guardado real: crea el familiar (store +1) y persiste en BD (reaparece al releer del servidor)" — verde en el camino feliz, sin probar el fallo intermedio.
**Causa raíz:** Arquitectura de guardado por operaciones sueltas, sin transacción envolvente.
**Cómo se cazó:** ojo humano (auditoría de código).
**Arreglo aplicado:** El editor manda todo el paquete de cambios junto a un único `guardar.php` que lo aplica todo-o-nada en una sola transacción, resolviendo ids temporales. `persistir.js` reescrito.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si una acción del usuario son N escrituras, o hay una transacción o hay N estados corruptos posibles.
**Traza:** #1102, #1107, #1108; `guardar.php`, `persistir.js`; PLAN-QA §1quinquies/§3.1 INT-03.

## [2026-07-09] — INT-06: el avatar se borraba del disco antes del commit
**Categoría:** datos
**Síntoma:** El borrado del avatar anterior ocurría antes del commit; si el commit fallaba, el fichero ya no estaba pero la BD lo seguía apuntando.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** Efecto secundario de sistema de ficheros ejecutado dentro de una transacción que puede revertirse (el disco no hace rollback).
**Cómo se cazó:** ojo humano (auditoría de código).
**Arreglo aplicado:** `Fotos::programarBorrado` encola; `ejecutarEscritura` purga solo tras commit exitoso y descarta en rollback.
**Commit:** NO CONSTA
**Ley que sale de aquí:** El sistema de ficheros no participa en tu transacción: todo efecto irreversible va después del commit.
**Traza:** #1102, #1107; `Fotos::borrar/programarBorrado`, `Personas.php`; PLAN-QA §1quinquies/§3.1 INT-06.

## [2026-07-09] — INT-07: borrar algo que no existe devolvía ok:true
**Categoría:** silencio falso
**Síntoma:** Editar o borrar un id inexistente (o ya en papelera) devolvía `ok:true` sin cambiar nada.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El propio endpoint DABA VERDE (`ok:true`) sobre una operación que no hizo absolutamente nada. Cualquier test que comprobara el `ok` pasaba.
**Causa raíz:** No se comprobaba `rowCount()` tras el UPDATE/DELETE; en MySQL `rowCount` cuenta filas cambiadas (reguardar igual da 0 = falso positivo).
**Cómo se cazó:** ojo humano (auditoría de código) + Frente A (TA-4.2/4.3).
**Arreglo aplicado:** Editar comprueba existencia activa por `SELECT`; borrar comprueba `rowCount`; id inexistente o en papelera → error claro.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un endpoint que responde "ok" sin haber tocado una sola fila es una máquina de mentir a los tests.
**Traza:** #1102, #1107; `Personas::editar`, `persona.php`, `rowCount()`; PLAN-QA §1quinquies/§3.1 INT-07.

## [2026-07-09] — CONC-01: editar durante una relectura duplicaba la persona y mandaba la original a la papelera
**Categoría:** datos
**Síntoma:** Si se editaba justo durante el `await` de `recargarDesdeBD()`, se capturaban ids temporales y el diff podía DUPLICAR una persona y mandar la original a la papelera.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La "red de seguridad" existente (la que se puso tras el bug del árbol vaciándose) solo frena colapsos y borrados masivos: con este caso de UNA persona, la red no salta y el test de red de seguridad da verde ("0 avisos en ediciones legítimas", #1024).
**Causa raíz:** La captura de cambios no estaba serializada respecto a la relectura en vuelo; los ids temporales se resolvían mal.
**Cómo se cazó:** ojo humano (auditoría de código), verificado con relectura retrasada por CDP.
**Arreglo aplicado:** `relecturaEnVuelo` difiere la captura hasta que el árbol está fresco; remapeo temp→real como segunda barrera.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Una red de seguridad calibrada para catástrofes (borrado masivo) es ciega al daño de una sola fila, que es el que de verdad se cuela.
**Traza:** #1109, #1110; `persistir.js` (`recargarDesdeBD`); PLAN-QA §1sexies/§3.3 CONC-01.

## [2026-07-09] — CONC-03: el historial de deshacer no se re-basaba tras recargar
**Categoría:** datos
**Síntoma:** `reiniciarHistorial()` solo se llamaba en la carga inicial; tras `recargarDesdeBD`, un Deshacer/Rehacer podía reinyectar un estado con ids temporales y re-crear duplicados.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El backtest `undo-brutal.mjs` daba 24/24 (todos los campos + sexo + separar + borrar + encadenado) sin cubrir el caso "deshacer DESPUÉS de una recarga".
**Causa raíz:** El array de snapshots del historial es privado (closure) y no se puede re-basar; los ids temporales revivían en un Deshacer.
**Cómo se cazó:** ojo humano (auditoría de código).
**Arreglo aplicado:** Se descartó la vía obvia (re-basar el historial, ver entrada siguiente) y se optó por conservar el mapa temp→real toda la sesión y traducir el estado capturado antes del diff (`remapearIdsTemporales`).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Todo estado derivado (historial, caché, índice) necesita re-basarse en CADA punto donde la fuente cambia, no solo en el arranque.
**Traza:** #1109, #1110; `persistir.js` (`remapearIdsTemporales`, `reiniciarHistorial`, `recargarDesdeBD`); PLAN-QA §1sexies/§3.3 CONC-03.

## [2026-07-09] — La cura de CONC-03 inutilizaba el botón de Deshacer
**Categoría:** rompe
**Síntoma:** El arreglo pedido explícitamente en el prompt ("re-basar el historial también tras recargarDesdeBD") dejaba el botón de deshacer inservible.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (se midieron las dos vías empíricamente ANTES de elegir, así que el efecto se descubrió sin llegar a entregarse).
**Causa raíz:** Re-basar el historial tras cada relectura vacía la pila de deshacer.
**Cómo se cazó:** test (se midieron las dos opciones empíricamente en vez de suponer).
**Arreglo aplicado:** Decisión revertida: en vez de re-basar el historial, se traducen correctamente los identificadores. Resuelve CONC-03 sin cargarse el deshacer.
**Commit:** NO CONSTA
**Ley que sale de aquí:** El arreglo que pide el prompt puede ser el arreglo equivocado: medir las dos vías antes de elegir cuesta menos que revertir la mala.
**Traza:** #1109, #1110; `persistir.js`, `reiniciarHistorial()`.

## [2026-07-09] — CONC-05: los fallos de relectura se tragaban en silencio y el mensaje mentía
**Categoría:** silencio falso
**Síntoma:** Los `catch(_){}` silenciaban los fallos de relectura: un guardado con éxito seguido de relectura fallida dejaba la pantalla divergente de la BD SIN aviso y con ids temporales sin resolver. Y el mensaje "Se ha recargado el árbol…" se mostraba incluso cuando había sido un 401 o un error.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El propio mensaje de la interfaz DABA VERDE ("Se ha recargado el árbol…") sobre una relectura que había fallado con 401. La app se auto-certificaba mintiendo.
**Causa raíz:** Catch vacíos + mensaje de éxito emitido sin comprobar el resultado real.
**Cómo se cazó:** ojo humano (auditoría de código), verificado con Fetch de CDP (TA-5.1).
**Arreglo aplicado:** No tragar los fallos; avisar de verdad (guardado OK + refresco fallido → "recarga F5"); distinguir recarga real de error/sesión caducada (401 → login, sin alert engañoso).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un `catch` vacío no oculta el error: oculta que el usuario está mirando datos falsos.
**Traza:** #1109, #1110; `persistir.js` (`recargarDesdeBD`, `catch(_){}`); PLAN-QA §1sexies/§3.3 CONC-05.

## [2026-07-09] — CAL-01: si fallaba la carga inicial, pantalla en blanco y a callar
**Categoría:** silencio falso
**Síntoma:** Si la carga inicial del árbol fallaba (red caída, 500), el usuario veía una pantalla en blanco sin saber por qué; solo había un `console.error`.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** El fallo de carga solo se registraba en consola, sin ninguna vía de aviso al usuario.
**Cómo se cazó:** ojo humano (auditoría de código), verificado con CDP (TA-5.1).
**Arreglo aplicado:** Overlay "No se pudo cargar el árbol" + botón "Reintentar"; el 401 lleva al login.
**Commit:** NO CONSTA
**Ley que sale de aquí:** `console.error` no es manejo de errores: es manejo de errores para el desarrollador y silencio para el usuario.
**Traza:** #1109, #1110; `app.js` (`iniciarDesdeBD`), `arbol.php`; PLAN-QA §1sexies/§3.4 CAL-01.

## [2026-07-09] — CAL-02: el buscador mostraba las fotos rotas
**Categoría:** visual
**Síntoma:** Los resultados del buscador mostraban imagen rota para las fotos subidas.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** `buscador.js` usaba `p.avatar` crudo en vez de `urlFoto()` como el resto de la app; una foto subida se sirve por `foto.php?persona=<id>`.
**Cómo se cazó:** ojo humano (auditoría de código).
**Arreglo aplicado:** Usar `urlFoto()` como el resto.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Cada sitio que construye una URL a mano en vez de llamar al helper es un sitio que se va a desincronizar.
**Traza:** #1109, #1110; `buscador.js`, `urlFoto()`; PLAN-QA §1sexies/§3.4 CAL-02.

## [2026-07-09] — CAL-03: esc() no escapaba la comilla simple y reventaba con no-cadena
**Categoría:** carencia
**Síntoma:** `esc()` no escapaba `'` (inseguro en atributos `'…'`) y reventaba con números/null.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** Implementación frágil de `esc()` (no convertía a cadena, no cubría la comilla simple).
**Cómo se cazó:** ojo humano (auditoría de código).
**Arreglo aplicado:** `esc()` único endurecido en `util.js` (convierte siempre a cadena, escapa `'`→`&#39;`).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un escapador debe tolerar cualquier tipo de entrada y cubrir ambas comillas.
**Traza:** `util.js` (`esc()`); PLAN-QA §1decies/§3.4 CAL-03.

## [2026-07-09] — CAL-04: ids número-vs-cadena comparados sin coerción
**Categoría:** carencia
**Síntoma:** Comparaciones de id frágiles (`!==` entre número y cadena) podían fallar.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** Ids comparados sin coerción de tipo (los ids cruzan la frontera BD↔JS como número o cadena).
**Cómo se cazó:** ojo humano (auditoría de código).
**Arreglo aplicado:** `separarPareja` compara con `String()` en ambos lados.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Los ids que cruzan la frontera BD↔JS pueden ser número o cadena; compáralos coercionando.
**Traza:** `separarPareja`; PLAN-QA §1decies/§3.4 CAL-04.

## [2026-07-09] — CAL-05: guardas de `.rels` faltantes
**Categoría:** carencia
**Síntoma:** Acceso a `.rels` sin guardar el objeto podía romper si faltaba.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** Se guardaba el datum pero no el acceso a `.rels`.
**Cómo se cazó:** ojo humano (auditoría de código).
**Arreglo aplicado:** `ficha.js` y `formulario.js` guardan `d && d.rels` / `persona && persona.rels`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Guarda toda la cadena de acceso, no solo el primer nivel.
**Traza:** `ficha.js`, `formulario.js`; PLAN-QA §1decies/§3.4 CAL-05.

## [2026-07-09] — CAL-06: la función esc() escrita cinco veces
**Categoría:** carencia
**Síntoma:** `esc()` estaba duplicada 5 veces por los módulos; la que no escapaba la comilla simple era la peligrosa.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (nada roto hoy, pero "se ve" en un repo público; 463 pruebas en verde con las 5 copias).
**Causa raíz:** Crecimiento por copiar-pegar entre módulos sin extraer utilidades comunes.
**Cómo se cazó:** ojo humano (auditoría de código, Frente B).
**Arreglo aplicado:** Nuevo `util.js` con un único `esc()` endurecido; eliminadas las 5 copias.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Cinco copias de `esc()` son cinco oportunidades de que una se quede sin endurecer — y la que no escapa la comilla simple es la que te muerde.
**Traza:** #1130, #1131; `util.js`, `esc()`; PLAN-QA §3.4 CAL-06.

## [2026-07-09] — CAL-07: cuatro constructores de nombre casi iguales
**Categoría:** carencia
**Síntoma:** Cuatro (hasta cinco) construcciones distintas del nombre de una persona repartidas por el código.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (463/463 en verde).
**Causa raíz:** Falta de una utilidad común de formateo de nombre.
**Cómo se cazó:** ojo humano (auditoría de código).
**Arreglo aplicado:** `nombreDe`/`nombreCorto` unificados en `util.js` (reemplazan los constructores duplicados).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Cuatro formas de construir el mismo nombre son cuatro sitios donde el "(sin nombre)" saldrá distinto.
**Traza:** #1130, #1131; `util.js` (`nombreDe`, `nombreCorto`); PLAN-QA §3.4 CAL-07.

## [2026-07-09] — CAL-08: código muerto (borrar.js, Db::prefijo) y duplicación de profundidad/fechas
**Categoría:** carencia
**Síntoma:** Código muerto (`borrar.js:33`), `Db::prefijo()` sin uso, duplicación de cálculo de profundidad y de formateadores de fecha.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (463/463 en verde).
**Causa raíz:** Restos de etapas anteriores nunca retirados.
**Cómo se cazó:** ojo humano (auditoría de código).
**Arreglo aplicado:** Helper único de profundidad; código muerto y `Db::prefijo()` retirados.
**Commit:** NO CONSTA
**Ley que sale de aquí:** El código muerto que sigue en el repo público es lo que lee un reclutador.
**Traza:** #1130, #1131; `borrar.js`, `Db::prefijo()`; PLAN-QA §3.4 CAL-08.

## [2026-07-09] — CAL-09: acoplamiento por globals
**Categoría:** carencia
**Síntoma:** Acoplamiento entre módulos a través de variables globales.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** Comunicación entre módulos por estado global en vez de por interfaces explícitas.
**Cómo se cazó:** ojo humano (auditoría de código).
**Arreglo aplicado:** NO CONSTA el detalle; anotado en la auditoría de calidad.
**Commit:** NO CONSTA
**Ley que sale de aquí:** El acoplamiento por globals no rompe hoy, pero hace que cualquier cambio futuro tenga efectos a distancia.
**Traza:** #1130, #1131; PLAN-QA §3.4 CAL-09.

## [2026-07-09] — guardar.php nacía con un error fatal que se comía el código HTTP
**Categoría:** rompe
**Síntoma:** Al montar el nuevo `guardar.php` (INT-03), un conflicto de carga de archivos provocaba un error fatal, y ese error "se comía" el código de error HTTP. "Habría roto también el guardado normal del cliente."
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El error fatal SUPRIMÍA el status HTTP de error: la petición no devolvía un 500 legible. El propio mecanismo de reporte de fallos estaba roto por el fallo.
**Causa raíz:** Conflicto de carga de archivos (require/include) en el nuevo endpoint.
**Cómo se cazó:** test (el propio backtesting exhaustivo de la Tanda V1, antes de llegar al usuario).
**Arreglo aplicado:** Corregido el conflicto de carga.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un fatal en el arranque de un endpoint puede tragarse su propio código de estado: si el error no llega, el cliente cree que fue bien.
**Traza:** #1107; `guardar.php`, `persistir.js`.

## [2026-07-09] — Óscar y el falso "no puede tener dos progenitores padre" (deshacer × INT-02)
**Categoría:** rompe
**Síntoma:** A Óscar (1999) se le añade un padre nuevo → deshacer (lo quita bien) → REHACER → falla con "no puede tener dos progenitores padre", cuando Óscar nunca tuvo padre.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐⭐ TODO. La Tanda I1 daba 32/32 en servidor + 4/4 en concurrencia + 10/10 en cliente. La Tanda C1 daba 13/13 en historial y 12/12 en red, con re-verificación explícita de que "el bug crítico del árbol vaciándose sigue protegido". La Tanda H1 daba 35/35 en servidor y 9/9 de CSP. Cada pieza verde por separado; la interacción entre I1 (validación de progenitores) y C1 (historial + remapeo de ids) nunca se probó, y ahí estaba el fallo.
**Causa raíz:** Al deshacer, el padre va a la papelera (soft-delete) pero —por diseño, para poder restaurar— su filiación NO se borra: queda dormida apuntando a una persona de la papelera. `validarProgenitores` contaba ese vínculo dormido como "padre existente" → falso "dos padres".
**Cómo se cazó:** usuario, probando a mano la Tanda H1 con las manos ("vale veo el primer fallo... Oscar de 1999").
**Arreglo aplicado:** (A) Las validaciones miran solo el árbol ACTIVO: `validarProgenitores`, `validarSexoAlEditar`, `validarSinCiclo` y los recorridos de fechas filtran `borrado_en IS NULL`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Dos protecciones verdes por separado pueden ser un fallo juntas. Nadie prueba las intersecciones salvo el humano que usa la app de verdad.
**Traza:** #1114–#1120; `Relaciones.php` (`validarProgenitores`, `validarSexoAlEditar`, `validarSinCiclo`), `Fechas.php`, `borrado_en`; BACKTEST-FINAL Bloque 6.

## [2026-07-09] — El rehacer dejaba un huérfano en la papelera y una filiación colgante (corrupción invisible)
**Categoría:** datos
**Síntoma:** El síntoma visible era solo un mensaje de error, pero en la BD quedaba: 1 huérfano en la papelera + 1 filiación colgante, el rehacer imposible para siempre, y la pantalla (0 padres) divergiendo de la BD.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La PANTALLA daba verde: mostraba 0 padres, coherente y limpia, mientras la BD tenía basura. Todos los backtests anteriores (I1, C1, H1) comprobaban el estado final de la BD del demo (34/0) pero no el estado intermedio tras un fallo. (Este es el caso que el usuario puso de ejemplo en el encargo de la bitácora: la pantalla decía 0 padres, la BD tenía una filiación dormida apuntando a un padre en la papelera.)
**Causa raíz:** El diff veía al padre "ausente" → lo trataba como alta nueva duplicada; al fallar la validación, la transacción atómica revertía entera, dejando el huérfano de la operación anterior (el deshacer) intacto.
**Cómo se cazó:** usuario — intuición explícita: "imagino que eso se quedará duplicado en BD o algo". Confirmado reproduciendo los lotes exactos contra el servidor viejo.
**Arreglo aplicado:** (B) El REHACER RESTAURA en vez de duplicar: id real (entero) ausente = persona que un deshacer mandó a la papelera → se envía como restauración; el servidor la reactiva reutilizando su identidad y sus vínculos dormidos vuelven a estar activos.
**Commit:** NO CONSTA
**Ley que sale de aquí:** El síntoma es un mensaje; el daño está debajo. Tras cualquier fallo de escritura hay que ir a mirar la BD, no la pantalla.
**Traza:** #1116–#1119; `persistir.js`, `guardar.php`, `Personas::restaurar`, `arb_personas`, `arb_filiacion`.

## [2026-07-09] — Rehacer un hijo o una pareja duplicaba EN SILENCIO
**Categoría:** silencio falso
**Síntoma:** En +hijo y +pareja no existe la validación INT-02, así que el rehacer no daba ningún error... y duplicaba la persona en silencio, dejando el huérfano igual.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Aquí NO había mensaje de error: la operación se completaba "con éxito". Este fallo no lo habría reportado nadie; salió al investigar el de Óscar. Todos los backtests de deshacer/rehacer (24/24, 13/13) lo tuvieron delante y lo dieron por bueno.
**Causa raíz:** La misma que el caso de Óscar (el rehacer trata al id real ausente como alta nueva), pero sin ninguna validación que lo frenara.
**Cómo se cazó:** casualidad — salió al diagnosticar el bug de Óscar, no se buscaba.
**Arreglo aplicado:** El mismo arreglo (B): el rehacer restaura por identidad real en vez de crear duplicado.
**Commit:** NO CONSTA
**Ley que sale de aquí:** El bug que da error acaba arreglado; el bug gemelo que NO da error se queda vivo porque nadie lo reporta. Cuando arregles uno, busca sus hermanos silenciosos.
**Traza:** #1118, #1119; `persistir.js`, `guardar.php`, `Personas::restaurar`.

## [2026-07-09] — No se podía añadir un padre nuevo si el anterior estaba en la papelera
**Categoría:** rompe
**Síntoma:** Un bug latente que nadie había notado: si el padre de alguien estaba en la papelera, no se le podía añadir un padre nuevo.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El backtest de I1 declaró "uso legítimo (añadir madre/padre/hijo/pareja...) → todo OK" (32/32) sin haber probado nunca el caso "con el progenitor anterior en la papelera".
**Causa raíz:** `validarProgenitores` contaba los vínculos dormidos (a personas en papelera) como progenitores existentes.
**Cómo se cazó:** casualidad — apareció al arreglar el bug de Óscar ("Esto además arreglaba un bug latente").
**Arreglo aplicado:** Las validaciones filtran `borrado_en IS NULL`: una persona en la papelera y sus vínculos dormidos ya no restringen el árbol activo.
**Commit:** NO CONSTA
**Ley que sale de aquí:** El soft-delete crea un tercer estado (ni existe ni no existe) y TODA consulta de reglas tiene que decidir explícitamente qué hace con él.
**Traza:** #1118, #1119; `Relaciones.php` (`validarProgenitores`, `borrado_en`).

## [2026-07-09] — Al prompt del bug de Óscar le faltaba exigir mirar la BD
**Categoría:** carencia
**Síntoma:** El primer prompt de reporte del bug pedía arreglar el falso positivo, sin exigir investigar qué quedaba en la base de datos ni probar en más posiciones.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** El asistente se quedó en el síntoma visible (el mensaje de error) sin plantearse la corrupción subyacente.
**Cómo se cazó:** usuario ("tiene que hacer backtesting de más posiciones porque luego tendrá que ver porque imagino que eso se quedará duplicado en BD o algo").
**Arreglo aplicado:** Prompt reforzado con "IMPORTANTE — INVESTIGA EL ESTADO EN LA BD, no solo el síntoma" + matriz de posiciones + secuencias encadenadas.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Reportar un bug por su mensaje de error es reportar la mitad. Siempre pregunta qué quedó escrito.
**Traza:** #1115–#1117.

## [2026-07-09] — Al prompt de verificación final le faltaba exigir la BD paso a paso
**Categoría:** carencia
**Síntoma:** El prompt de backtesting hardcore de H1 solo pedía verificar la BD "en lo que aplique" (al final), no en cada paso.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** No se aplicó la lección recién aprendida del bug de Óscar (la BD puede estar corrupta con la pantalla limpia) al siguiente prompt.
**Cómo se cazó:** usuario ("le has pedido también que verifique a cada paso cómo se comporta la BD para evitar problemas?").
**Arreglo aplicado:** "EXIGENCIA CLAVE SOBRE LA BASE DE DATOS" añadida: escanear tras CADA paso relevante buscando duplicados, huérfanos, filiaciones dormidas/colgantes y divergencia pantalla↔BD. Resultó en la auditoría 25/25 con la tabla paso a paso.
**Commit:** NO CONSTA
**Ley que sale de aquí:** La lección aprendida en el bug N solo sirve si la metes en el prompt N+1. Si no, la vuelves a aprender.
**Traza:** #1123–#1126.

## [2026-07-09] — INT-10: main_id podía apuntar a una persona en la papelera
**Categoría:** datos
**Síntoma:** El `main_id` (la persona por la que se encuadra el árbol) podía apuntar a alguien que estaba en la papelera.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** Al mandar a la papelera una persona no se comprobaba si era el `main_id`.
**Cómo se cazó:** ojo humano (auditoría de código).
**Arreglo aplicado:** `arbol.php` omite `main_id` si apunta a papelera (arreglo de raíz, en servidor) → 4/4; el cliente cae al primer nodo.
**Commit:** NO CONSTA
**Ley que sale de aquí:** El soft-delete otra vez: toda referencia guardada a un id tiene que sobrevivir a que ese id se vaya a la papelera.
**Traza:** #1130, #1131; `arbol.php`, `main_id`; PLAN-QA §1decies/§3.1 INT-10.

## [2026-07-09] — INT-09: restaurar de la papelera dejaba aristas a personas no visibles
**Categoría:** datos
**Síntoma:** Restaurar una persona de la papelera podía dejar aristas apuntando a personas que siguen en la papelera (no visibles).
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** El mismo modelo de vínculos dormidos que causó el bug de Óscar, aplicado al restore manual desde la papelera.
**Cómo se cazó:** ojo humano (auditoría de código).
**Arreglo aplicado:** NO se corrigió: se DOCUMENTÓ en `docs/DESPLIEGUE-Y-SEGURIDAD.md`, tras verificar que `arbol.php` solo pinta activos (TA-8.3: "restaurar con el otro extremo en papelera → sin fantasma").
**Commit:** NO CONSTA
**Ley que sale de aquí:** Documentar un fallo conocido en vez de arreglarlo es legítimo, pero solo si dejas escrito por qué es tolerable.
**Traza:** #1130, #1131; `arbol.php`, `docs/DESPLIEGUE-Y-SEGURIDAD.md`; PLAN-QA §3.1 INT-09.

## [2026-07-09] — CONC-04: dos pestañas editando, gana la última, sin avisar
**Categoría:** datos
**Síntoma:** Con dos pestañas editando la misma persona, se aplica "última escritura gana" sin ningún aviso al usuario de que sus cambios se pisaron.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ TA-3.5 dio 5/5: "Sin corrupción, última-escritura-gana, convergen al recargar". Verde porque el criterio de aceptación era "no corrompe", no "no pierdes trabajo".
**Causa raíz:** No hay control de versión optimista ni detección de edición concurrente.
**Cómo se cazó:** ojo humano (auditoría de código) + confirmado en las pruebas pendientes del Frente A.
**Arreglo aplicado:** NO se corrigió: DOCUMENTADO en `docs/DESPLIEGUE-Y-SEGURIDAD.md`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** "No corrompe" y "no pierde datos del usuario" no son el mismo verde; escribe cuál de los dos estás comprobando.
**Traza:** #1130, #1131; `docs/DESPLIEGUE-Y-SEGURIDAD.md`, `persistir.js`; PLAN-QA §3.3 CONC-04.

## [2026-07-09] — HIG-01: un archivo muerto camino de GitHub
**Categoría:** despliegue
**Síntoma:** `index.viejo.html` seguía en el proyecto, a punto de subirse a un repositorio público que sirve de portafolio.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** Archivo de una versión anterior nunca borrado.
**Cómo se cazó:** ojo humano (auditoría de código, higiene de repo).
**Arreglo aplicado:** Borrado; y `.htaccess` devuelve 404 sobre él.
**Commit:** NO CONSTA
**Ley que sale de aquí:** El repo público muestra tus restos: lo que no borras, lo lee un reclutador.
**Traza:** #1130, #1131; `index.viejo.html`, `.gitignore`, `.htaccess`; PLAN-QA §4.5 HIG-01.

## [2026-07-09] — HIG-02: el .gitignore tenía una regla engañosa
**Categoría:** despliegue
**Síntoma:** El `.gitignore` tenía una regla `/backups/` engañosa (no cubría la ruta real `almacen/backups/`) y no cubría los `*.viejo.*`.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La regla ESTABA escrita en el `.gitignore`, así que "parecía" cubierto. Nadie había verificado con un `git add -A` real qué entraba de verdad.
**Causa raíz:** Regla de ignore escrita con una ruta que no coincidía con la real.
**Cómo se cazó:** ojo humano (auditoría de higiene de repo).
**Arreglo aplicado:** `.gitignore` limpiado (+`*.viejo.*`, −`/backups/`); verificado con un `git init` temporal que `git add -A` no cuela ningún secreto (`config.php`, `config.dev.bak`, `almacen/`).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Una regla en el `.gitignore` no es una garantía: la garantía es hacer el `git add -A` en seco y mirar la lista.
**Traza:** #1130, #1131; `.gitignore`; PLAN-QA §1decies/§4.5 HIG-02/HIG-04.

## [2026-07-09] — HIG-03: las claves de demo iban a publicarse sin advertencia
**Categoría:** seguridad
**Síntoma:** El proyecto se iba a publicar con las claves de demo (edición y lectura, con sus hashes en el repo público) sin documentar en ningún sitio que hay que cambiarlas en producción.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** Las claves de demo eran conocidas y cómodas durante todo el desarrollo; nadie escribió la advertencia.
**Cómo se cazó:** ojo humano (auditoría de higiene de repo).
**Arreglo aplicado:** Documentado que las claves demo DEBEN cambiarse en producción (mínimo 8 car.); el despliegue en Hostinger incluye "cambiar claves demo" como paso.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Toda credencial por defecto de un proyecto open-source es una credencial pública: o se fuerza el cambio o se advierte a gritos.
**Traza:** #1130, #1131, #1135; `db/datos-demo.sql`, `docs/DESPLIEGUE-Y-SEGURIDAD.md`, `PENDIENTES.md`; PLAN-QA §4.5 HIG-03.

## [2026-07-09] — Las cookies Secure y HSTS se dieron por verificadas simulando el proxy
**Categoría:** aviso falso
**Síntoma:** El informe de H1 declaró "cookie Secure/HSTS (vía X-Forwarded-Proto)" dentro del bloque 35/35 verde.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Se contó como VERDE dentro del 35/35 algo que solo se probó simulando el proxy TLS con una cabecera `X-Forwarded-Proto: https` inyectada a mano. El entorno local es HTTP: no hay TLS real que ejercitar. Lo reconoció explícitamente en "Lo que NO pude verificar en vivo (honesto)".
**Causa raíz:** No existe entorno HTTPS en local (Laragon sobre HTTP).
**Cómo se cazó:** ojo humano (autodeclaración honesta en el informe).
**Arreglo aplicado:** Ninguno; queda como pendiente bloqueante de la publicación: "Verificar HTTPS/cookies REAL... en el dominio con TLS".
**Commit:** NO CONSTA
**Ley que sale de aquí:** Una prueba que simula la condición que quiere verificar no es verde: es "pendiente" con buena letra. Que aparezca dentro del recuento 35/35 lo disfraza.
**Traza:** #1126, #1127, #1131, #1135; `src/Auth.php`, `http.php`, `PENDIENTES.md`.

## [2026-07-09] — El nonce CSP del instalador solo se verificó leyendo el código
**Categoría:** aviso falso
**Síntoma:** El informe de CSP declaró "16/16, CERO violaciones" incluyendo el instalador.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El `<script>` con nonce del instalador solo existe en el paso «datos», inaccesible sin reinstalar. Se verificó "de forma estática" (leyendo que la cabecera CSP lleva el nonce y que el `<script>` lo referencia), no ejecutándolo. Aun así entró en el recuento del 16/16.
**Causa raíz:** No se puede recorrer el instalador en vivo sin reinstalar la app.
**Cómo se cazó:** ojo humano (autodeclaración honesta en el informe).
**Arreglo aplicado:** Ninguno; anotado como limitación conocida.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Verificación estática y verificación en vivo no se suman en el mismo marcador. Si van juntas, el marcador miente.
**Traza:** #1113, #1126, #1131; `public/instalar/index.php`, CSP nonce.

## [2026-07-09] — PENDIENTES.md llevaba marcado como pendiente lo ya hecho (y al revés)
**Categoría:** carencia
**Síntoma:** El asistente empezó a planificar la publicación "dejándose un montón de cosas"; `PENDIENTES.md` tenía el PASO 13 sin marcar como cerrado y checkboxes obsoletos del bloque G (papelera, copias, login, fotos, modo con/sin control) marcados como pendientes cuando ya estaban hechos.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** La documentación de estado no se actualizó durante las tandas; el megabloque absorbió el Paso 13 sin que nadie lo reflejara.
**Cómo se cazó:** usuario ("te estás dejando un montón de cosas... en la documentación de PENDIENTES.MD habrá que saber cómo seguir").
**Arreglo aplicado:** `PENDIENTES.md` actualizado (nota de cierre del megabloque, PASO 13 marcado CERRADO, checkboxes corregidos) + inventario de recta final en tres bloques (imprescindible / recomendable / futuro).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un documento de pendientes que no se actualiza en cada tanda deja de ser un mapa y pasa a ser una trampa: te crees que queda lo que no queda y te olvidas de lo que sí.
**Traza:** #1132–#1136; `PENDIENTES.md`, `ESTADO-Y-DECISIONES.md`, `docs/DESPLIEGUE-Y-SEGURIDAD.md`.

---

# 2026-07-10 — El día de publicar (backtest final, acabado, marca y despliegue)

## [2026-07-10] — El "Exportar datos" que no exportaba las fotos
**Categoría:** datos
**Síntoma:** El JSON de "Exportar datos" guardaba en `avatar` solo el nombre del archivo de la foto; fuera del servidor esa cadena es inútil y la imagen no viaja. Tampoco incluía papelera ni ajustes.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Nada verificó esto: quedó anotado como "decisión abierta menor" en la auditoría previa ("Fotos en Exportar datos JSON: revisar si debe embeberlas") y se arrastró sin cerrar. El propio panel ya llevaba un texto de aviso que daba la sensación de que el tema estaba resuelto.
**Causa raíz:** `datos.js:9-22` serializaba `{titulo, subtitulo, personas[{id,data,rels}]}` y el campo `avatar` era una referencia al fichero de `almacen/fotos/`, no la imagen.
**Cómo se cazó:** usuario (preguntó explícitamente "¿coge también las fotos?")
**Arreglo aplicado:** Se enriqueció el JSON (metadatos, relaciones explícitas con nombres, listas globales de aristas, `tiene_foto` true/false) y se documentó que las fotos no viajan; el respaldo real siguen siendo las Copias.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Una "decisión abierta menor" sin cerrar es un fallo latente: si nadie la verifica, se publica.
**Traza:** #1147, #1148, #1150, #1151, #1157, #1159; `datos.js`, `construirExportacion()`, `f3Edit.exportData()`, `Backup.php:213-214`.

## [2026-07-10] — El botón que prometía un respaldo y solo daba texto
**Categoría:** visual
**Síntoma:** El botón "Exportar datos (JSON)" sonaba a copia de seguridad, pero ni llevaba fotos ni se podía restaurar. Nombre deshonesto.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA. Solo constaba que "el propio panel ya lo advierte en su texto", lo que se dio por suficiente.
**Causa raíz:** El nombre de la función no reflejaba lo que hacía (portabilidad, no respaldo).
**Cómo se cazó:** usuario / asistente al confirmar el comportamiento real
**Arreglo aplicado:** Renombrado a «Exportar árbol a JSON (otras apps o IA)», bloque del panel renombrado de «Copias y traspaso» a «Copias y portabilidad», y texto de ayuda reescrito.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si el nombre de un botón promete más de lo que hace, la interfaz miente.
**Traza:** #1151, #1152, #1156, #1157, #1159; panel → Datos, `admin.js`, `datos.js`.

## [2026-07-10] — El JSON exportado no se podía volver a meter por ninguna vía
**Categoría:** carencia
**Síntoma:** No existía ninguna función de importar ese JSON; y si se intentaba restaurar como copia, el backend lo rechazaba.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA. Se había retirado "Importar" del panel a propósito, redirigiendo a Copias→Restaurar, sin comprobar que los formatos fueran compatibles.
**Causa raíz:** `Backup.php:213-214` exige `manifest.tipo === "arbol-genealogico-backup"`; el export no tiene manifest → rechazo con «El archivo no es una copia de seguridad de este árbol.»
**Cómo se cazó:** usuario (pregunta) → revisión de código
**Arreglo aplicado:** Se asumió como diseño (es portabilidad hacia fuera) y se documentó en el texto de ayuda. No se añadió importación.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Quitar un botón sin comprobar que la vía alternativa acepta el mismo formato deja al usuario con datos que no pueden volver.
**Traza:** #1151, #1156, #1159; `Backup.php`, panel → Datos.

## [2026-07-10] — El asistente recomendó borrar el export y tuvo que retirarlo
**Categoría:** carencia
**Síntoma:** El asistente concluyó "es redundante y confuso, me inclino por quitarlo" y el usuario le explicó el propósito real (portabilidad hacia otras apps/IA); el asistente retiró la recomendación.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA (fue un razonamiento, no una prueba).
**Causa raíz:** El asistente evaluó la utilidad de la función sin entender su caso de uso (portabilidad de datos).
**Cómo se cazó:** usuario ("no para usarlo aquí sino para llevarlo a cualquier otro formato, lector o IA")
**Arreglo aplicado:** Se retiró la recomendación de eliminar; se mantuvo, renombró y enriqueció.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Antes de proponer eliminar algo, averigua para qué lo usa el dueño.
**Traza:** #1153–#1156.

## [2026-07-10] — El aviso de VS Code que no era un problema
**Categoría:** aviso falso
**Síntoma:** VS Code avisaba de que no podía validar PHP y pedía configurar `php.validate.executablePath`. Alarmó al usuario en mitad de un backtesting.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Es el caso inverso: la app y el backtesting funcionaban perfectamente mientras la herramienta gritaba.
**Causa raíz:** VS Code no encuentra el binario de PHP porque vive dentro de Laragon, en una ruta que no conoce por defecto.
**Cómo se cazó:** usuario (lo trajo alarmado)
**Arreglo aplicado:** Ninguno. Se recomendó ignorarlo (o configurar la ruta del ejecutable si molestaba).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un aviso rojo del editor no es un fallo de la app; identifica quién habla antes de correr.
**Traza:** #1181; ajuste `php.validate.executablePath`.

## [2026-07-10] — Restaurar una copia borraba el .htaccess de la carpeta de fotos
**Categoría:** seguridad
**Síntoma:** `Backup::restaurar` eliminaba `almacen/fotos/.htaccess` en cada restauración. El swap de la carpeta recreaba solo `.gitkeep`.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ **El más grave del día.** La prueba TA-8.2 (ida y vuelta con caracteres raros y foto: firma MD5 de la BD idéntica y foto byte a byte) dio VERDE. La verificación manual del usuario del Paso 11 (generar, descargar, restaurar desde servidor y archivo) dio VERDE. Y lo demoledor: **las restauraciones de esas pruebas anteriores ya se habían llevado el fichero por delante y nadie lo notó** — se detectó al arrancar el backtest dedicado y encontrarlo ausente.
**Causa raíz:** El swap de la carpeta de fotos recreaba la carpeta desde cero sin preservar el fichero de defensa (el `.htaccess` no viaja en la copia, es del repo).
**Cómo se cazó:** test (backtesting dedicado de copias, 50/50, pedido expresamente por el usuario)
**Arreglo aplicado:** `restaurar()` preserva el `.htaccess` a través del swap y, si falta, repone uno canónico (`Backup::HTACCESS_FOTOS`). Verificado 3/3.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Una prueba que solo compara los datos que le importan (BD + fotos) no ve lo que destruye alrededor. Hay que verificar también lo que NO debía cambiar.
**Traza:** #1204, #1205; `Backup::restaurar`, `almacen/fotos/.htaccess`; PLAN-QA §1undecies.

## [2026-07-10] — El swap de fotos ocurre fuera de la transacción
**Categoría:** datos
**Síntoma:** La restauración es transaccional para la BD, pero el intercambio de la carpeta de fotos sucede **después del commit**, fuera de la transacción: un fallo de disco a mitad no revierte nada.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Se probó el fallo de BD a mitad (transacción revertida, árbol intacto) y dio VERDE — pero esa prueba no toca el escenario del swap de ficheros.
**Causa raíz:** Diseño: la operación de ficheros no está cubierta por la transacción de la base de datos.
**Cómo se cazó:** test (autodeclarado por Claude Code como "lo que NO puedo verificar en vivo")
**Arreglo aplicado:** NINGUNO. Se declaró como limitación conocida y no verificable en local.
**Commit:** NO CONSTA
**Ley que sale de aquí:** "Es transaccional" solo vale para lo que está dentro de la transacción; los ficheros casi nunca lo están.
**Traza:** #1204; `Backup.php` (`restaurar`).

## [2026-07-10] — XSS latente en la ficha nativa de family-chart
**Categoría:** seguridad
**Síntoma:** La ficha de lectura por defecto de la librería (`.f3-form.non-editable`) pintaba los valores como HTML crudo: un nombre o nota con `<img src=x onerror=…>` creaba el nodo en el DOM.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ **La auditoría de seguridad completa anterior, la tanda H1 (CSP), la Tanda Q1 (unificación de `esc()`) y todas las pruebas de XSS previas dieron VERDE.** La prueba de XSS pasaba (el `onerror` no se ejecutaba, `window.__xssImg` indefinido) porque la CSP lo bloqueaba, mientras los nodos crudos seguían creándose en el DOM. El fallo llevaba ahí todo el tiempo (es de la librería) y solo se vio al re-auditar el conjunto.
**Causa raíz:** La ficha nativa de la librería no escapa la salida. La ficha propia sí escapa y la nativa está oculta por CSS, pero los nodos crudos quedaban en el DOM.
**Cómo se cazó:** test (re-auditoría final pedida por el usuario porque "se han tocado cosas")
**Arreglo aplicado:** Se neutralizan los nodos crudos en `ficha.js` (se vacían los `.f3-info-field-value` con `textContent` tras montar la ficha escapada). Re-verificado 7/7. La CSP estricta ya lo neutralizaba → no era explotable, pero se arregló para no depender solo de la CSP.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Escapar TU vista no basta si la librería pinta una vista paralela oculta. Y la defensa en profundidad (CSP) tapa fallos que ninguna auditoría vio.
**Traza:** #1206–#1209; `ficha.js` (`fichaLecturaBonita`), `.f3-info-field-value`, CSP; PLAN-QA §1duodecies, BACKTEST-FINAL Bloque 13.

## [2026-07-10] — El toggle "Fecha exacta → Año" se comía el año de nacimiento
**Categoría:** datos
**Síntoma:** Si ponías una fecha exacta, cambiabas a "solo año" y guardabas, **se perdía el año**. Pérdida silenciosa de un dato.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ **La auditoría de seguridad original, la re-auditoría final completa (#1208, que declaró "0 hallazgos abiertos, listo para publicar") y todas las tandas de regresión (S1, I1, V1, C1, H1, Q1) dieron VERDE con este bug vivo.** El toggle parecía funcionar (cambiaba a "Año") mientras el valor quedaba vacío. Se cazó solo en el backtest final total de 463 pruebas, que el usuario pidió por su cuenta ("aunque nos peguemos 1 día entero con test").
**Causa raíz:** En `mejorarCampoFecha` se asignaba `input.value='1983'` mientras el input seguía siendo `type="date"` (que rechaza un año suelto); el cambio a `type="text"` iba después.
**Cómo se cazó:** test (backtest final total, 463/463; Bloque 3).
**Arreglo aplicado:** Reordenar: primero `type='text'`, luego el valor. Reverificado `1983-02-17` → «1983».
**Commit:** NO CONSTA
**Ley que sale de aquí:** Al cambiar el tipo de un input, fija el tipo antes que el valor; el input descarta valores que no encajan con su tipo actual. Y un veredicto de "listo para publicar" no significa que no queden bugs que comen datos.
**Traza:** #1210, #1214–#1216; `formulario.js` (`mejorarCampoFecha`); BACKTEST-FINAL Bloque 3 (F-2).

## [2026-07-10] — Faltaba el favicon (404)
**Categoría:** carencia
**Síntoma:** La app no tenía favicon: el navegador pedía `/favicon.ico` y recibía un 404 (pestaña sin icono).
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Todas las auditorías y backtests anteriores (incluida la re-auditoría final "listo para publicar") pasaron sin detectarlo. Se registró como hallazgo F-1 solo en el backtest final total.
**Causa raíz:** Nunca se creó el icono ni se enlazó en el `<head>`.
**Cómo se cazó:** test/casualidad (404 benigno observado en el backtest final, F-1).
**Arreglo aplicado:** Logo propio en SVG (árbol de nodos en teal) y de ahí `favicon.svg`, `favicon.ico` multitamaño (16/32/48), PNG 512×512 y apple-touch-icon 180×180, enlazados en el `<head>` de `index.php` y del instalador con cache-busting. Backtest del favicon 27/27.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Las auditorías de seguridad y de funcionalidad no miran el `<head>`; el acabado necesita su propia lista.
**Traza:** #1216, #1217, #1219, #1221–#1223; `public/favicon.*`, `index.php` `<head>`; BACKTEST-FINAL F-1.

## [2026-07-10] — El demo trae una clave más corta de lo que el panel exige
**Categoría:** datos
**Síntoma:** La clave de solo lectura del demo tiene 7 caracteres, pero el panel de administración exige un mínimo de 8. Incoherencia entre los datos sembrados y la validación.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Todas las pruebas del instalador y del panel (incluido el instalador 12/12 y el modo acceso) pasaron sin señalar esta incoherencia; el demo se instalaba con una clave que su propio panel rechazaría.
**Causa raíz:** Los datos del demo se sembraron directamente en `db/datos-demo.sql`, saltándose la validación de `Acceso::establecerClave`, antes de endurecer la longitud mínima.
**Cómo se cazó:** test (backtest final total, marcado como "no bloqueante", F-3).
**Arreglo aplicado:** NO CONSTA (se dijo "se puede dejar así o actualizar el demo"; no consta que se cambiara).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si endureces una validación, revisa los datos semilla: el demo no puede violar sus propias reglas.
**Traza:** #1216; `db/datos-demo.sql`, `Acceso::establecerClave`; BACKTEST-FINAL F-3.

## [2026-07-10] — El emoji 🎉 solitario del panel
**Categoría:** visual
**Síntoma:** El mensaje "No hay personas sin nombre. 🎉" era el **único emoji de todo el UI** de la app; rompía el registro.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA: ninguna prueba anterior revisaba coherencia de registro/tono.
**Causa raíz:** Texto escrito sin criterio de coherencia global de tono.
**Cómo se cazó:** test (repaso completo de textos, pedido antes de publicar)
**Arreglo aplicado:** Retirado en los 3 mensajes. Confirmado: 0 emojis en el UI de la app (los del instalador se dejaron a propósito).
**Commit:** NO CONSTA
**Ley que sale de aquí:** El tono es una funcionalidad: un solo elemento fuera de registro se nota.
**Traza:** #1211–#1213; `admin.js`.

## [2026-07-10] — "Vertical (de arriba abajo)" sin paralelismo
**Categoría:** visual
**Síntoma:** La opción de orientación decía «Vertical (de arriba abajo)» frente a «Horizontal (de izquierda a derecha)»: no leían en paralelo.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** Redacción no revisada en conjunto.
**Cómo se cazó:** test (repaso de textos)
**Arreglo aplicado:** Cambiado a «de arriba a abajo».
**Commit:** NO CONSTA
**Ley que sale de aquí:** Las opciones hermanas se leen juntas: si una rompe el paralelismo, chirría.
**Traza:** #1212; `admin.js` (Ajustes → Orientación).

## [2026-07-10] — La misma persona sin nombre se llamaba de dos formas
**Categoría:** visual
**Síntoma:** El marcador de persona sin nombre aparecía como «Sin nombre» en la tarjeta, la ficha, la papelera y el panel; y como «(sin nombre)» en los parentescos, el diálogo de separar pareja, el de borrar y el export JSON.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Todos los backtests de ficha, papelera, parentescos y export (28/28) dieron VERDE con las dos formas conviviendo: ninguna prueba comparaba las cadenas entre pantallas.
**Causa raíz:** El literal se escribía a mano en varios sitios (duplicación de cadena).
**Cómo se cazó:** test (repaso de textos)
**Arreglo aplicado:** Unificado a «Sin nombre» en `util.js` (`nombreCorto`) y `datos.js` (×2). Se dejó a propósito el «(sin nombre)» de `admin.js:437`, donde es un inciso dentro de una frase.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Una cadena escrita en N sitios diverge en N formas. Los literales de UI también son fuente única de verdad.
**Traza:** #1212, #1213; `util.js` (`nombreCorto`), `datos.js`, `admin.js:437`.

## [2026-07-10] — El instalador iba de otro verde
**Categoría:** visual
**Síntoma:** El instalador usaba un verde propio (`#2f7d5d`) distinto del teal de marca de la app (`#0f857d`/`#148f84`).
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El instalador se había verificado completo (12/12, y luego 19/19) sin que ninguna prueba mirara la coherencia de color con la app.
**Causa raíz:** El instalador se construyó como pieza aparte, con sus propias variables de acento (`--acc`/`--acc2`).
**Cómo se cazó:** ojo humano (al crear el logo/favicon se destapó la incoherencia de marca)
**Arreglo aplicado:** Sustituidas las dos variables de acento por el teal de marca en claro y oscuro. Backtest 30/30 + instalador 19/19.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Las piezas que se construyen "aparte" (instaladores, páginas de error) se salen de la marca sin que nadie lo pruebe.
**Traza:** #1223, #1225, #1226; `<style>` del instalador, `--acc`/`--acc2`.

## [2026-07-10] — El emoji 🌳 haciendo de logo en la app
**Categoría:** visual
**Síntoma:** La tarjeta de título de la app mostraba un emoji genérico de árbol en lugar de un logo.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Los backtests de frontend (B1 46/46) y responsive (144/144) daban VERDE con el emoji: verificaban que no se rompía el layout, no que la identidad fuera propia.
**Causa raíz:** Nunca existió un logo; se usó un emoji como placeholder que se quedó.
**Cómo se cazó:** ojo humano (usuario: "habrá que crear un logo para esta APP que puede sustituir al arbolito")
**Arreglo aplicado:** Emoji sustituido por el logo SVG inline; `.titlecard .leaf` ajustado a bloque cuadrado de 26px.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Los placeholders sobreviven al proyecto entero si nadie los inventaría.
**Traza:** #1217, #1225, #1226; `.titlecard .leaf`, logo SVG.

## [2026-07-10] — El emoji que quedó vivo en la pantalla de login
**Categoría:** visual
**Síntoma:** Tras "cerrar" la coherencia de marca (instalador + tarjeta de título), la pantalla de acceso/login **seguía usando el emoji 🌳 tres veces** (`.login-leaf`, en los tres estados de acceso).
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El backtest de marca dio **30/30**, B1 **46/46** y B14 responsive **144/144** — todo verde — mientras el login seguía con el emoji genérico. El propio Claude Code lo señaló a mano ("te lo señalo, no lo toco sin tu OK") porque las pruebas no lo cubrían.
**Causa raíz:** El alcance del arreglo se limitó a lo aprobado explícitamente (la tarjeta de título), sin barrer todos los usos del emoji.
**Cómo se cazó:** ojo humano (Claude Code lo reportó al terminar)
**Arreglo aplicado:** Logo SVG en los tres estados del login + botón "Entrar" a teal usando `var(--accent)` (theme-aware, sin hardcode). Verificado 29/29 + B11 13/13.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Cuando sustituyas un símbolo, busca TODAS sus apariciones antes de declarar la coherencia cerrada.
**Traza:** #1226–#1228; `.login-leaf`, `var(--accent)`.

## [2026-07-10] — Nunca se había buscado la duplicación de código a propósito
**Categoría:** carencia
**Síntoma:** Se creía que la duplicación estaba resuelta porque la Tanda Q1 había unificado `esc()` (repetida 5 veces) y los constructores de nombre. Al buscar sistemáticamente aparecieron **26 hallazgos** nuevos.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La Tanda Q1 se dio por "limpieza de duplicación hecha" — pero fue **oportunista** (arreglar lo que se vio de pasada), no sistemática. Y el proyecto llevaba 463 pruebas en verde.
**Causa raíz:** No existía ninguna auditoría dedicada a duplicación; se confundió "arreglamos lo que saltó" con "no queda duplicación".
**Cómo se cazó:** usuario ("¿hemos llegado a hacer alguna auditoría en donde se vea si el código usado se repite?")
**Arreglo aplicado:** Auditoría dedicada solo de diagnóstico (`docs/AUDITORIA-DUPLICACION.md`), 26 hallazgos clasificados por beneficio/riesgo; se refactorizó después el subconjunto seguro.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Arreglar lo que ves de pasada no es lo mismo que rastrear. Lo oportunista deja mapa sin explorar.
**Traza:** #1254, #1255, #1257, #1258; `docs/AUDITORIA-DUPLICACION.md`, `util.js`.

## [2026-07-10] — Código muerto: la clave de config que nadie lee
**Categoría:** carencia
**Síntoma:** El instalador escribía en la configuración una clave `'prefijo'=>'arb_'` que **ninguna clase lee**.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Todas las pruebas del instalador (12/12, luego 19/19) pasaron escribiendo esa clave inútil.
**Causa raíz:** Resto de un diseño anterior nunca retirado.
**Cómo se cazó:** test (auditoría de duplicación).
**Arreglo aplicado:** Eliminada en la tanda de refactorización segura.
**Commit:** NO CONSTA
**Ley que sale de aquí:** El código muerto que escribe datos confunde al siguiente que lo lea: bórralo.
**Traza:** #1262; instalador, fichero de config generado.

## [2026-07-10] — La regla del nombre de foto, escrita en cinco sitios
**Categoría:** carencia
**Síntoma:** La regex/regla que valida el nombre de archivo de foto estaba duplicada en 5 lugares (cliente y servidor). Era lo más repetido de todo el código.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Todo el backtest (463/463) y las auditorías de seguridad daban VERDE: funcionaba, simplemente estaba escrito cinco veces.
**Causa raíz:** Falta de una constante compartida por capa.
**Cómo se cazó:** test (auditoría de duplicación, JS-5 / PHP-3).
**Arreglo aplicado:** Una `const` por capa en JS y `Fotos::PATRON` público reutilizado en PHP.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Una regla de validación repetida en 5 sitios son 5 sitios donde olvidarse de cambiarla.
**Traza:** #1258, #1262, #1281; `Fotos::PATRON`, regex del avatar.

## [2026-07-10] — formulario.js subía la foto por su cuenta, saltándose api.js
**Categoría:** carencia
**Síntoma:** `formulario.js` hacía su propio `fetch` para subir la foto en vez de pasar por `api.js`, rompiendo la "puerta única" al backend.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Las pruebas de fotos (subir/cambiar/quitar) pasaban en verde; nadie comprobaba **por dónde** salía la petición.
**Causa raíz:** La subida de foto se implementó al margen de la capa de API.
**Cómo se cazó:** test (auditoría de duplicación, JS-2).
**Arreglo aplicado:** `apiSubirFoto` en `api.js`; `formulario.js` deja de tener su fetch propio.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Una "puerta única" con un agujero no es una puerta única; verifica la arquitectura, no solo el resultado.
**Traza:** #1262, #1281; `formulario.js`, `api.js`, `apiSubirFoto`.

## [2026-07-10] — Ocho llamadas fetch GET copiadas y pegadas
**Categoría:** carencia
**Síntoma:** ≈8 llamadas `fetch` GET al servidor repetían el mismo boilerplate.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ 463/463 en verde: funcionaba.
**Causa raíz:** Sin helper común de acceso a la API.
**Cómo se cazó:** test (auditoría de duplicación, JS-1).
**Arreglo aplicado:** `getJSON` en `api.js` unificando las ≈8 llamadas.
**Commit:** NO CONSTA
**Ley que sale de aquí:** El boilerplate repetido es el sitio donde se olvida el manejo de errores en uno de los ocho.
**Traza:** #1262, #1281; `api.js`, `getJSON`.

## [2026-07-10] — Los 7 endpoints leían la entrada JSON cada uno a su manera
**Categoría:** carencia
**Síntoma:** Los 7 endpoints PHP repetían la lectura y validación del cuerpo JSON de la petición.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La auditoría de seguridad (SEC-05) y el backtest completo dieron VERDE sobre esos 7 endpoints duplicados.
**Causa raíz:** Sin helper común en la capa HTTP.
**Cómo se cazó:** test (auditoría de duplicación, PHP-1).
**Arreglo aplicado:** `leerEntradaJsonOResponder` en `http.php`, usado por los 7 endpoints.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si la validación de entrada está copiada en 7 endpoints, el que la endurezca en uno dejará seis abiertos.
**Traza:** #1262, #1281; `http.php`, `leerEntradaJsonOResponder`.

## [2026-07-10] — El año se sacaba con .slice(0,4) frágiles repartidos
**Categoría:** datos
**Síntoma:** La extracción del año de una fecha se hacía con `.slice(0,4)` sueltos por el código, descritos explícitamente como "frágiles".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Todas las pruebas de fechas y años pasaban en verde con esos slices por medio.
**Causa raíz:** Manipulación de fechas por corte de cadena en lugar de un helper.
**Cómo se cazó:** test (auditoría de duplicación, JS-4).
**Arreglo aplicado:** `anioDe` movido a `util.js` + nuevo `rangoAnios`, eliminando los `.slice(0,4)`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Cortar cadenas de fecha a mano es una bomba de relojería (y ese mismo día se perdió un año de nacimiento por un toggle).
**Traza:** #1262, #1281; `util.js`, `anioDe`, `rangoAnios`.

## [2026-07-10] — Formateadores de fecha duplicados
**Categoría:** carencia
**Síntoma:** Varias funciones de formateo de fecha repetidas por el código.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ 463/463 verdes.
**Causa raíz:** Sin módulo de utilidades centralizado para fechas (parcialmente resuelto en Q1, no del todo).
**Cómo se cazó:** test (auditoría de duplicación, JS-3).
**Arreglo aplicado:** Formateadores movidos a `util.js`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si tras una limpieza siguen quedando duplicados del mismo tipo, la limpieza no fue sistemática.
**Traza:** #1262, #1281; `util.js`.

## [2026-07-10] — slugify duplicado
**Categoría:** carencia
**Síntoma:** La función `slugify` estaba repetida.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA prueba específica; el conjunto estaba en verde.
**Causa raíz:** Helper no centralizado.
**Cómo se cazó:** test (auditoría de duplicación, JS-6).
**Arreglo aplicado:** Movido a `util.js`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Los helpers pequeños son los que más se duplican porque "cuesta menos reescribirlos".
**Traza:** #1262, #1281; `util.js`, `slugify`.

## [2026-07-10] — descargarArchivo duplicado
**Categoría:** carencia
**Síntoma:** La función de descarga de archivo estaba repetida.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA prueba específica; el conjunto estaba en verde.
**Causa raíz:** Helper no centralizado.
**Cómo se cazó:** test (auditoría de duplicación, JS-7).
**Arreglo aplicado:** Movido a `util.js`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Lo trivial se duplica sin resistencia.
**Traza:** #1262, #1281; `util.js`, `descargarArchivo`.

## [2026-07-10] — La auditoría clasificó PHP-4 como "seguro" y no lo era
**Categoría:** silencio falso
**Síntoma:** PHP-4 (unificar `Personas::etiqueta`) estaba en el bloque "✅ Recomendado ANTES de publicar / riesgo bajo, verificable 1:1". Al aplicarlo apareció un riesgo de dependencia real (un ciclo) invisible en el diagnóstico: `relacion.php` no carga `Personas`, así que llamar a `Personas::etiqueta` desde el mensaje de error daría un fatal "class not found".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ **La propia auditoría de duplicación**, que clasificó los 26 hallazgos por riesgo y metió PHP-4 en el saco de los seguros. El diagnóstico mintió.
**Causa raíz:** El análisis de riesgo no detectó la dependencia cíclica entre clases (el grafo real es `Fechas`←`Relaciones`←`Personas`).
**Cómo se cazó:** casualidad / al ejecutar (Claude Code se paró al intentarlo, como se le había pedido).
**Arreglo aplicado:** Se abortó PHP-4 y se aplazó a la tanda Q2. Se aplicaron 10 de los 11.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Una clasificación de riesgo hecha leyendo el código es una hipótesis, no un hecho. "Deduplicar" moviendo un método a otra clase exige comprobar que TODOS los llamadores cargan esa clase.
**Traza:** #1262, #1281, #1285; `Personas::etiqueta`, `Relaciones::etiqueta`, `relacion.php`, `docs/AUDITORIA-DUPLICACION.md`.

## [2026-07-10] — No había páginas de error personalizadas
**Categoría:** carencia
**Síntoma:** Cualquiera que tecleara una URL inexistente vería el 404 genérico y feo del servidor, no una página con la marca.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La auditoría de seguridad había cubierto los errores **de la API** (400/401/500 en JSON, SEC-05) y eso se dio por "errores resueltos". Las páginas HTML para humanos nunca se plantearon.
**Causa raíz:** Se confundió "respuestas de error de la API" con "páginas de error del sitio". No había `ErrorDocument` en el `.htaccess`.
**Cómo se cazó:** usuario ("tampoco hemos configurado las páginas de error 400 no?")
**Arreglo aplicado:** `public/error/404.html`, `403.html`, `500.html` autónomas (CSS y logo SVG en línea, sin recursos externos, CSP propia en `<meta>`), configuradas con `ErrorDocument` en `public/.htaccess`. Verificado que la API sigue devolviendo JSON en 7 endpoints. Backtest 58/58.
**Commit:** NO CONSTA
**Ley que sale de aquí:** "Los errores están cubiertos" puede significar dos cosas distintas. Pregunta cuál.
**Traza:** #1286, #1287, #1289, #1294, #1295; `public/error/*.html`, `public/.htaccess`, `ErrorDocument`.

## [2026-07-10] — Dos rojos que eran del test, no del código
**Categoría:** aviso falso
**Síntoma:** En la primera pasada del backtest de páginas de error salieron 2 fallos que no existían: un regex del test confundía el namespace `xmlns` del SVG con un recurso externo, y una expectativa de código HTTP equivocada en `api/foto.php`.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Caso inverso: el test dio ROJO sobre código correcto. "La lógica de las páginas y del .htaccess siempre fue correcta".
**Causa raíz:** Test mal escrito (regex demasiado laxo + expectativa de status equivocada).
**Cómo se cazó:** test (el propio ejecutor al investigar los rojos).
**Arreglo aplicado:** Corregidos los dos tests.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Cuando un test falla, la primera sospechosa es la prueba. Un test mal escrito quema tiempo y confianza.
**Traza:** #1294, #1295; `api/foto.php`, backtest de páginas de error.

## [2026-07-10] — El .gitignore protegía por lista negra
**Categoría:** seguridad
**Síntoma:** La sección de `config/` del `.gitignore` era una lista negra (ignorar ficheros concretos). Cualquier fichero de secretos con un nombre no previsto (por ejemplo un `config.php.*bak` de swap) se habría colado al repositorio público.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El `.gitignore` **ya había sido "verificado" en la Tanda Q1** y se daba por bueno.
**Causa raíz:** Enfoque de exclusión por denegación en vez de por permiso.
**Cómo se cazó:** test (remate de acabado pre-git).
**Arreglo aplicado:** `config/*` con lista blanca: se ignora todo salvo la plantilla y el `.htaccess`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Para secretos, deniega todo y permite lo explícito. La lista negra solo protege de lo que ya imaginaste.
**Traza:** #1303–#1305; `.gitignore`, `config/config.example.php`, `config/.htaccess`.

## [2026-07-10] — El .gitignore referenciaba un .gitkeep que no existía
**Categoría:** carencia
**Síntoma:** El `.gitignore` excluía `almacen/backups/*` salvo un `.gitkeep`... que no existía en el disco.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La verificación previa del `.gitignore` (Q1) dio verde sin detectar la referencia rota.
**Causa raíz:** Se escribió la regla sin crear el fichero.
**Cómo se cazó:** test (remate de acabado pre-git).
**Arreglo aplicado:** Creado `almacen/backups/.gitkeep`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Una regla de ignore que apunta a un fichero inexistente deja una carpeta sin versionar sin que nadie lo note.
**Traza:** #1304; `.gitignore`, `almacen/backups/.gitkeep`.

## [2026-07-10] — config.example.php no documentaba el flag debug
**Categoría:** carencia
**Síntoma:** La plantilla de configuración no mencionaba el flag opcional `debug`, así que quien instalara no sabía que existe ni que en producción debe ir a `false`.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA: la plantilla estaba dada por buena ("ya existía, bien documentado y sin secretos").
**Causa raíz:** El flag se añadió al código y no a la plantilla.
**Cómo se cazó:** test (remate de acabado pre-git).
**Arreglo aplicado:** Añadido y documentado en `config.example.php`.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un flag que no está en la plantilla de config no existe para quien instala; y un `debug` olvidado en producción es un agujero.
**Traza:** #1304; `config/config.example.php`.

## [2026-07-10] — Al compartir el enlace salía pelado (sin Open Graph)
**Categoría:** carencia
**Síntoma:** El sitio no tenía metadatos web ni Open Graph: compartir el enlace no producía previsualización.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Todo el backtest final (463/463) y el veredicto "listo para publicar" con el `<head>` sin metadatos.
**Causa raíz:** Nunca se añadieron; nadie los pidió hasta el repaso de acabado.
**Cómo se cazó:** ojo humano (el asistente al "estrujarse" buscando qué faltaba, tras pedírselo el usuario)
**Arreglo aplicado:** `<title>` + `meta description` + 9 etiquetas Open Graph + Twitter Card, con imagen propia 1200×630 (`public/assets/img/og.png`); URLs absolutas desde el host (`sitioBase()`). Verificado que la previsualización **no** expone datos del usuario.
**Commit:** NO CONSTA
**Ley que sale de aquí:** El `<head>` es parte del producto; ninguna suite de pruebas funcional lo mira.
**Traza:** #1301–#1304; `public/index.php` `<head>`, `sitioBase()`, `public/assets/img/og.png`.

## [2026-07-10] — No había CHANGELOG
**Categoría:** carencia
**Síntoma:** Un proyecto que se publica con versión 1.0.0 y que planea actualizaciones no tenía registro de cambios.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA.
**Causa raíz:** Pieza de publicación no planificada.
**Cómo se cazó:** ojo humano (repaso de acabado).
**Arreglo aplicado:** `CHANGELOG.md` en formato Keep a Changelog + SemVer, con entrada [1.0.0] · 2026-07-10.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Si hay versión, hay changelog; si no, la versión no significa nada.
**Traza:** #1301, #1302, #1304; `CHANGELOG.md`.

## [2026-07-10] — El asistente coló una errata en el prompt de la licencia
**Categoría:** visual
**Síntoma:** El prompt generado decía "el bloelectrónicoque NOTICE" en vez de "el bloque NOTICE". El propio asistente tuvo que avisar y pedir que se corrigiera al pegarlo.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA; el prompt se entregó y el error se detectó al releerlo.
**Causa raíz:** Corrupción de texto en la generación del prompt.
**Cómo se cazó:** ojo humano (el propio asistente, tras entregarlo).
**Arreglo aplicado:** Aviso al usuario para corregirlo al pegar.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Los artefactos que generas para otra IA también hay que releerlos antes de entregarlos.
**Traza:** #1307.

## [2026-07-10] — La licencia de html-to-image sin trazabilidad
**Categoría:** carencia
**Síntoma:** El fichero vendorizado de `html-to-image` no lleva versión ni cabecera de licencia embebida, así que la atribución tuvo que citarse desde el repositorio oficial en vez de desde el propio archivo.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La revisión de licencias de las otras tres dependencias (D3 ISC, family-chart MIT, jsPDF MIT) sí se leyó de los propios ficheros; solo esta quedó sin fuente local.
**Causa raíz:** Se vendorizó una build sin cabecera.
**Cómo se cazó:** test (revisión de licencias de terceros).
**Arreglo aplicado:** Ninguno. Se decidió dejarlo así (se cita la licencia desde el repositorio oficial; suficiente legalmente).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Vendoriza siempre la build con su cabecera de licencia; si no, la trazabilidad depende de tu memoria.
**Traza:** #1310, #1311; `assets/vendor/` (html-to-image), `THIRD-PARTY-NOTICES.md`, `NOTICE`, `LICENSE`.

## [2026-07-10] — Las capturas del árbol salían recortadas
**Categoría:** visual
**Síntoma:** Las capturas del árbol (el "hero" del README, lo que más impacta) se recortaban arriba y abajo.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El backtest del README las dio por generadas y enlazadas correctamente; la validación comprobaba que las imágenes **existen**, no que se vean bien.
**Causa raíz:** Encuadre natural del navegador sin usar "ver todo".
**Cómo se cazó:** ojo humano (lo reportó Claude Code y lo confirmó el asistente).
**Arreglo aplicado:** Regeneradas con "ver todo" / árbol completo bien encuadrado.
**Commit:** NO CONSTA
**Ley que sale de aquí:** "La imagen existe y se enlaza" no es "la imagen se ve bien". El ojo humano no es sustituible en lo visual.
**Traza:** #1321, #1327; `docs/capturas/arbol-claro.png`, `arbol-oscuro.png`.

## [2026-07-10] — El README no enseñaba el panel de administración
**Categoría:** carencia
**Síntoma:** Las 4 capturas del README (árbol claro, ficha, editar, árbol oscuro) mostraban solo visualización y edición. **Ni una del panel de administración**, la pieza que demuestra que es una aplicación de gestión y no un visualizador.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ **El asistente había leído el README entero y dictaminado "está muy bien hecho", y justo antes había confirmado "el contenido del README está todo bien y completo" con la lista de comprobación ✓ por ✓.** El backtest de Claude Code también lo dio por bueno. El hueco lo vio el usuario.
**Causa raíz:** El prompt de capturas enumeró qué mostrar (árbol, ficha, editor, oscuro) y nadie preguntó qué faltaba.
**Cómo se cazó:** usuario ("hay 3 capturas pero en ningún momento se mete en la administración... por eso te digo que revises a conciencia").
**Arreglo aplicado:** Añadidas capturas de panel de administración, móvil/responsive e instalador, y galería reorganizada.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Revisar es preguntar "¿falta algo que debería estar?", no solo "¿está bien lo que hay?".
**Traza:** #1321, #1323–#1327; `README.md`, `docs/capturas/`.

## [2026-07-10] — El escaparate enseñaba un árbol sin caras
**Categoría:** carencia
**Síntoma:** Las capturas mostraban el árbol con aros y muñequitos porque el demo Gil **no tenía ninguna foto cargada**: no se enseñaba una de las funciones más vistosas de la app.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Todos los backtests del demo lo dejaban explícitamente "limpio: 0 fotos / 0 copias" y eso se celebraba como estado correcto — el propio criterio de "demo limpio" garantizaba que las capturas salieran sin caras.
**Causa raíz:** El demo se mantenía sin fotos por higiene de pruebas; nadie pensó en el demo como escaparate.
**Cómo se cazó:** usuario.
**Arreglo aplicado:** 12 rostros de personas inexistentes (thispersondoesnotexist / StyleGAN2), pasados por el pipeline real (512px, JPEG, sin EXIF), asignados al demo permanente, con la procedencia documentada; capturas regeneradas.
**Commit:** NO CONSTA
**Ley que sale de aquí:** "Demo limpio" y "demo que luce" son objetivos opuestos: decide cuál sirve a cada momento.
**Traza:** #1328, #1329, #1331, #1337, #1346; `db/demo-fotos/`, `db/datos-demo.sql`, `almacen/fotos/`, `docs/capturas/`.

## [2026-07-10] — El control "Vista" no aparecía en ninguna captura
**Categoría:** carencia
**Síntoma:** El popover "Vista" (orientación V/H + generaciones de antepasados/descendientes), una función potente, no estaba capturado en el README.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ Mismo silencio: la revisión del README del asistente lo dio por completo (#1321/#1323).
**Causa raíz:** Lista de capturas incompleta desde el prompt inicial.
**Cómo se cazó:** usuario.
**Arreglo aplicado:** Captura del control "Vista" desplegado, añadida a la sección de Visualización.
**Commit:** NO CONSTA
**Ley que sale de aquí:** El escaparate se diseña listando funciones, no improvisando pantallazos.
**Traza:** #1328, #1329, #1332; `README.md`, `docs/capturas/`.

## [2026-07-10] — Las fotos del demo estaban ignoradas por git: el demo no habría viajado con caras
**Categoría:** datos
**Síntoma:** Las fotos viven en `almacen/fotos/`, que está (correctamente) ignorado por git. Poner fotos al demo no habría servido de nada: quien clonara e instalara vería el árbol sin caras.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La política de `.gitignore` estaba verificada y en verde — y era precisamente lo que rompía el demo. Se detectó **antes** de asignar las fotos, al planificar.
**Causa raíz:** El demo con fotos necesita un hogar versionado propio; `almacen/` es datos vivos, no assets.
**Cómo se cazó:** test (Claude Code lo anticipó al investigar; el asistente lo llamó "el aviso de fontanería").
**Arreglo aplicado:** `db/demo-fotos/` versionado (12 jpg + README) y el instalador/seed lo copia a `almacen/fotos/` al instalar. Verificado con simulación git: `db/demo-fotos/` SÍ se versiona, `almacen/fotos/` sigue ignorado.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Los datos de demo son código (van al repo); los datos vivos no. Confundirlos deja el demo vacío en casa del que instala.
**Traza:** #1333, #1337, #1346, #1369; `db/demo-fotos/`, `almacen/fotos/`, `db/datos-demo.sql`, `db/cargar-demo.php`, `.gitignore`.

## [2026-07-10] — El plan aprobado de rostros era irrealizable
**Categoría:** carencia
**Síntoma:** Se aprobó "que Claude Code investigue y genere/consiga rostros por IA con licencia comercial"; al llegar el momento resultó que **ni el asistente ni Claude Code generan imágenes**. Hubo que rehacer la decisión.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA. La recomendación ("generarlos con una herramienta cuyos términos te cedan el uso comercial") se dio por buena y se aprobó formalmente antes de comprobar que era ejecutable.
**Causa raíz:** Se planificó una tarea sin verificar la capacidad real de la herramienta que iba a ejecutarla.
**Cómo se cazó:** usuario ("¿hay alguna manera de que Claude Code los genere?").
**Arreglo aplicado:** Cambio de vía: descargar rostros de personas inexistentes de thispersondoesnotexist, documentando la procedencia en el repo.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Antes de aprobar un plan, comprueba que quien lo ejecuta puede hacerlo.
**Traza:** #1330, #1332–#1337; `THIRD-PARTY-NOTICES.md`, `README.md`, `db/demo-fotos/README`.

## [2026-07-10] — Los tres mayores del demo salieron con cara de 55, no de 80
**Categoría:** visual
**Síntoma:** Las personas de 80, 76 y 73 años del demo recibieron rostros de ~55-62 años. Se nota sobre todo en la ficha grande.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ El backtest de las fotos dio **13/13 verde** (pipeline correcto, 512px, sin EXIF, demo recarga con las fotos en las personas correctas): verificaba la fontanería, no la verosimilitud.
**Causa raíz:** El dataset del generador tiene pocas caras de 70-80 años.
**Cómo se cazó:** ojo humano (lo reportó Claude Code voluntariamente).
**Arreglo aplicado:** NINGUNO. Se decidió dejarlo, con el argumento de que una foto "de hace años" de un abuelo es realista en un árbol familiar.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un 13/13 sobre el proceso no dice nada sobre si el resultado es creíble.
**Traza:** #1346, #1347; `db/demo-fotos/`, `db/datos-demo.sql`.

## [2026-07-10] — El README "completo y correcto" al que le faltaban tres secciones
**Categoría:** silencio falso
**Síntoma:** Se declaró el README completo (#1323: "el contenido del README está todo bien y completo", con checklist ✓). Al revisarlo de nuevo faltaban: la **motivación** (el porqué del proyecto), la **seguridad** estaba diluida en un bullet pese a ser el trabajo más grande del proyecto, y no había **hoja de ruta**.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ La revisión del asistente ("está todo lo que debe estar ✓✓✓") y el backtest de Claude Code del README (13 cabeceras coherentes, anclas resueltas, 10/10 imágenes, HTML balanceado). Todo verde con las tres carencias vivas.
**Causa raíz:** La revisión comprobó que estaban las secciones **estándar**, no que el README contara lo que hacía fuerte a este proyecto.
**Cómo se cazó:** usuario ("¿el README está bien detallado? ¿no falta nada que profundizar?").
**Arreglo aplicado:** Añadida sección "Por qué Linaje" (motivación real: los datos que el padre del autor había recopilado y el riesgo de perderlos), sección propia de "Seguridad" (extraída del bullet), y "Hoja de ruta".
**Commit:** NO CONSTA
**Ley que sale de aquí:** "Tiene todas las secciones estándar" no es "está completo". Lo que te distingue es justo lo que las plantillas no piden.
**Traza:** #1321, #1323, #1338–#1340, #1350–#1352; `README.md`.

## [2026-07-10] — Renombrar la carpeta tumbó la web: 403 Forbidden
**Categoría:** despliegue
**Síntoma:** Tras renombrar la carpeta del proyecto, la URL local devolvía **403 Forbidden**. La app dejó de cargar.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA prueba previa. El asistente había avisado genéricamente ("si la URL cambiara o dejara de funcionar, avísame"), pero nadie verificó de antemano cómo estaba mapeado el sitio antes de mover la carpeta.
**Causa raíz:** El mapeo del sitio local no era un vhost `.conf`, sino un **symlink** dentro del `www\` de Laragon apuntando directamente a la subcarpeta `public/` de la ruta **vieja**. Al renombrar, el symlink quedó colgando → Apache no podía resolver el document root → 403.
**Cómo se cazó:** usuario (abrió la URL: "me dice que la web es forbidden").
**Arreglo aplicado:** Borrado el symlink roto y recreado como **junction** (`mklink /J`, no requiere elevación) apuntando a la nueva ruta. Verificado 200 OK sin reiniciar Apache. Se dejó anotado el procedimiento para futuros renombrados.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un cambio "puramente cosmético" (renombrar una carpeta) rompe todo lo que apunte a ella por ruta absoluta. Inventaría los enlaces antes de mover.
**Traza:** #1355, #1356, #1358, #1359, #1361–#1364; symlink/junction de Laragon `www\<sitio>` → `public/`, `hosts`, Apache.

## [2026-07-10] — El asistente diagnosticó mal el 403 (culpó al vhost)
**Categoría:** despliegue
**Síntoma:** El asistente afirmó en el prompt de diagnóstico que "probablemente el archivo de configuración de Apache (algo como `sites-enabled/auto.genealogia.test.conf`) sigue apuntando a la ruta VIEJA → de ahí el Forbidden".
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA: fue una hipótesis, no una prueba. La comprobación posterior la refutó: "Ningún `.conf` de Apache mencionaba el sitio (se descartó el vhost como causa)".
**Causa raíz:** El asistente supuso el mecanismo estándar de Laragon (auto vhost por `.conf`) sin haber visto la instalación real; era un symlink.
**Cómo se cazó:** test (Claude Code investigó en lugar de aceptar la hipótesis).
**Arreglo aplicado:** Se descartó el vhost y se arregló el symlink.
**Commit:** NO CONSTA
**Ley que sale de aquí:** Un prompt que afirma la causa en vez de pedirla sesga la investigación. Di el síntoma, no tu corazonada.
**Traza:** #1362, #1363; `sites-enabled/*.conf`, symlink de Laragon.

## [2026-07-10] — El .gitignore verificado que habría subido las notas internas a GitHub
**Categoría:** seguridad
**Síntoma:** Los `.md` internos de trabajo (CLAUDE.md, ESTADO.md, ESTADO-Y-DECISIONES.md, PENDIENTES.md, NOTAS-LIBRERIA.md y los `docs/PLAN-*`, `BACKTEST-FINAL.md`, `AUDITORIA-DUPLICACION.md`, `DESPLIEGUE-Y-SEGURIDAD.md`) **se habrían subido al repositorio público**, exponiendo el dominio personal del autor, rutas absolutas de su máquina, TODOs y los planes de QA/seguridad (que dan pistas de cómo está montada la defensa).
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** ⭐ **La simulación de `git add -A` del remate de acabado (#1304) dio VERDE explícito: "98 ficheros se versionarían y NADA sensible se cuela"** — y esos 98 incluían los `.md` internos. La prueba solo buscaba credenciales, no información sensible del autor. La siguiente simulación, más exhaustiva, los destapó.
**Causa raíz:** La definición de "sensible" del primer chequeo era demasiado estrecha (secretos técnicos) y dejaba fuera datos personales y notas internas.
**Cómo se cazó:** test (verificación de seguridad exhaustiva pre-GitHub, pedida antes de crear el repo).
**Arreglo aplicado:** Reglas ancladas a la raíz en el `.gitignore` (`/CLAUDE.md`, `/ESTADO.md`, `/ESTADO-Y-DECISIONES.md`, `/PENDIENTES.md`, `/NOTAS-LIBRERIA.md`, `/docs/*.md`) sin tocar `docs/capturas/` ni los `.md` públicos. Recuento 126 → 115 ficheros. Los documentos siguen en disco.
**Commit:** NO CONSTA
**Ley que sale de aquí:** "Nada sensible se cuela" depende de tu definición de sensible. Los datos personales y las notas internas no son credenciales, pero también se filtran.
**Traza:** #1304, #1364–#1369; `.gitignore`, `docs/`, `docs/capturas/`.

## [2026-07-10] — Un repositorio público vacío colgando en la cuenta
**Categoría:** seguridad
**Síntoma:** De los 8 repositorios de la cuenta de GitHub, uno era **público y estaba completamente vacío** (0 archivos): la única cara visible de la cuenta de cara a un reclutador.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA: nadie había mirado el estado de la cuenta hasta el inventario, hecho el mismo día de publicar.
**Causa raíz:** Repositorio de prueba creado y abandonado sin cerrar.
**Cómo se cazó:** test (inventario de la cuenta con `gh repo list`, pedido por el usuario antes de borrar nada).
**Arreglo aplicado:** El usuario lo pasó a privado desde la web (pendiente de comprobación en #1391-#1392).
**Commit:** NO CONSTA
**Ley que sale de aquí:** Tu escaparate incluye lo que dejaste abandonado. Inventaría antes de publicar.
**Traza:** #1377–#1379, #1389–#1392; cuenta de GitHub (no del repo).

## [2026-07-10] — La identidad de git iba a firmar los commits públicos con otro nombre y el correo real
**Categoría:** seguridad
**Síntoma:** La configuración global de git tenía otra identidad (un alias distinto del que el usuario quería para este proyecto), y el email por defecto habría quedado **público en cada commit** del repositorio.
**Qué se probó y DIO VERDE mientras el fallo estaba vivo:** NO CONSTA: no se detectó hasta el inventario de la cuenta, en el último paso antes del primer commit.
**Causa raíz:** Configuración global heredada de otros proyectos, nunca revisada para uno público.
**Cómo se cazó:** test (inventario de la cuenta de GitHub; Claude Code lo señaló y pidió confirmación del email).
**Arreglo aplicado:** `git config` **local** solo para este repo con el nombre elegido y el **email anónimo `@users.noreply.github.com`**; activadas en GitHub "Keep my email addresses private" y "Block command line pushes that expose my email". La config global quedó intacta.
**Commit:** NO CONSTA
**Ley que sale de aquí:** El primer commit público hereda la identidad que llevabas puesta. Configúrala **antes**, no después: el historial no se borra.
**Traza:** #1377–#1379, #1382–#1385; `git config` local del repo.

---

# Cobertura, recuento y dudas para el enrutador

## Recuento total

**272 entradas** de fallo, repartidas por día:

| Día | Entradas |
|---|---|
| 2026-07-06 | 71 |
| 2026-07-07 | 37 |
| 2026-07-08 | 38 |
| 2026-07-09 | 76 |
| 2026-07-10 | 50 |
| **Total** | **272** |

> El día 09 pasó de 63 fallos brutos en el volcado a 76 entradas porque los
> hallazgos que el extractor había **agrupado** (SEC-11/12/13 del instalador;
> SEC-16/17/18/19; los CAL-06/07/08/09 de calidad) se separaron en una entrada
> por fallo, y se recuperaron de la documentación tres fallos de calidad
> (CAL-03/04/05) que no estaban desagregados en la conversación.
>
> En esta segunda revisión (respuestas del enrutador) se **eliminaron 2 entradas
> del 10-jul** —"Dos taglines distintos" y "El badge dice PHP 8.0+"— porque el
> enrutador comprobó contra el README publicado que no eran divergencias reales:
> no eran fallos, sino una falsa alarma del asistente durante el desarrollo. El
> total bajó de 274 a 272.

## Desglose por categoría (aproximado)

| Categoría | Nº |
|---|---|
| carencia | 66 |
| visual | 54 |
| datos | 39 |
| rompe | 36 |
| seguridad | 33 |
| aviso falso | 20 |
| silencio falso | 13 |
| despliegue | 11 |
| **Total** | **272** |

(El desglose es orientativo: algunos fallos podrían encajar en dos categorías; se asignó la dominante. De las 272 entradas, **162 llevan campo estrella ⭐ con una prueba concreta que mintió**; el resto tienen el campo estrella en NO CONSTA — sobre todo fallos visuales del 06-jul vistos directamente por el usuario y hallazgos de auditoría del 09-jul detectados por lectura de código sin que hubiera pasado antes por una prueba verde.)

## Concentración de los NO CONSTA

- **Commit: NO CONSTA en las 261 entradas.** No es un hueco de investigación: el repositorio se publicó con todo el desarrollo en un único commit inicial (`ad23621`) y no existe historial `fix:`. No se puede fechar por git el arreglo de ningún fallo concreto. Cruce hecho: `git log`, `git show --stat` de los dos commits, y el CHANGELOG (que solo tiene la entrada 1.0.0).
- **Campo estrella (⭐) NO CONSTA:** se concentra en los fallos **visuales del día 06** (muchos son "el usuario lo vio en una captura" sin que constara una verificación previa que hubiera dado verde) y en varios hallazgos de auditoría del **día 09** detectados por lectura de código (Frente B) que nunca habían pasado por una prueba que mintiera: simplemente el código estaba mal y nadie lo había ejecutado. En esos casos el ⭐ es honestamente NO CONSTA, no un hueco disimulado.
- **Causa raíz NO CONSTA:** puntual (p. ej. por qué se caía el servidor local el 07-jul; el detalle técnico exacto del toggle año/fecha del 10-jul). Marcadas literalmente.

## Cobertura declarada

- **Conversación:** procesados los 1.392 mensajes íntegros, partidos por día en 17 tramos (06: #1–301 · 07: #302–692 · 08: #693–1009 · 09: #1010–1144 · 10: #1145–1392). Sin huecos.
- **Documentación interna:** leídos enteros `PLAN-QA-SEGURIDAD.md`, `BACKTEST-FINAL.md`, `AUDITORIA-DUPLICACION.md`, `PENDIENTES.md`, `ESTADO-Y-DECISIONES.md`, `ESTADO.md`, `NOTAS-LIBRERIA.md`, `DESPLIEGUE-Y-SEGURIDAD.md`.
- **Deduplicación:** el día 09 y el barrido de documentación describían en gran parte los mismos hallazgos (SEC-\*, INT-\*, VAL-\*, CONC-\*, CAL-\*, HIG-\*). Se fusionaron en una sola entrada por fallo, quedándose con la versión más rica y añadiendo el código de hallazgo en la traza. Donde el volcado del día 09 había **agrupado** varios fallos (SEC-11/12/13 del instalador; SEC-16/17/18/19; los CAL de calidad), se **separaron** en entradas individuales con el detalle de la documentación, siguiendo la regla "una entrada por fallo".
- **Códigos de hallazgo cubiertos:** los 51 códigos que aparecen en la documentación (SEC-01…20, INT-01…10, CONC-01…05, VAL-01…04, CAL-01…09, HIG-01…04). Tras la búsqueda de la segunda revisión, el estado de los tres que se saltaban es:
  - **SEC-07** — NO es una entrada omitida. Aparece **una sola vez** en todo el repo y el volcado: en la cabecera de la sección §2.2 del `PLAN-QA-SEGURIDAD.md` ("Relleno raro — motiva `VAL-01..04`, `SEC-07`, `SEC-14`"). No tiene definición, ni caso `TA-*`, ni hallazgo confirmado bajo ese código. La prueba de inyección por campos de texto de esa sección (`TA-2.2`: `<script>`, `'; DROP TABLE`, comillas) salió **SEGURA** ("guardados literal, tabla intacta, script no ejecutado"). Es decir, SEC-07 fue una etiqueta que motivó una prueba, y la prueba pasó: **no se materializó como fallo**. Sin entrada, correctamente.
  - **INT-05** — NO aparece en ninguna parte: 0 apariciones en el volcado y 0 en toda la documentación. Es un **salto de numeración interna** (INT va del 04 al 06). No hay hallazgo que registrar.
  - **VAL-04** — EXISTE y está documentado, pero **no es un fallo**: `PLAN-QA-SEGURIDAD.md` (§727) y `DESPLIEGUE-Y-SEGURIDAD.md` lo recogen bajo "Elecciones de producto (no son fallos)" — el `sexo` es opcional en el servidor **a propósito**, para admitir tarjetas anónimas que se pintan neutras (`Personas.php:52` mapea a `NULL`). Decisión de producto confirmada, no un descuido. Sin entrada, correctamente.

### Lo que NO puedo garantizar (sin fingir cobertura total)

1. **El backtest final de 463 pruebas (10-jul) SÍ está completo en el repo** (`docs/BACKTEST-FINAL.md`) y se ha revisado entero: lista exactamente tres hallazgos — F-1 (favicon), F-2 (toggle año) y F-3 (clave demo) — y declara "un único bug real" (F-2). Los tres tienen entrada. **No falta ningún F-N.** Se cierra la reserva que había en la primera versión sobre este informe.
2. Otros informes de Claude Code sí quedan solo resumidos en el volcado (la refactorización del subconjunto seguro Q2, la auditoría de duplicación de 26 hallazgos, el resultado de las fotos del demo). Ahí solo consta lo que el asistente destacó; **puede haber detalles menores de esos informes originales que no llegaran al volcado**.
3. Faltan del volcado varios mensajes de USUARIO (sobre todo el 06-jul): algunas quejas se deducen de la respuesta del asistente que las cita. Se anotó el fallo solo cuando quedaba explícito.
4. Las capturas de pantalla y los volcados de consola a los que se alude no están; los síntomas visuales se describen tal como los relatan usuario y asistente, no verificados de primera mano.

## Dudas — todas cerradas (segunda revisión)

Las seis dudas de la primera versión quedan resueltas. Se conserva el registro de cómo se cerró cada una, para trazabilidad.

1. **SEC-07 / INT-05 / VAL-04** — CERRADA (búsqueda en volcado + repo). Ninguna genera entrada nueva: SEC-07 es solo una etiqueta motivadora de una prueba de inyección que salió segura; INT-05 no existe (salto de numeración); VAL-04 es una decisión de producto documentada, no un fallo. Detalle en "Códigos de hallazgo cubiertos", arriba.
2. **El bug "0 padres en pantalla / filiación dormida en la BD"** — CERRADA (enrutador). Es UN solo fallo, exactamente la entrada del 09-jul "El rehacer dejaba un huérfano en la papelera (corrupción invisible)". No hay dos fallos fundidos. Entrada intacta.
3. **Backtest final de 463 pruebas (10-jul)** — CERRADA (revisión de `docs/BACKTEST-FINAL.md`). El informe está completo en el repo: exactamente F-1, F-2 y F-3, "un único bug real" (F-2). Los tres tienen entrada. No falta ningún F-N.
4. **Campo estrella de los fallos visuales del 06-jul** — CERRADA (enrutador). El enrutador no recuerda si alguno se declaró "verificado ✓" antes de rechazarlo; se quedan en NO CONSTA. No se infiere ni se rellena.
5. **Servidor local caído a mitad de verificación (07-jul, #304)** — CERRADA (lectura del volcado #304→#307). El asistente **aprobó F3** en #305 sobre el informe con la nota del servidor caído dentro, y la tabla **NO se repitió** con el servidor vivo: eso es el verde que mintió, y ya está en el campo estrella de la entrada. La causa de la caída sigue siendo NO CONSTA (el informe no la da). Entrada reforzada.
6. **Taglines y badge PHP del README (10-jul)** — CERRADA (enrutador, contra el README publicado). NO eran fallos: el badge PHP 8.0+ es coherente con el Stack y los Requisitos, y hay un único tagline real. Fue una falsa alarma del asistente durante el desarrollo. **Las dos entradas se eliminaron** de la bitácora.

---

*Documento generado a partir del histórico completo de la conversación (fuera del repo) y de la documentación interna. Ningún dato sensible (IP, usuario SSH, nombres de BD, claves de la demo) se ha volcado aquí: los fallos de seguridad que giran sobre esos datos se describen sin el dato.*
*Primera versión: extracción y consolidación (274 entradas). Segunda versión (esta): cierre de las seis dudas del enrutador — 2 entradas eliminadas (no eran fallos), 1 reforzada (#304), 3 códigos de hallazgo resueltos en cobertura. **Total final: 272 entradas.***
