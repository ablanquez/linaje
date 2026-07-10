<?php
declare(strict_types=1);
/**
 * cargar-demo.php — carga (o RECARGA desde cero) el árbol de DEMOSTRACIÓN.
 * ---------------------------------------------------------------------------
 * Deja la base de datos con el esquema + los datos ficticios del demo «Familia
 * Gil», y copia las FOTOS del demo (db/demo-fotos/) a almacen/fotos/ para que se
 * vean en el árbol. Es DESTRUCTIVO: borra las tablas arb_* y las recrea.
 *
 * Uso (línea de comandos, desde la raíz del proyecto):
 *     php db/cargar-demo.php
 *
 * Requiere que config/config.php exista (credenciales de la BD). Las fotos del
 * demo son rostros de personas que NO EXISTEN, generados por IA (ver
 * THIRD-PARTY-NOTICES.md); no representan a personas reales.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo por línea de comandos.'); }

$RAIZ = dirname(__DIR__);
$cfgPath = $RAIZ . '/config/config.php';
if (!is_file($cfgPath)) {
    fwrite(STDERR, "Falta config/config.php. Configura la conexión antes de cargar el demo.\n");
    exit(1);
}
$cfg = require $cfgPath;
$db = $cfg['db'];

$dsn = "mysql:host={$db['host']};port=" . ($db['puerto'] ?? 3306) . ";dbname={$db['nombre']};charset=" . ($db['charset'] ?? 'utf8mb4');
// MULTI_STATEMENTS: deja que el parser de MySQL ejecute cada fichero .sql
// entero (maneja bien los comentarios "--" y los ";" dentro de cadenas).
$opciones = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
];
if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) $opciones[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true;
$pdo = new PDO($dsn, $db['usuario'], $db['clave'], $opciones);

/** Ejecuta un fichero .sql completo (multi-sentencia). */
function ejecutarSql(PDO $pdo, string $ruta): void
{
    $sql = file_get_contents($ruta);
    if ($sql === false) throw new RuntimeException("No se pudo leer $ruta");
    $pdo->exec($sql);
}

echo "· Borrando tablas anteriores…\n";
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['arb_filiacion', 'arb_pareja', 'arb_usuarios', 'arb_ajustes', 'arb_arboles', 'arb_personas'] as $t) {
    $pdo->exec("DROP TABLE IF EXISTS `$t`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "· Creando el esquema…\n";
ejecutarSql($pdo, __DIR__ . '/esquema.sql');
echo "· Cargando los datos de demostración…\n";
ejecutarSql($pdo, __DIR__ . '/datos-demo.sql');

echo "· Copiando las fotos del demo a almacen/fotos/…\n";
$destino = $RAIZ . '/almacen/fotos';
if (!is_dir($destino)) mkdir($destino, 0775, true);
$copiadas = 0;
foreach (glob(__DIR__ . '/demo-fotos/*.jpg') ?: [] as $foto) {
    if (copy($foto, $destino . '/' . basename($foto))) $copiadas++;
}

$personas = (int) $pdo->query('SELECT COUNT(*) FROM arb_personas')->fetchColumn();
$conFoto  = (int) $pdo->query('SELECT COUNT(*) FROM arb_personas WHERE avatar IS NOT NULL')->fetchColumn();
echo "\n✔ Demo cargado: $personas personas ($conFoto con foto), $copiadas fotos copiadas.\n";
