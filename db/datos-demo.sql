-- ===========================================================================
--  DATOS DE DEMOSTRACIÓN — Árbol genealógico   (db/datos-demo.sql)
--  EJEMPLO OFICIAL DEL PROYECTO · Familia "Gil" · 7 generaciones · 34 personas
-- ===========================================================================
--  ⚠️  DATOS FICTICIOS, DE EJEMPLO. Ninguna persona es real.
--  ⚠️  ESTE SCRIPT BORRA (TRUNCATE) EL CONTENIDO de las 5 tablas antes de
--      insertar el ejemplo, para poder recargarlo limpio cuantas veces haga
--      falta.  NO EJECUTARLO NUNCA SOBRE UNA BASE CON DATOS REALES.
--
--  Para qué sirve: árbol grande y ramificado que contiene AL MENOS UN CASO REAL
--  de cada parentesco que la ficha calcula (directos, ascendientes, descendientes,
--  colaterales y políticos), además de SEGUNDAS NUPCIAS (parejas múltiples),
--  medios hermanos, fechas exactas/solo-año y personas vivas/fallecidas. Se usa
--  para el backtesting y como ejemplo permanente de portafolio.
--
--  Persona central (main_id): Lucía Gil Romero (id 16), en la generación media.
--
--  Esquema de la familia (═ pareja · ⚭2ª segundas nupcias · │ filiación):
--
--   GEN A (tatarabuelos, †)   (1)Ramón Gil ═ (2)Encarnación Ferrer
--                                          │
--   GEN B (bisabuelos, †)          (3)Antonio Gil ═ (4)Josefa Marín
--                                          │
--   GEN C (abuelos, †)   (5)Miguel Gil ═ (6)Pilar Ortega    (7)Teresa Gil ═ (8)Ernesto Ledesma
--                                 │                          (Teresa = tía abuela de Lucía)
--   GEN D (padres/tíos/suegros)
--        (9)Sergio Gil ═ (10)Isabel Romero  ⚭2ª═ (11)Raquel Duarte
--        (12)Nuria Gil ═ (13)David Sáez   (tíos)
--        (14)Alberto Pérez ═ (15)Carmen Ibáñez   (suegros de Lucía)
--                                 │
--   GEN E   (16)LUCÍA Gil ═ (19)Adrián Pérez │ (17)Pablo Gil ═ (21)Elena Ríos │ (18)Iker Gil (medio hº)
--           (20)Sofía / (24)Rubén Pérez (cuñados) │ (22)Hugo / (23)Clara Sáez (primos hermanos)
--                                 │
--   GEN F   (25)Mateo ═ (27)Nerea  ⚭2ª═ (28)Laura │ (26)Alba ═ (29)Óscar │ (30)Nora Gil ═ (32)Iván Costa │ (31)Bruno Gil
--           (Mateo/Alba = hijos de Lucía · Nora/Bruno = sobrinos)
--                                 │
--   GEN G   (34)Vera Pérez (nieta de Lucía)        (33)Leo Costa (sobrino nieto de Lucía)
--
--  Cómo cargarlo: seleccionar la base "genealogia" en HeidiSQL, abrir una
--  pestaña Consulta, pegar este archivo entero y ejecutar (F9).
-- ===========================================================================


-- Asegura que el contenido (acentos, ñ…) se interpreta como UTF-8 al cargar,
-- sea cual sea el cliente (HeidiSQL o la línea de comandos).
SET NAMES utf8mb4;


-- ── 0) Limpieza previa (recarga limpia) ─────────────────────────────────────
-- TRUNCATE con las comprobaciones de clave foránea desactivadas: vacía las
-- tablas y REINICIA los AUTO_INCREMENT, dejando la base como recién instalada.
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `arb_pareja`;
TRUNCATE TABLE `arb_filiacion`;
TRUNCATE TABLE `arb_usuarios`;
TRUNCATE TABLE `arb_personas`;
TRUNCATE TABLE `arb_ajustes`;
TRUNCATE TABLE `arb_arboles`;
SET FOREIGN_KEY_CHECKS = 1;


-- ── 1) Personas ─────────────────────────────────────────────────────────────
-- Columnas: id, nombre, apellido1, apellido2, sexo, nacimiento, fallecimiento,
--           lugar, ocupacion, notas.  (avatar se deja NULL: sin foto.)
-- Fechas: "AAAA-MM-DD" = exacta, "AAAA" = solo año, "" = se desconoce / vive.
INSERT INTO `arb_personas`
  (`id`, `nombre`, `apellido1`, `apellido2`, `sexo`, `nacimiento`, `fallecimiento`, `lugar`, `ocupacion`, `notas`) VALUES
  -- GEN A — Tatarabuelos (fallecidos)
  ( 1, 'Ramón',       'Gil',    'Soler',  'M', '1862-03-14', '1938',       'Teruel',   'Labrador',    'Tatarabuelo paterno (ejemplo).'),
  ( 2, 'Encarnación', 'Ferrer', 'Blasco', 'F', '1866',       '1944',       'Teruel',   'Costurera',   ''),
  -- GEN B — Bisabuelos (fallecidos)
  ( 3, 'Antonio',     'Gil',    'Ferrer', 'M', '1890-07-21', '1961',       'Zaragoza', 'Herrero',     ''),
  ( 4, 'Josefa',      'Marín',  'Cano',   'F', '1893',       '1970-04-30', 'Zaragoza', 'Modista',     ''),
  -- GEN C — Abuelos y tía abuela (fallecidos)
  ( 5, 'Miguel',      'Gil',    'Marín',  'M', '1918-02-08', '1994',       'Zaragoza', 'Maestro',     'Abuelo (ejemplo).'),
  ( 6, 'Pilar',       'Ortega', 'Ruiz',   'F', '1921',       '2005',       'Zaragoza', 'Enfermera',   ''),
  ( 7, 'Teresa',      'Gil',    'Marín',  'F', '1924-05-16', '2010',       'Huesca',   'Farmacéutica','Tía abuela (hermana del abuelo Miguel).'),
  ( 8, 'Ernesto',     'Ledesma','Pons',   'M', '1922',       '2001-09-10', 'Huesca',   'Veterinario', ''),
  -- GEN D — Padres, tíos y suegros (vivos)
  ( 9, 'Sergio',      'Gil',    'Ortega', 'M', '1946-04-18', '',           'Zaragoza', 'Arquitecto',  'Padre de la persona central; con dos parejas (ejemplo de segundas nupcias).'),
  (10, 'Isabel',      'Romero', 'Vidal',  'F', '1949-11-02', '',           'Zaragoza', 'Fisioterapeuta','Madre de la persona central.'),
  (11, 'Raquel',      'Duarte', 'Sanz',   'F', '1950',       '',           'Zaragoza', 'Diseñadora',  'Segunda pareja de Sergio (ejemplo).'),
  (12, 'Nuria',       'Gil',    'Ortega', 'F', '1952-08-09', '',           'Madrid',   'Periodista',  'Tía (hermana del padre).'),
  (13, 'David',       'Sáez',   'Moreno', 'M', '1948',       '',           'Madrid',   'Cocinero',    ''),
  (14, 'Alberto',     'Pérez',  'Lucas',  'M', '1947-01-25', '',           'Zaragoza', 'Electricista','Suegro de la persona central.'),
  (15, 'Carmen',      'Ibáñez', 'Roca',   'F', '1950',       '',           'Zaragoza', 'Bibliotecaria','Suegra de la persona central.'),
  -- GEN E — Persona central, hermanos, pareja, cuñados y primos (vivos)
  (16, 'Lucía',       'Gil',    'Romero', 'F', '1974-06-25', '',           'Zaragoza', 'Arquitecta',  'PERSONA CENTRAL del árbol de ejemplo.'),
  (17, 'Pablo',       'Gil',    'Romero', 'M', '1976-03-10', '',           'Zaragoza', 'Ingeniero',   'Hermano de la persona central.'),
  (18, 'Iker',        'Gil',    'Duarte', 'M', '1979',       '',           'Zaragoza', 'Músico',      'Medio hermano (comparte solo al padre).'),
  (19, 'Adrián',      'Pérez',  'Ibáñez', 'M', '1973-01-30', '',           'Zaragoza', 'Médico',      'Pareja de la persona central.'),
  (20, 'Sofía',       'Pérez',  'Ibáñez', 'F', '1975-12-14', '',           'Zaragoza', 'Abogada',     'Cuñada (hermana de la pareja).'),
  (21, 'Elena',       'Ríos',   'Cano',   'F', '1977',       '',           'Zaragoza', 'Profesora',   'Cuñada (pareja del hermano Pablo).'),
  (22, 'Hugo',        'Sáez',   'Gil',    'M', '1980-10-05', '',           'Madrid',   'Cocinero',    'Primo hermano (hijo de la tía Nuria).'),
  (23, 'Clara',       'Sáez',   'Gil',    'F', '1983-02-17', '',           'Madrid',   'Enfermera',   'Prima hermana.'),
  (24, 'Rubén',       'Pérez',  'Ibáñez', 'M', '1978',       '',           'Zaragoza', 'Aparejador',  'Cuñado (hermano de la pareja).'),
  -- GEN F — Hijos, sobrinos, yerno y nueras (vivos)
  (25, 'Mateo',       'Pérez',  'Gil',    'M', '2000-09-05', '',           'Zaragoza', 'Diseñador',   'Hijo de la persona central; con dos parejas (ejemplo).'),
  (26, 'Alba',        'Pérez',  'Gil',    'F', '2003-04-22', '',           'Zaragoza', 'Estudiante',  'Hija de la persona central.'),
  (27, 'Nerea',       'Vidal',  'Sanz',   'F', '2001-07-19', '',           'Zaragoza', 'Veterinaria', 'Nuera (pareja del hijo Mateo).'),
  (28, 'Laura',       'Gómez',  'Prat',   'F', '2000',       '',           'Zaragoza', 'Ilustradora', 'Segunda nuera (segunda pareja de Mateo).'),
  (29, 'Óscar',       'Ramos',  'Gil',    'M', '1999-12-01', '',           'Zaragoza', 'Enfermero',   'Yerno (pareja de la hija Alba).'),
  (30, 'Nora',        'Gil',    'Ríos',   'F', '2004-06-08', '',           'Zaragoza', 'Estudiante',  'Sobrina (hija del hermano Pablo).'),
  (31, 'Bruno',       'Gil',    'Ríos',   'M', '2006',       '',           'Zaragoza', 'Estudiante',  'Sobrino.'),
  (32, 'Iván',        'Costa',  'Ruiz',   'M', '2003',       '',           'Zaragoza', 'Fotógrafo',   ''),
  -- GEN G — Nieta y sobrino nieto (vivos, menores)
  (33, 'Leo',         'Costa',  'Gil',    'M', '2024-01-20', '',           'Zaragoza', '',            'Sobrino nieto de la persona central.'),
  (34, 'Vera',        'Pérez',  'Vidal',  'F', '2024',       '',           'Zaragoza', '',            'Nieta de la persona central.');


-- ── 1b) Fotos del demo ──────────────────────────────────────────────────────
-- Rostros de personas que NO EXISTEN, generados por IA (thispersondoesnotexist /
-- StyleGAN2); no representan a personas reales (ver THIRD-PARTY-NOTICES.md). Los
-- archivos viven versionados en db/demo-fotos/ y el cargador del demo
-- (db/cargar-demo.php) los copia a almacen/fotos/. Solo llevan foto las
-- generaciones recientes (una foto actual no encaja con un antepasado de 1862).
UPDATE `arb_personas` SET `avatar` = 'ed9aa3c00507eb502e075446a19eb40c.jpg' WHERE `id` =  9; -- Sergio
UPDATE `arb_personas` SET `avatar` = '0852d2a002b2eb24eada321bc19baec0.jpg' WHERE `id` = 10; -- Isabel
UPDATE `arb_personas` SET `avatar` = '0220dd015598668898d2bd5f75774fda.jpg' WHERE `id` = 12; -- Nuria
UPDATE `arb_personas` SET `avatar` = '1063a1eda2567874771e2b326dadef12.jpg' WHERE `id` = 16; -- Lucía
UPDATE `arb_personas` SET `avatar` = 'c2cd2da164d5f61dbdbd777d9459eadb.jpg' WHERE `id` = 17; -- Pablo
UPDATE `arb_personas` SET `avatar` = '3eb0fb3affc902a27f3f5dfb9d98df0c.jpg' WHERE `id` = 19; -- Adrián
UPDATE `arb_personas` SET `avatar` = 'e675987525b9eae4e500089e19c9b98e.jpg' WHERE `id` = 21; -- Elena
UPDATE `arb_personas` SET `avatar` = 'aec7f14c04559ec2b83c22c5c8ea28d5.jpg' WHERE `id` = 25; -- Mateo
UPDATE `arb_personas` SET `avatar` = '39f63994b7f9ea855990b4d2b13e2b50.jpg' WHERE `id` = 26; -- Alba
UPDATE `arb_personas` SET `avatar` = 'b6d2436e6e593b073a8d1b4e6a90c03b.jpg' WHERE `id` = 27; -- Nerea
UPDATE `arb_personas` SET `avatar` = 'a7c73d91775533d351e9c39077f1ea12.jpg' WHERE `id` = 29; -- Óscar
UPDATE `arb_personas` SET `avatar` = '79723d4714555352c99985775e7e601e.jpg' WHERE `id` = 31; -- Bruno


-- ── 2) Filiación (progenitor → hijo) ────────────────────────────────────────
-- Una fila por progenitor. Padre/madre se deduce del sexo del progenitor.
INSERT INTO `arb_filiacion` (`progenitor_id`, `hijo_id`) VALUES
  -- Antonio (3) es hijo de Ramón (1) y Encarnación (2)
  ( 1, 3), ( 2, 3),
  -- Miguel (5) y Teresa (7) son hijos de Antonio (3) y Josefa (4)
  ( 3, 5), ( 4, 5),
  ( 3, 7), ( 4, 7),
  -- Sergio (9) y Nuria (12) son hijos de Miguel (5) y Pilar (6)
  ( 5, 9), ( 6, 9),
  ( 5,12), ( 6,12),
  -- Lucía (16) y Pablo (17) son hijos de Sergio (9) e Isabel (10)
  ( 9,16), (10,16),
  ( 9,17), (10,17),
  -- Iker (18) es hijo de Sergio (9) y Raquel (11)  → medio hermano de Lucía
  ( 9,18), (11,18),
  -- Adrián (19), Sofía (20) y Rubén (24) son hijos de Alberto (14) y Carmen (15)
  (14,19), (15,19),
  (14,20), (15,20),
  (14,24), (15,24),
  -- Hugo (22) y Clara (23) son hijos de Nuria (12) y David (13)  → primos de Lucía
  (12,22), (13,22),
  (12,23), (13,23),
  -- Mateo (25) y Alba (26) son hijos de Lucía (16) y Adrián (19)
  (16,25), (19,25),
  (16,26), (19,26),
  -- Nora (30) y Bruno (31) son hijos de Pablo (17) y Elena (21)  → sobrinos de Lucía
  (17,30), (21,30),
  (17,31), (21,31),
  -- Leo (33) es hijo de Nora (30) e Iván (32)  → sobrino nieto de Lucía
  (30,33), (32,33),
  -- Vera (34) es hija de Mateo (25) y Nerea (27)  → nieta de Lucía
  (25,34), (27,34);


-- ── 3) Parejas (cónyuges, en orden canónico a < b) ──────────────────────────
INSERT INTO `arb_pareja` (`persona_a_id`, `persona_b_id`) VALUES
  ( 1,  2),   -- Ramón – Encarnación
  ( 3,  4),   -- Antonio – Josefa
  ( 5,  6),   -- Miguel – Pilar
  ( 7,  8),   -- Teresa – Ernesto
  ( 9, 10),   -- Sergio – Isabel
  ( 9, 11),   -- Sergio – Raquel   (segundas nupcias)
  (12, 13),   -- Nuria – David
  (14, 15),   -- Alberto – Carmen
  (16, 19),   -- Lucía – Adrián
  (17, 21),   -- Pablo – Elena
  (25, 27),   -- Mateo – Nerea
  (25, 28),   -- Mateo – Laura     (segundas nupcias)
  (26, 29),   -- Alba – Óscar
  (30, 32);   -- Nora – Iván


-- ── 4) Ajustes del árbol (clave/valor) ──────────────────────────────────────
-- main_id = persona central (Lucía, id 16): desde ella se ven hacia arriba
-- padres/abuelos/bisabuelos/tatarabuelos y hacia abajo hijos/nietos.
--
-- Desde el PASO 12, los ajustes incluyen también el CONTROL DE ACCESO (antes en
-- config.php): el interruptor `acceso_activo` y los HASHES de las dos claves. Así
-- el demo funciona con el login leyendo de la BD (como en producción).
--   · acceso_activo = '1'  → login obligatorio (pon '0' para árbol abierto)
--   · clave de EDICIÓN (admin)   = "editar1234"   (hash abajo)
--   · clave de LECTURA (solo ver)= "ver1234"      (hash abajo)
-- Los hashes son password_hash() bcrypt; NUNCA se guarda la clave en claro.
-- `instalado` y `version_esquema` = cerrojo/versión que normalmente pone el
-- instalador (PASO 12); en el demo se fijan para dejar el árbol listo para usar.
INSERT INTO `arb_ajustes` (`clave`, `valor`) VALUES
  ('titulo',             'Familia Gil'),
  ('subtitulo',          'Árbol de demostración · datos ficticios'),
  ('main_id',            '16'),
  ('acceso_activo',      '1'),
  ('clave_edicion_hash', '$2y$10$61DDbgBIbLbMKcI0M4Mb4ej160pIewosrLAmvo1c0r11Eyl1WlJ/6'),
  ('clave_lectura_hash', '$2y$10$m4uwkS7SxnHqZfi4n/mgYOGhXPk4kB5Duf3EI6XBK4PBVtwtThPSW'),
  ('version_esquema',    '1'),
  ('instalado',          '1');


-- ── 5) Árbol (preparación multi-árbol, PASO 12) ─────────────────────────────
-- Hoy hay UN solo árbol por instalación; esta tabla deja la arquitectura
-- preparada para el futuro (ver docs/MULTI-ARBOL.md) sin construir nada más.
-- El instalador crea esta fila id=1; en el demo la fijamos igual.
INSERT INTO `arb_arboles` (`id`, `nombre`) VALUES
  (1, 'Familia Gil');


-- ===========================================================================
--  Fin del demo. Resultado: 34 personas, 38 filas de filiación, 14 de pareja,
--  8 ajustes (incluye acceso/claves/instalado), 1 árbol. Todo ficticio.
-- ===========================================================================
