<?php
/**
 * PLANTILLA DE CONFIGURACIÓN  (config.example.php)
 * ------------------------------------------------
 * Este archivo SÍ se sube a GitHub: NO contiene secretos, solo el ejemplo.
 *
 * Para poner en marcha el proyecto:
 *   1. Copia este archivo a  config/config.php
 *   2. Rellena en config.php tus credenciales reales de la base de datos.
 *   3. config/config.php está en .gitignore y NUNCA debe subirse a GitHub.
 *
 * El archivo devuelve un array de configuración que lee src/bootstrap.php.
 */

return [

    // ── Conexión a la base de datos ─────────────────────────────────────────
    'db' => [
        'host'    => 'localhost',
        'puerto'  => 3306,
        'nombre'  => 'genealogia',                 // nombre de la base de datos
        'usuario' => 'usuario_de_la_bd',
        'clave'   => 'PON_AQUI_UNA_CONTRASENA_FUERTE',
        'charset' => 'utf8mb4',                    // no cambiar (soporte de acentos/emojis)
    ],

    // ── Modo depuración (opcional) ──────────────────────────────────────────
    // En PRODUCCIÓN déjalo en false (o borra esta línea): los errores NO se
    // muestran al visitante, solo se registran en almacen/logs/. Ponlo en true
    // SOLO en tu entorno local si necesitas ver los errores en pantalla.
    'debug' => false,

    // ── Claves de acceso ────────────────────────────────────────────────────
    // YA NO van aquí. Desde el PASO 12 el control de acceso (interruptor
    // login sí/no) y los HASHES de las dos claves (edición/lectura) viven en la
    // BASE DE DATOS (tabla arb_ajustes), para poder cambiarlos desde el panel y
    // que el auto-deploy no los pise. Los escribe el instalador (public/instalar/).
    // Este archivo solo guarda el "cómo conecto a la BD" (como wp-config.php).

];
