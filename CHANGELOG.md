# Registro de cambios

Todos los cambios relevantes de **Linaje** se documentan en este archivo.

El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/)
y el proyecto se adhiere al [Versionado Semántico](https://semver.org/lang/es/).

## [1.0.0] · 2026-07-10

Primera versión pública de **Linaje**, una aplicación para crear, explorar y
compartir tu árbol genealógico en tu propio servidor, con tus datos siempre bajo
tu control.

### Añadido

- **Árbol genealógico visual.** Representación interactiva de la familia con
  tarjetas conectadas: zoom, desplazamiento, orientación vertical u horizontal,
  control del número de generaciones y «volver al inicio». Basado en la librería
  family-chart.
- **Tarjetas de persona** con foto (o un aro por sexo y edad cuando no hay foto),
  y un buscador de personas por nombre o por año.
- **Ficha de lectura** clara y en acordeón, con el cálculo automático del
  **parentesco** de cada persona respecto a la principal (padres, abuelos, tíos,
  primos, cónyuges, y demás, incluidas las segundas nupcias).
- **Edición completa** de personas: crear y modificar nombre, apellidos, sexo,
  fechas (año aproximado o fecha exacta), lugar, ocupación y notas, con
  validaciones en vivo (campos obligatorios y fechas imposibles).
- **Fotografías**: subir, cambiar o quitar la foto de cada persona. Las imágenes se
  reescalan, se limpian de metadatos (EXIF) y se sirven de forma segura.
- **Añadir familiares** (padre, madre, pareja, hijo/a) con una coreografía guiada
  que muestra dónde encaja cada nuevo miembro en el árbol.
- **Integridad garantizada**: el servidor rechaza situaciones imposibles (ciclos,
  más de dos progenitores, fechas incoherentes entre generaciones) para que el
  árbol nunca quede en un estado inválido.
- **Deshacer y rehacer** de todas las acciones de edición, con una red de
  seguridad que evita pérdidas accidentales de datos.
- **Papelera**: las personas borradas se pueden restaurar o eliminar de forma
  definitiva; se impide borrar a alguien si dejaría el árbol desconectado.
- **Copias de seguridad**: genera, descarga y restaura copias completas (datos y
  fotos) desde el servidor o desde un archivo, con copia previa automática y
  retención de las más recientes.
- **Exportación a JSON** portable y autoexplicativo, para llevar tus datos a otro
  programa o formato (sin las fotos, que se conservan en las copias de seguridad).
- **Panel de administración** con ajustes, apariencia, seguridad, gestión de datos
  e información del sistema.
- **Modo de acceso configurable**: árbol privado (requiere iniciar sesión para
  verlo) o árbol abierto (lectura pública, pero administrar siempre exige clave).
- **Instalador guiado** que pone en marcha la aplicación paso a paso (requisitos,
  conexión, estructura, primera persona y claves) y se autobloquea al terminar.
- **Seguridad**: contraseñas cifradas, protección contra XSS, CSRF e inyección
  SQL, política de seguridad de contenido (CSP) estricta, límite de intentos de
  acceso por IP y cabeceras de seguridad en todo el sitio.
- **Identidad de marca**: logo propio, favicon, tema claro y oscuro, y diseño
  responsive (móvil, tablet y escritorio).
- **Páginas de error personalizadas** (404, 403, 500) con la imagen de la marca.
- **Metadatos para compartir** (Open Graph / Twitter Card): al enviar el enlace se
  muestra una previsualización cuidada con el nombre y el logo.

[1.0.0]: https://keepachangelog.com/es-ES/1.0.0/
