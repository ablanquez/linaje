# Avisos de terceros (Third-Party Notices)

**Linaje** se distribuye bajo la [Apache License 2.0](LICENSE). Además, incluye
(«bundlea») los siguientes componentes de terceros en `public/assets/vendor/`,
cada uno bajo su propia licencia. Todas son licencias **permisivas (MIT e ISC)**,
**compatibles** con la Apache License 2.0: se pueden redistribuir dentro de un
proyecto Apache 2.0 siempre que se conserven sus avisos de copyright y licencia,
que es lo que hace este archivo.

Ninguna de estas dependencias es de tipo *copyleft* (GPL/LGPL/AGPL), por lo que no
imponen condiciones adicionales a la distribución de Linaje.

## Componentes incluidos

| Componente | Versión | Autoría | Licencia | Origen |
|------------|---------|---------|----------|--------|
| **D3.js** (`d3.min.js`) | 7.9.0 | Mike Bostock | ISC | https://d3js.org |
| **family-chart** (`family-chart.min.js`, `family-chart.css`) | 0.9.0 | donatso | MIT | https://donatso.github.io/family-chart/ |
| **jsPDF** (`jspdf.umd.min.js`) | 2.5.2 | James Hall, yWorks GmbH y contribuidores | MIT | https://github.com/parallax/jsPDF |
| **html-to-image** (`html-to-image.js`) | (versión incluida en `vendor/`) | bubkoo | MIT | https://github.com/bubkoo/html-to-image |

Los avisos de copyright originales también van embebidos en la cabecera de los
propios ficheros minificados (por ejemplo, el bloque `@license` de `jspdf.umd.min.js`
y la primera línea de `d3.min.js` y `family-chart.min.js`).

---

## Texto de las licencias

### ISC License — aplica a: D3.js

Copyright 2010–2023 Mike Bostock

Permission to use, copy, modify, and/or distribute this software for any purpose
with or without fee is hereby granted, provided that the above copyright notice
and this permission notice appear in all copies.

THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH
REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND
FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT,
OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER RESULTING FROM LOSS OF USE,
DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS
ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS
SOFTWARE.

---

### MIT License — aplica a: family-chart, jsPDF, html-to-image

Los titulares de copyright de cada componente MIT son:

- **family-chart** — Copyright (c) 2025 donatso
- **jsPDF** — Copyright (c) 2010–2021 James Hall <james@parall.ax>; 2015–2021 yWorks
  GmbH; 2015–2021 Lukas Holländer; 2016–2018 Aras Abbasi; y otros contribuidores
  (ver el bloque `@license` embebido en `jspdf.umd.min.js`).
- **html-to-image** — Copyright (c) bubkoo (https://github.com/bubkoo/html-to-image)

El texto de la licencia MIT, aplicable a los tres componentes anteriores, es:

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in the
Software without restriction, including without limitation the rights to use,
copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the
Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN
AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

---

## Fotos del árbol de demostración (rostros generados por IA)

Las fotografías del árbol de demostración «Familia Gil» (carpeta `db/demo-fotos/`,
que el cargador del demo copia a `almacen/fotos/`) son **rostros de personas que
NO EXISTEN**, generados por inteligencia artificial (**StyleGAN2**), obtenidos de
**[thispersondoesnotexist.com](https://thispersondoesnotexist.com)**.

- **No representan a ninguna persona real.** Al no existir la persona retratada, no
  hay derechos de imagen, de personalidad ni datos personales (RGPD) implicados.
- Se usan **exclusivamente como datos de demostración ficticios**, para ilustrar el
  aspecto de la aplicación. Cualquier parecido con una persona real sería casual.
- Cada imagen se ha **reescalado a 512 px y reguardado como JPEG sin metadatos
  (EXIF)** por el propio procesamiento de fotos de la aplicación.

Los datos del demo (nombres, fechas, lugares, parentescos) son igualmente
**inventados**.
