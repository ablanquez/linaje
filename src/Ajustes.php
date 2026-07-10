<?php
declare(strict_types=1);

/**
 * Ajustes — guarda ajustes del árbol (clave/valor) en arb_ajustes.
 * ---------------------------------------------------------------
 * Claves admitidas: titulo, subtitulo, main_id (persona central).
 * Upsert con sentencia preparada. Valida longitudes y que main_id sea una
 * persona existente y activa. La LECTURA de ajustes ya la hace api/arbol.php.
 */
final class Ajustes
{
    private const MAX = 200;   // longitud máx. de título/subtítulo

    // Claves de VISUALIZACIÓN / APARIENCIA (PASO 13, panel de administración).
    // Son ajustes "de pantalla": no tocan datos ni acceso, se aplican al dibujar.
    public const K_ORIENTACION = 'orientacion';    // 'vertical' | 'horizontal'
    public const K_PROF_ARRIBA = 'prof_arriba';    // generaciones de antepasados (1..100)
    public const K_PROF_ABAJO  = 'prof_abajo';     // generaciones de descendientes (1..100)
    public const K_TEMA        = 'tema_defecto';   // 'claro' | 'oscuro'

    /** Guarda el subconjunto de ajustes presente en $cambios. Lanza si algo es inválido. */
    public static function guardar(PDO $pdo, array $cambios): void
    {
        $upsert = $pdo->prepare(
            'INSERT INTO arb_ajustes (clave, valor) VALUES (:c, :v)
             ON DUPLICATE KEY UPDATE valor = :v2'
        );
        $guardarClave = static function (string $clave, string $valor) use ($upsert): void {
            $upsert->execute(['c' => $clave, 'v' => $valor, 'v2' => $valor]);
        };

        $algo = false;

        if (array_key_exists('titulo', $cambios)) {
            $guardarClave('titulo', self::texto($cambios['titulo'], 'título'));
            $algo = true;
        }
        if (array_key_exists('subtitulo', $cambios)) {
            $guardarClave('subtitulo', self::texto($cambios['subtitulo'], 'subtítulo'));
            $algo = true;
        }
        if (array_key_exists('main_id', $cambios)) {
            $id = (int) $cambios['main_id'];
            $st = $pdo->prepare('SELECT 1 FROM arb_personas WHERE id = :id AND borrado_en IS NULL');
            $st->execute(['id' => $id]);
            if (!$st->fetchColumn()) {
                throw new InvalidArgumentException('La persona central no existe o está en la papelera.');
            }
            $guardarClave('main_id', (string) $id);
            $algo = true;
        }

        // ── Ajustes de visualización / apariencia ────────────────────────────
        if (array_key_exists(self::K_ORIENTACION, $cambios)) {
            $guardarClave(self::K_ORIENTACION, self::enum($cambios[self::K_ORIENTACION], ['vertical', 'horizontal'], 'orientación'));
            $algo = true;
        }
        if (array_key_exists(self::K_PROF_ARRIBA, $cambios)) {
            $guardarClave(self::K_PROF_ARRIBA, (string) self::profundidad($cambios[self::K_PROF_ARRIBA]));
            $algo = true;
        }
        if (array_key_exists(self::K_PROF_ABAJO, $cambios)) {
            $guardarClave(self::K_PROF_ABAJO, (string) self::profundidad($cambios[self::K_PROF_ABAJO]));
            $algo = true;
        }
        if (array_key_exists(self::K_TEMA, $cambios)) {
            $guardarClave(self::K_TEMA, self::enum($cambios[self::K_TEMA], ['claro', 'oscuro'], 'tema'));
            $algo = true;
        }

        if (!$algo) {
            throw new InvalidArgumentException('No se indicó ningún ajuste a guardar.');
        }
    }

    /** Valida que $v sea uno de los valores permitidos (enumerado). */
    private static function enum($v, array $permitidos, string $campo): string
    {
        $v = is_scalar($v) ? trim((string) $v) : '';
        if (!in_array($v, $permitidos, true)) {
            throw new InvalidArgumentException("Valor de $campo no válido: solo se admite " . implode(' o ', $permitidos) . '.');
        }
        return $v;
    }

    /** Normaliza una profundidad de generaciones a un entero entre 1 y 100. */
    private static function profundidad($v): int
    {
        $n = (int) $v;
        if ($n < 1)   $n = 1;
        if ($n > 100) $n = 100;
        return $n;
    }

    /** Normaliza y valida un texto (título/subtítulo). */
    private static function texto($v, string $campo): string
    {
        if (!is_scalar($v)) $v = '';
        $v = trim((string) $v);
        if (mb_strlen($v) > self::MAX) {
            throw new InvalidArgumentException("El $campo es demasiado largo (máx. " . self::MAX . ").");
        }
        return $v;
    }
}
