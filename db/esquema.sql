-- ===========================================================================
--  ESQUEMA DE LA BASE DE DATOS — Árbol genealógico
-- ===========================================================================
--  Crea la ESTRUCTURA (5 tablas). No inserta datos reales.
--
--  Compatibilidad: escrito para MariaDB 10.5 (la de Hostinger) y probado
--  también en MySQL 8.4 (Laragon local). Se evitan cosas exclusivas de
--  MySQL 8 (p.ej. la colación utf8mb4_0900_*, que MariaDB no tiene): usamos
--  utf8mb4_unicode_ci, que existe en ambos.
--
--  Motor InnoDB en todas las tablas: nos da CLAVES FORÁNEAS (integridad de
--  los vínculos) y TRANSACCIONES (el guardado por operación las necesita).
--
--  IDEA CLAVE — relaciones como "aristas":
--    En el navegador, family-chart maneja listas bidireccionales
--    (parents / spouses / children). Aquí NO duplicamos eso: guardamos cada
--    vínculo UNA sola vez, y solo de dos tipos:
--       · filiación  (progenitor → hijo)        → tabla arb_filiacion
--       · pareja     (cónyuge ↔ cónyuge)        → tabla arb_pareja
--    Todo lo demás (abuelos, nietos, hermanos, primos…) se DEDUCE de esas dos.
--    Al leer, el backend reconstruye el JSON que family-chart consume.
--
--  Prefijo de tablas: arb_  (más adelante el instalador lo hará configurable;
--  por ahora va fijo). Cómo ejecutarlo: selecciona la base de datos
--  "genealogia" en HeidiSQL y lanza este script entero (guía en el chat).
--
--  Convención de fechas: se guardan como VARCHAR conservando EXACTAMENTE el
--  formato del formulario ("AAAA" solo año, o "AAAA-MM-DD" fecha exacta). No
--  usamos el tipo DATE para no perder el matiz "solo año".
-- ===========================================================================

-- Interpretar el contenido como UTF-8 al ejecutar el script (cualquier cliente).
SET NAMES utf8mb4;


-- ---------------------------------------------------------------------------
--  1) arb_personas — una fila por persona del árbol
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `arb_personas` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT
                   COMMENT 'Identificador único; lo asigna la BD al crear la persona',

  `nombre`         VARCHAR(100)  NOT NULL DEFAULT ''
                   COMMENT 'Nombre de pila',
  `apellido1`      VARCHAR(100)  NOT NULL DEFAULT ''
                   COMMENT 'Primer apellido',
  `apellido2`      VARCHAR(100)  NOT NULL DEFAULT ''
                   COMMENT 'Segundo apellido',

  -- NULL = sexo desconocido (p.ej. tarjeta anónima que la librería deja al
  -- borrar un nexo). Con valor, define el aro azul/rosa y si es padre o madre.
  `sexo`           ENUM('M','F') NULL DEFAULT NULL
                   COMMENT 'M = hombre, F = mujer, NULL = desconocido',

  -- Fechas tal cual las escribe el front: "AAAA" o "AAAA-MM-DD" (ver cabecera).
  `nacimiento`     VARCHAR(10)   NOT NULL DEFAULT ''
                   COMMENT 'Nacimiento: "AAAA" o "AAAA-MM-DD" (cadena, sin pérdida)',
  `fallecimiento`  VARCHAR(10)   NOT NULL DEFAULT ''
                   COMMENT 'Fallecimiento: "AAAA" o "AAAA-MM-DD"; vacío = vive / se desconoce',

  `lugar`          VARCHAR(150)  NOT NULL DEFAULT ''
                   COMMENT 'Lugar (nacimiento/residencia); texto libre',
  `ocupacion`      VARCHAR(150)  NOT NULL DEFAULT ''
                   COMMENT 'Ocupación / profesión',
  `notas`          TEXT          NULL
                   COMMENT 'Notas libres (biografía, anécdotas…)',

  -- Guardamos el NOMBRE DE ARCHIVO de la foto (p.ej. "17.jpg"), NO la imagen.
  -- El archivo vivirá en almacen/fotos/ y se servirá protegido (PASO 7).
  `avatar`         VARCHAR(255)  NULL DEFAULT NULL
                   COMMENT 'Nombre del archivo de foto en almacen/fotos/ (NULL = sin foto)',

  `creado_en`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                   COMMENT 'Fecha de alta del registro',
  `actualizado_en` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                   COMMENT 'Se actualiza sola en cada modificación',

  -- PAPELERA (soft-delete): si tiene fecha, la persona está "en la papelera";
  -- si es NULL, está activa. El borrado del día a día solo pone esta fecha,
  -- NO elimina la fila (así se puede recuperar) y NO dispara ninguna cascada.
  `borrado_en`     TIMESTAMP     NULL DEFAULT NULL
                   COMMENT 'NULL = activa; con fecha = en la papelera (soft-delete)',

  PRIMARY KEY (`id`),
  KEY `idx_personas_borrado` (`borrado_en`)   -- para listar rápido activas vs papelera
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Personas del árbol (los nodos)';


-- ---------------------------------------------------------------------------
--  2) arb_filiacion — vínculo progenitor → hijo (una arista por relación)
-- ---------------------------------------------------------------------------
--  Un hijo con padre Y madre = DOS filas (una por progenitor). Que sea padre
--  o madre se deduce del `sexo` del progenitor en arb_personas.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `arb_filiacion` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `progenitor_id` INT UNSIGNED NOT NULL COMMENT 'Padre o madre (→ arb_personas.id)',
  `hijo_id`       INT UNSIGNED NOT NULL COMMENT 'Hijo/a (→ arb_personas.id)',

  PRIMARY KEY (`id`),

  -- No permitir el mismo vínculo dos veces.
  UNIQUE KEY `uq_filiacion` (`progenitor_id`, `hijo_id`),
  -- Para buscar rápido "¿quiénes son los padres de este hijo?".
  KEY `idx_filiacion_hijo` (`hijo_id`),

  -- NOTA: "una persona no puede ser su propia progenitora" (progenitor_id <> hijo_id)
  -- se garantiza en el CÓDIGO PHP al insertar. No se pone como CHECK aquí porque
  -- MySQL/MariaDB PROHÍBEN un CHECK sobre columnas que llevan una clave foránea con
  -- acción referencial (ON DELETE CASCADE), y el CASCADE es prioritario.

  -- Integridad: ambos extremos deben existir en arb_personas. ON DELETE CASCADE
  -- solo actúa en el BORRADO FÍSICO definitivo de una persona (no en la papelera,
  -- que es soft-delete): al eliminar de verdad una persona, sus aristas se van con ella.
  CONSTRAINT `fk_filiacion_progenitor` FOREIGN KEY (`progenitor_id`)
      REFERENCES `arb_personas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_filiacion_hijo` FOREIGN KEY (`hijo_id`)
      REFERENCES `arb_personas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Filiación: quién es progenitor de quién';


-- ---------------------------------------------------------------------------
--  3) arb_pareja — vínculo de cónyuges (una arista por pareja)
-- ---------------------------------------------------------------------------
--  Orden CANÓNICO: se guarda siempre con persona_a_id < persona_b_id, así una
--  misma pareja no puede quedar duplicada como (1,2) y (2,1). Separar una
--  pareja = borrar su fila (los hijos siguen colgando por arb_filiacion).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `arb_pareja` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `persona_a_id` INT UNSIGNED NOT NULL COMMENT 'Cónyuge con id menor (→ arb_personas.id)',
  `persona_b_id` INT UNSIGNED NOT NULL COMMENT 'Cónyuge con id mayor (→ arb_personas.id)',

  PRIMARY KEY (`id`),

  UNIQUE KEY `uq_pareja` (`persona_a_id`, `persona_b_id`),
  KEY `idx_pareja_b` (`persona_b_id`),   -- para buscar por el segundo cónyuge

  -- NOTA: el orden canónico a < b (que impide duplicar (1,2)/(2,1) y evita a = b)
  -- lo garantiza el CÓDIGO PHP al insertar, ordenando los dos ids antes de guardar.
  -- No se pone como CHECK por el mismo motivo que en arb_filiacion: MySQL/MariaDB no
  -- permiten un CHECK sobre columnas con clave foránea de acción referencial (CASCADE).

  CONSTRAINT `fk_pareja_a` FOREIGN KEY (`persona_a_id`)
      REFERENCES `arb_personas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pareja_b` FOREIGN KEY (`persona_b_id`)
      REFERENCES `arb_personas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Parejas (cónyuges), en orden canónico a < b';


-- ---------------------------------------------------------------------------
--  4) arb_usuarios — identidades de acceso (PREPARADA PARA EL FUTURO)
-- ---------------------------------------------------------------------------
--  Con el login actual ("Forma 1": nombre + fecha + clave global) esta tabla
--  NO es imprescindible, pero se deja creada para poder asignar rol/credencial
--  POR PERSONA más adelante sin rehacer el esquema.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `arb_usuarios` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `persona_id`    INT UNSIGNED NOT NULL
                  COMMENT 'A qué persona del árbol corresponde (→ arb_personas.id)',
  `rol`           ENUM('lectura','edicion') NOT NULL DEFAULT 'lectura'
                  COMMENT 'Nivel de acceso: solo ver, o editar (admin)',
  `password_hash` VARCHAR(255) NULL DEFAULT NULL
                  COMMENT 'Hash (password_hash) si esa persona tuviera clave propia; nunca en claro',
  `activo`        TINYINT(1)   NOT NULL DEFAULT 1
                  COMMENT '1 = puede entrar, 0 = deshabilitado',
  `creado_en`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  -- Una identidad de acceso por persona.
  UNIQUE KEY `uq_usuarios_persona` (`persona_id`),

  CONSTRAINT `fk_usuarios_persona` FOREIGN KEY (`persona_id`)
      REFERENCES `arb_personas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Usuarios/roles por persona (reservado para el futuro)';


-- ---------------------------------------------------------------------------
--  5) arb_ajustes — configuración del árbol en formato clave/valor
-- ---------------------------------------------------------------------------
--  Guarda ajustes sueltos de la instalación:
--    titulo, subtitulo   → tarjeta de título del árbol
--    main_id             → id de la persona central (foco inicial)
--    version_esquema     → versión de este esquema (para migraciones futuras)
--    instalado           → cerrojo del asistente de instalación (PASO 12)
--  Estas filas las rellenará el instalador; aquí solo se crea la tabla vacía.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `arb_ajustes` (
  `clave` VARCHAR(50) NOT NULL COMMENT 'Nombre del ajuste (p.ej. "titulo")',
  `valor` TEXT        NULL     COMMENT 'Valor del ajuste',
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Ajustes del árbol (clave/valor)';


-- ---------------------------------------------------------------------------
--  6) arb_arboles — árboles de la instalación (PREPARACIÓN MULTI-ÁRBOL, PASO 12)
-- ---------------------------------------------------------------------------
--  HOY hay UN solo árbol por instalación (siempre id = 1, que crea el instalador).
--  Esta tabla NO se usa todavía para filtrar nada: existe solo para dejar la
--  arquitectura PREPARADA (sin construir la gestión multi-árbol) y darle un hogar
--  estable a los metadatos por árbol. La "receta" para pasar a multi-árbol de
--  verdad (añadir arbol_id a las demás tablas y filtrar por él) está escrita en
--  docs/MULTI-ARBOL.md. El código pasa por Arbol::actualId(), que hoy devuelve 1.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `arb_arboles` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`    VARCHAR(150) NOT NULL DEFAULT '' COMMENT 'Nombre del árbol (informativo)',
  `creado_en` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Árboles de la instalación (hoy siempre 1; reservado para multi-árbol)';


-- ===========================================================================
--  Fin del esquema. Resultado esperado: 6 tablas vacías
--    arb_personas · arb_filiacion · arb_pareja · arb_usuarios · arb_ajustes
--    · arb_arboles
--  No se ha insertado ningún dato.
-- ===========================================================================
