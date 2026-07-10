<?php
declare(strict_types=1);

/**
 * Arbol — costura para la futura arquitectura multi-árbol (PASO 12, Tier 0).
 * -------------------------------------------------------------------------
 * HOY hay UN solo árbol por instalación. Esta clase NO construye la gestión
 * multi-árbol: es solo el ÚNICO punto por el que pasa "¿en qué árbol estoy?".
 * Igual que Auth::controlActivo() es el único punto del interruptor de acceso.
 *
 * Devuelve siempre 1 (el árbol que crea el instalador en arb_arboles). El día
 * que se quiera soporte multi-árbol de verdad, se cambia AQUÍ (leer el árbol de
 * la sesión / subdominio / etc.) y se aplican los ALTER + filtros descritos en
 * docs/MULTI-ARBOL.md, sin tener que rehacer lo ya construido.
 *
 * "Preparar, no construir": el mínimo que evita rehacer. Nada más.
 */
final class Arbol
{
    /** Id del árbol actual. Hoy, siempre 1. (Futuro: resolver por sesión/dominio.) */
    public static function actualId(): int
    {
        return 1;
    }

    /**
     * CERROJO DE ÁRBOL (respaldo de BD para las reglas de integridad — CONC-02).
     * -------------------------------------------------------------------------
     * Toma un bloqueo exclusivo (FOR UPDATE) sobre la fila del árbol actual en
     * arb_arboles. Sirve de MUTEX para las escrituras ESTRUCTURALES (añadir una
     * filiación, cambiar el sexo/fecha de una persona con hijos): serializa a los
     * escritores entre sí, de modo que el patrón «comprobar-luego-insertar» pase a
     * ser ATÓMICO. Sin esto, dos peticiones simultáneas pueden colarse cada una tras
     * su comprobación y violar juntas una regla (TOCTOU): p.ej. añadir cada una el
     * "2.º" progenitor y dejar 3, o cerrar un ciclo entre las dos.
     *
     * REQUISITO DE USO: debe ser la PRIMERA sentencia de la transacción. En InnoDB
     * (REPEATABLE READ) la «vista de lectura» de las lecturas normales se fija en la
     * primera lectura consistente; una lectura BLOQUEANTE como esta no la fija. Así,
     * cuando una 2.ª petición se desbloquea (porque la 1.ª ya hizo commit y soltó el
     * cerrojo), su primera lectura normal posterior forma la vista DESPUÉS del commit
     * ajeno y VE sus cambios. Resultado: solo una de las dos prospera.
     *
     * Las LECTURAS del árbol (arbol.php) no piden este cerrojo, así que ver el árbol
     * nunca se bloquea; solo se serializan entre sí las escrituras estructurales.
     *
     * Si (caso anómalo) no existiera la fila del árbol, no bloquea nada y se degrada
     * al comportamiento anterior; el instalador y el demo siempre la crean (id = 1).
     */
    public static function bloquear(PDO $pdo): void
    {
        $st = $pdo->prepare('SELECT id FROM arb_arboles WHERE id = :id FOR UPDATE');
        $st->execute(['id' => self::actualId()]);
        $st->fetchColumn();
    }
}
