<?php
declare(strict_types=1);

/**
 * Fechas — validación de coherencia de fechas entre progenitores e hijos.
 * ----------------------------------------------------------------------
 * REGLA (estricta, por AÑO): un descendiente NO puede haber nacido el mismo año NI
 * antes que cualquiera de sus antepasados; tiene que nacer DESPUÉS. Se compara solo
 * el año (sirve tanto para "AAAA" como para "AAAA-MM-DD"). Si a alguno le falta el
 * año, no se puede comparar ESE par y se omite (no inventamos datos).
 *
 * TRANSITIVA (INT-04): la comprobación NO se limita a la arista aislada
 * progenitor→hijo. Recorre TODA la ascendencia y TODA la descendencia, así que una
 * cadena con años parciales —unos con año, otros sin— tampoco puede colar un
 * imposible. Ejemplo que antes se evadía: A(2000) → B(sin año) → C(1990). La arista
 * A→B no se puede comparar (B sin año) y B→C tampoco; pero C es descendiente de A y
 * 1990 < 2000 → imposible. Aquí se detecta comparando el antepasado de año MÁS
 * TARDÍO contra el descendiente de año MÁS TEMPRANO a través del nuevo vínculo.
 *
 * Es la autoridad en el SERVIDOR (el frontend valida además para avisar antes de
 * enviar, pero la comprobación de verdad vive aquí). Se aplica:
 *   · al AÑADIR una filiación (progenitor → hijo)      → Relaciones::anadirFiliacion
 *     (Fechas::validarNuevaFiliacion: antepasados del progenitor vs descendientes del hijo)
 *   · al EDITAR el nacimiento de una persona           → Personas::editar
 *     (Fechas::validarNacimiento: contra TODA su ascendencia Y descendencia)
 */
final class Fechas
{
    /** Año (int) de una fecha "AAAA" o "AAAA-MM-DD"; null si no hay año. */
    public static function anio(?string $fecha): ?int
    {
        if ($fecha === null) return null;
        return preg_match('/^(\d{4})/', trim($fecha), $m) ? (int) $m[1] : null;
    }

    /**
     * VAL-03 (autoridad única del calendario): ¿es una fecha ACEPTABLE del front?
     *   '' (vacío)  → sí (dato opcional)
     *   'AAAA'      → sí si el año es plausible (1 … año que viene)
     *   'AAAA-MM-DD'→ sí si el año es plausible Y es fecha REAL de calendario (checkdate)
     * Cualquier otra forma (2020-13-45, 0000, 3000, texto) → no. Se usa en el alta/
     * edición de personas (Personas) y al restaurar una copia (Backup::validarIntegridad).
     */
    public static function esFechaCalendario(?string $v): bool
    {
        $v = trim((string) ($v ?? ''));
        if ($v === '') return true;
        $max = (int) date('Y') + 1;
        if (preg_match('/^(\d{4})$/', $v, $m)) {
            $y = (int) $m[1];
            return $y >= 1 && $y <= $max;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) {
            $y = (int) $m[1];
            if ($y < 1 || $y > $max) return false;
            return checkdate((int) $m[2], (int) $m[3], $y);
        }
        return false;
    }

    /**
     * Valida la NUEVA arista progenitor→hijo de forma TRANSITIVA. El vínculo hace
     * que cada antepasado (o el propio progenitor) sea antepasado de cada
     * descendiente (o el propio hijo). Basta comparar el par más ajustado: el
     * antepasado de año conocido más TARDÍO contra el descendiente de año conocido
     * más TEMPRANO. Si empatan o se cruzan, la cadena es imposible.
     */
    public static function validarNuevaFiliacion(PDO $pdo, int $progenitorId, int $hijoId): void
    {
        // Lado de arriba: {progenitor} ∪ ascendencia(progenitor) → año MÁS TARDÍO.
        $arriba = self::extremoAnio(self::ascendencia($pdo, $progenitorId, true), 'max');
        // Lado de abajo: {hijo} ∪ descendencia(hijo) → año MÁS TEMPRANO.
        $abajo  = self::extremoAnio(self::descendencia($pdo, $hijoId, true), 'min');

        if ($arriba && $abajo && $arriba['anio'] >= $abajo['anio']) {
            throw new InvalidArgumentException(sprintf(
                'Fecha imposible: «%s» (%d) no puede ser antepasado/a de «%s» (%d). '
                . 'Un descendiente debe nacer DESPUÉS que sus antepasados, no el mismo año ni antes.',
                self::etiqueta($arriba['persona']), $arriba['anio'],
                self::etiqueta($abajo['persona']), $abajo['anio']
            ));
        }
    }

    /**
     * Valida el nacimiento de una persona (al editarlo) contra TODA su ASCENDENCIA
     * y TODA su DESCENDENCIA (transitivo). Lanza con mensaje claro si es imposible.
     */
    public static function validarNacimiento(PDO $pdo, int $id, string $nuevoNacimiento): void
    {
        $aX = self::anio($nuevoNacimiento);
        if ($aX === null) return;   // sin año no hay nada que comparar

        $yo = self::persona($pdo, $id);
        $nombreYo = $yo ? self::etiqueta($yo) : ('#' . $id);

        // Contra los ANTEPASADOS (yo debo nacer DESPUÉS que el más tardío de ellos).
        $arriba = self::extremoAnio(self::ascendencia($pdo, $id, false), 'max');
        if ($arriba && $aX <= $arriba['anio']) {
            throw new InvalidArgumentException(sprintf(
                'Fecha imposible: «%s» (%d) nacería el mismo año o antes que su antepasado/a '
                . '«%s» (%d). Un descendiente debe nacer DESPUÉS que sus antepasados.',
                $nombreYo, $aX, self::etiqueta($arriba['persona']), $arriba['anio']
            ));
        }

        // Contra los DESCENDIENTES (el más temprano de ellos debe nacer DESPUÉS que yo).
        $abajo = self::extremoAnio(self::descendencia($pdo, $id, false), 'min');
        if ($abajo && $abajo['anio'] <= $aX) {
            throw new InvalidArgumentException(sprintf(
                'Fecha imposible: su descendiente «%s» (%d) habría nacido el mismo año o antes '
                . 'que «%s» (%d). Un antepasado debe nacer ANTES que sus descendientes.',
                self::etiqueta($abajo['persona']), $abajo['anio'], $nombreYo, $aX
            ));
        }
    }

    // ── Recorridos del grafo (con conjunto de VISITADOS: a prueba de ciclos) ─────
    /**
     * Sube por la ascendencia (progenitores, recursivo) desde $id. Con $incluirSelf,
     * incluye a la propia persona. Devuelve filas [id, nombre, apellido1, nacimiento].
     * El conjunto de visitados evita recorrer en círculo si (defensivamente) hubiera
     * un ciclo, y no repetir personas con varios caminos (p.ej. primos).
     */
    private static function ascendencia(PDO $pdo, int $id, bool $incluirSelf): array
    {
        // Solo antepasados ACTIVOS: un vínculo que pase por una persona en la papelera
        // está dormido y no debe imponer coherencia de fechas sobre el árbol activo.
        return self::recorrer($pdo, $id, $incluirSelf,
            'SELECT f.progenitor_id FROM arb_filiacion f JOIN arb_personas p ON p.id = f.progenitor_id
              WHERE f.hijo_id = :id AND p.borrado_en IS NULL');
    }

    /** Baja por la descendencia (hijos, recursivo) desde $id. Igual que ascendencia. */
    private static function descendencia(PDO $pdo, int $id, bool $incluirSelf): array
    {
        return self::recorrer($pdo, $id, $incluirSelf,
            'SELECT f.hijo_id FROM arb_filiacion f JOIN arb_personas p ON p.id = f.hijo_id
              WHERE f.progenitor_id = :id AND p.borrado_en IS NULL');
    }

    /** Recorrido BFS genérico por una arista (la SQL da el siguiente id a visitar). */
    private static function recorrer(PDO $pdo, int $id, bool $incluirSelf, string $sqlVecinos): array
    {
        $paso = $pdo->prepare($sqlVecinos);
        $vistos = [$id => true];      // no volver sobre el origen
        $cola = [$id];
        $ids = [];                    // ids alcanzados (sin el origen)
        while ($cola) {
            $actual = array_pop($cola);
            $paso->execute(['id' => $actual]);
            foreach ($paso->fetchAll(PDO::FETCH_COLUMN) as $vec) {
                $vec = (int) $vec;
                if (isset($vistos[$vec])) continue;
                $vistos[$vec] = true;
                $ids[] = $vec;
                $cola[] = $vec;
            }
        }
        if ($incluirSelf) $ids[] = $id;
        if (!$ids) return [];
        // Carga los datos (nacimiento/etiqueta) de las personas alcanzadas de una vez.
        $marc = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare(
            "SELECT id, nombre, apellido1, nacimiento FROM arb_personas WHERE id IN ($marc)"
        );
        $st->execute($ids);
        return $st->fetchAll();
    }

    /**
     * De un conjunto de personas devuelve la de año EXTREMO ('max' = más tardío,
     * 'min' = más temprano) entre las que tienen año conocido, o null si ninguna.
     * Devuelve ['anio' => int, 'persona' => fila].
     */
    private static function extremoAnio(array $personas, string $modo): ?array
    {
        $mejor = null;
        foreach ($personas as $p) {
            $a = self::anio($p['nacimiento'] ?? null);
            if ($a === null) continue;
            if ($mejor === null
                || ($modo === 'max' && $a > $mejor['anio'])
                || ($modo === 'min' && $a < $mejor['anio'])) {
                $mejor = ['anio' => $a, 'persona' => $p];
            }
        }
        return $mejor;
    }

    // ── Interno ──────────────────────────────────────────────────────────────
    private static function persona(PDO $pdo, int $id): ?array
    {
        $st = $pdo->prepare('SELECT id, nombre, apellido1, nacimiento FROM arb_personas WHERE id = :id');
        $st->execute(['id' => $id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    private static function etiqueta(array $p): string
    {
        $n = trim(((string) ($p['nombre'] ?? '')) . ' ' . ((string) ($p['apellido1'] ?? '')));
        return $n !== '' ? $n : ('#' . $p['id']);
    }
}
