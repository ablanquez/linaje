<?php
declare(strict_types=1);

/**
 * Relaciones — vínculos entre personas (aristas): filiación y pareja.
 * ------------------------------------------------------------------
 * Solo dos tipos de arista, como en el esquema:
 *   · filiación  (progenitor → hijo)   → arb_filiacion
 *   · pareja     (cónyuge ↔ cónyuge)   → arb_pareja (orden canónico a < b)
 *
 * Todo con sentencias preparadas. Los INSERT usan ON DUPLICATE KEY UPDATE para
 * ser IDEMPOTENTES (si el vínculo ya existe, no duplica ni falla) sin perder la
 * protección de claves foráneas (un id inexistente sí da error).
 */
require_once __DIR__ . '/Fechas.php';
require_once __DIR__ . '/Arbol.php';

final class Relaciones
{
    /** Comprueba que una persona existe y está activa (no en la papelera). */
    private static function exigirActiva(PDO $pdo, int $id, string $rol): void
    {
        if ($id <= 0) throw new InvalidArgumentException("Id de $rol inválido.");
        $st = $pdo->prepare('SELECT 1 FROM arb_personas WHERE id = :id AND borrado_en IS NULL');
        $st->execute(['id' => $id]);
        if (!$st->fetchColumn()) {
            throw new InvalidArgumentException("La persona ($rol) no existe o está en la papelera.");
        }
    }

    // ── Filiación ───────────────────────────────────────────────────────────
    public static function anadirFiliacion(PDO $pdo, int $progenitor, int $hijo): void
    {
        if ($progenitor === $hijo) {
            throw new InvalidArgumentException('Una persona no puede ser su propio progenitor.');
        }
        // CONC-02: cerrojo de árbol como PRIMERA sentencia → el «comprobar-luego-
        // insertar» de abajo pasa a ser atómico frente a otras escrituras a la vez.
        Arbol::bloquear($pdo);

        self::exigirActiva($pdo, $progenitor, 'progenitor');
        self::exigirActiva($pdo, $hijo, 'hijo');

        // IDEMPOTENCIA: si el vínculo ya existe, no hay nada que validar ni insertar
        // (re-añadir no es un error). Se comprueba TRAS el cerrojo, sobre el estado real.
        if (self::filiacionExiste($pdo, $progenitor, $hijo)) return;

        // INT-02: como mucho 2 progenitores, y no dos del mismo sexo (padre+madre).
        self::validarProgenitores($pdo, $progenitor, $hijo);
        // INT-01: la nueva arista no puede cerrar un ciclo de ascendencia (cadena de
        // cualquier longitud): nadie puede acabar siendo su propio antepasado.
        self::validarSinCiclo($pdo, $progenitor, $hijo);
        // INT-04: coherencia de fechas TRANSITIVA (cubre cadenas con años parciales).
        Fechas::validarNuevaFiliacion($pdo, $progenitor, $hijo);

        $st = $pdo->prepare(
            'INSERT INTO arb_filiacion (progenitor_id, hijo_id) VALUES (:p, :h)
             ON DUPLICATE KEY UPDATE progenitor_id = progenitor_id'   // no-op si ya existe
        );
        $st->execute(['p' => $progenitor, 'h' => $hijo]);
    }

    /** ¿Existe ya la arista progenitor→hijo? */
    private static function filiacionExiste(PDO $pdo, int $progenitor, int $hijo): bool
    {
        $st = $pdo->prepare('SELECT 1 FROM arb_filiacion WHERE progenitor_id = :p AND hijo_id = :h');
        $st->execute(['p' => $progenitor, 'h' => $hijo]);
        return (bool) $st->fetchColumn();
    }

    /**
     * INT-02 — Límite de progenitores y control de sexo.
     * Un hijo no puede tener MÁS DE 2 progenitores, ni dos del mismo sexo conocido
     * (el modelo es padre + madre). Si el sexo de alguno es desconocido (NULL) no se
     * puede afirmar el conflicto de sexo, así que solo aplica el límite de 2.
     */
    private static function validarProgenitores(PDO $pdo, int $progenitor, int $hijo): void
    {
        // Solo cuentan los progenitores ACTIVOS: una persona en la PAPELERA no
        // restringe el árbol activo (sus vínculos están "dormidos"). Sin este filtro,
        // un progenitor borrado seguía contando y disparaba un falso "dos progenitores"
        // (p.ej. al rehacer un añadir-familiar, o al añadir un padre nuevo tras mandar
        // el anterior a la papelera). Coherente con arbol.php y exigirActiva (activo-solo).
        $st = $pdo->prepare(
            'SELECT p.id, p.nombre, p.apellido1, p.sexo
               FROM arb_filiacion f JOIN arb_personas p ON p.id = f.progenitor_id
              WHERE f.hijo_id = :h AND p.borrado_en IS NULL'
        );
        $st->execute(['h' => $hijo]);
        $actuales = $st->fetchAll();   // el vínculo nuevo NO está entre ellos (idempotencia ya descartada)

        $etqHijo = self::etiquetaPersona($pdo, $hijo);
        if (count($actuales) >= 2) {
            throw new InvalidArgumentException(sprintf(
                '«%s» ya tiene 2 progenitores. Un hijo no puede tener más de dos.', $etqHijo
            ));
        }

        $sexoNuevo = self::sexoDe($pdo, $progenitor);
        if ($sexoNuevo === 'M' || $sexoNuevo === 'F') {
            foreach ($actuales as $a) {
                if ($a['sexo'] === $sexoNuevo) {
                    $rol = $sexoNuevo === 'M' ? 'padres (varones)' : 'madres';
                    throw new InvalidArgumentException(sprintf(
                        '«%s» ya tiene como progenitor a «%s». Un hijo no puede tener dos %s.',
                        $etqHijo, self::etiqueta($a), $rol
                    ));
                }
            }
        }
    }

    /**
     * INT-01 — Detección de ciclos de ascendencia (cadenas de cualquier longitud).
     * Añadir progenitor→hijo cierra un ciclo si el progenitor YA es descendiente del
     * hijo (hijo →…→ progenitor). Se recorre la descendencia del hijo con conjunto de
     * visitados (a prueba de ciclos previos) y se comprueba si el progenitor aparece.
     */
    private static function validarSinCiclo(PDO $pdo, int $progenitor, int $hijo): void
    {
        // Solo se sigue por hijos ACTIVOS: un vínculo que pase por una persona en la
        // papelera está dormido y no forma un ciclo visible en el árbol activo.
        $paso = $pdo->prepare(
            'SELECT f.hijo_id FROM arb_filiacion f JOIN arb_personas p ON p.id = f.hijo_id
              WHERE f.progenitor_id = :id AND p.borrado_en IS NULL'
        );
        $vistos = [$hijo => true];
        $cola = [$hijo];
        while ($cola) {
            $actual = array_pop($cola);
            $paso->execute(['id' => $actual]);
            foreach ($paso->fetchAll(PDO::FETCH_COLUMN) as $desc) {
                $desc = (int) $desc;
                if ($desc === $progenitor) {
                    throw new InvalidArgumentException(sprintf(
                        'Vínculo imposible: haría que «%s» fuese antepasado de sí mismo/a '
                        . '(se cerraría un ciclo de ascendencia).',
                        self::etiquetaPersona($pdo, $progenitor)
                    ));
                }
                if (isset($vistos[$desc])) continue;
                $vistos[$desc] = true;
                $cola[] = $desc;
            }
        }
    }

    /** Sexo ('M'|'F'|null) de una persona. */
    private static function sexoDe(PDO $pdo, int $id): ?string
    {
        $st = $pdo->prepare('SELECT sexo FROM arb_personas WHERE id = :id');
        $st->execute(['id' => $id]);
        $s = $st->fetchColumn();
        return ($s === 'M' || $s === 'F') ? $s : null;
    }

    private static function etiquetaPersona(PDO $pdo, int $id): string
    {
        $st = $pdo->prepare('SELECT id, nombre, apellido1 FROM arb_personas WHERE id = :id');
        $st->execute(['id' => $id]);
        $r = $st->fetch();
        return $r ? self::etiqueta($r) : ('#' . $id);
    }

    private static function etiqueta(array $p): string
    {
        $n = trim(((string) ($p['nombre'] ?? '')) . ' ' . ((string) ($p['apellido1'] ?? '')));
        return $n !== '' ? $n : ('#' . ($p['id'] ?? '?'));
    }

    /**
     * INT-02 (defensa ante EVASIÓN por edición): impide que al cambiar el SEXO de una
     * persona con hijos, alguno de esos hijos acabe con dos progenitores del mismo
     * sexo (p.ej. crear padre+madre legítimos y luego cambiar el padre a mujer). La
     * llama Personas::editar con el nuevo sexo. Con sexo desconocido no hay conflicto.
     */
    public static function validarSexoAlEditar(PDO $pdo, int $personaId, ?string $nuevoSexo): void
    {
        if ($nuevoSexo !== 'M' && $nuevoSexo !== 'F') return;   // desconocido → nada que comprobar
        // Hijos de esta persona y, por cada hijo, los OTROS progenitores. Solo cuentan
        // hijos y otros progenitores ACTIVOS (los de la papelera no restringen el árbol).
        $st = $pdo->prepare(
            'SELECT c.id AS hijo_id, c.nombre AS hijo_nombre, c.apellido1 AS hijo_ap,
                    o.id AS otro_id, o.nombre AS otro_nombre, o.apellido1 AS otro_ap, o.sexo AS otro_sexo
               FROM arb_filiacion f
               JOIN arb_personas c ON c.id = f.hijo_id
               JOIN arb_filiacion f2 ON f2.hijo_id = f.hijo_id AND f2.progenitor_id <> :yo
               JOIN arb_personas o ON o.id = f2.progenitor_id
              WHERE f.progenitor_id = :yo2 AND c.borrado_en IS NULL AND o.borrado_en IS NULL'
        );
        $st->execute(['yo' => $personaId, 'yo2' => $personaId]);
        foreach ($st->fetchAll() as $r) {
            if ($r['otro_sexo'] === $nuevoSexo) {
                $etqHijo = self::etiqueta(['nombre' => $r['hijo_nombre'], 'apellido1' => $r['hijo_ap'], 'id' => $r['hijo_id']]);
                $etqOtro = self::etiqueta(['nombre' => $r['otro_nombre'], 'apellido1' => $r['otro_ap'], 'id' => $r['otro_id']]);
                $rol = $nuevoSexo === 'M' ? 'padres (varones)' : 'madres';
                throw new InvalidArgumentException(sprintf(
                    'No se puede cambiar el sexo: «%s» ya tiene como progenitor a «%s», y un hijo '
                    . 'no puede tener dos %s.', $etqHijo, $etqOtro, $rol
                ));
            }
        }
    }

    public static function quitarFiliacion(PDO $pdo, int $progenitor, int $hijo): void
    {
        $st = $pdo->prepare('DELETE FROM arb_filiacion WHERE progenitor_id = :p AND hijo_id = :h');
        $st->execute(['p' => $progenitor, 'h' => $hijo]);
    }

    // ── Pareja ──────────────────────────────────────────────────────────────
    public static function anadirPareja(PDO $pdo, int $a, int $b): void
    {
        if ($a === $b) {
            throw new InvalidArgumentException('Una persona no puede emparejarse consigo misma.');
        }
        self::exigirActiva($pdo, $a, 'cónyuge');
        self::exigirActiva($pdo, $b, 'cónyuge');
        if ($a > $b) { [$a, $b] = [$b, $a]; }   // orden canónico a < b
        $st = $pdo->prepare(
            'INSERT INTO arb_pareja (persona_a_id, persona_b_id) VALUES (:a, :b)
             ON DUPLICATE KEY UPDATE persona_a_id = persona_a_id'   // no-op si ya existe
        );
        $st->execute(['a' => $a, 'b' => $b]);
    }

    public static function quitarPareja(PDO $pdo, int $a, int $b): void
    {
        if ($a > $b) { [$a, $b] = [$b, $a]; }   // mismo orden canónico para localizar la fila
        $st = $pdo->prepare('DELETE FROM arb_pareja WHERE persona_a_id = :a AND persona_b_id = :b');
        $st->execute(['a' => $a, 'b' => $b]);
    }
}
