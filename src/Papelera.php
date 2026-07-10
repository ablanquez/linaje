<?php
declare(strict_types=1);

/**
 * Papelera — listar, restaurar y eliminar definitivamente personas borradas.
 * -------------------------------------------------------------------------
 * El borrado del día a día es SOFT-DELETE (borrado_en). Aquí:
 *   · listar()            → las que están en la papelera.
 *   · restaurar()         → borrado_en = NULL (vuelve al árbol, con sus vínculos
 *                            "dormidos" y su foto, ya que nada de eso se borró).
 *   · eliminarDefinitivo()→ DELETE físico (el ON DELETE CASCADE limpia sus
 *                            aristas) + borra su archivo de foto.
 *   · vaciar()            → lo anterior para TODAS las de la papelera.
 *
 * SEGURIDAD de datos: todas las operaciones destructivas exigen
 * `borrado_en IS NOT NULL`, así que NUNCA tocan a una persona ACTIVA.
 * (Acceso: solo rol edición; lo aplica el endpoint api/papelera.php.)
 */
final class Papelera
{
    /** Personas actualmente en la papelera (las más recientes primero). */
    public static function listar(PDO $pdo): array
    {
        $sql = 'SELECT id, nombre, apellido1, apellido2, sexo, nacimiento, fallecimiento,
                       avatar, borrado_en
                FROM arb_personas
                WHERE borrado_en IS NOT NULL
                ORDER BY borrado_en DESC, id DESC';
        return $pdo->query($sql)->fetchAll();
    }

    /** Restaura una persona de la papelera (vuelve a estar activa). */
    public static function restaurar(PDO $pdo, int $id): void
    {
        if ($id <= 0) throw new InvalidArgumentException('Id de persona inválido.');
        $st = $pdo->prepare('UPDATE arb_personas SET borrado_en = NULL WHERE id = :id AND borrado_en IS NOT NULL');
        $st->execute(['id' => $id]);
        if ($st->rowCount() === 0) {
            throw new InvalidArgumentException('Esa persona no está en la papelera.');
        }
    }

    /** Borra DEFINITIVAMENTE una persona de la papelera (físico + su foto). */
    public static function eliminarDefinitivo(PDO $pdo, int $id): void
    {
        if ($id <= 0) throw new InvalidArgumentException('Id de persona inválido.');
        // Guardamos el avatar antes de borrar la fila, para limpiar el archivo.
        $sel = $pdo->prepare('SELECT avatar FROM arb_personas WHERE id = :id AND borrado_en IS NOT NULL');
        $sel->execute(['id' => $id]);
        $fila = $sel->fetch();
        if (!$fila) {
            throw new InvalidArgumentException('Esa persona no está en la papelera.');
        }
        // DELETE físico: el ON DELETE CASCADE se lleva sus aristas (filiación/pareja).
        $del = $pdo->prepare('DELETE FROM arb_personas WHERE id = :id AND borrado_en IS NOT NULL');
        $del->execute(['id' => $id]);
        // INT-06: el archivo se borra tras el commit (si algo revierte, no queda huérfano el borrado).
        Fotos::programarBorrado($fila['avatar'] ?? null);
    }

    /** Vacía la papelera: borra definitivamente TODAS (físico + sus fotos). Devuelve cuántas. */
    public static function vaciar(PDO $pdo): int
    {
        // Recoger los avatares antes de borrar, para limpiar sus archivos.
        $avatares = $pdo->query('SELECT avatar FROM arb_personas
                                 WHERE borrado_en IS NOT NULL AND avatar IS NOT NULL')
                        ->fetchAll(PDO::FETCH_COLUMN);
        $n = $pdo->exec('DELETE FROM arb_personas WHERE borrado_en IS NOT NULL');   // CASCADE
        // INT-06: los archivos se borran tras el commit (ejecutarEscritura purga la cola).
        foreach ($avatares as $av) {
            Fotos::programarBorrado((string) $av);
        }
        return (int) $n;
    }
}
