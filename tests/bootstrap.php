<?php
error_reporting(-1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '1.7.8.7');
}
if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'ps_');
}
if (!defined('_PS_USE_SQL_SLAVE_')) {
    define('_PS_USE_SQL_SLAVE_', true);
}

$repoRoot = dirname(__DIR__);
require_once $repoRoot . '/tests/shim/PrestaShopStub.php';
PrestaShopStub::resetAll();

require_once $repoRoot . '/statscarrier.php';

$dbHost = getenv('STATSCARRIER_DB_HOST');
if ($dbHost === false || $dbHost === '') {
    $dbHost = '127.0.0.1';
}
$dbPort = getenv('STATSCARRIER_DB_PORT');
if ($dbPort === false || $dbPort === '') {
    $dbPort = '3306';
}
$dbName = getenv('STATSCARRIER_DB_NAME');
if ($dbName === false || $dbName === '') {
    $dbName = 'statscarrier_test';
}
$dbUser = getenv('STATSCARRIER_DB_USER');
if ($dbUser === false || $dbUser === '') {
    $dbUser = 'statscarrier';
}
$dbPass = getenv('STATSCARRIER_DB_PASSWORD');
if ($dbPass === false) {
    $dbPass = '';
}

$dsn = 'mysql:host=' . $dbHost . ';port=' . $dbPort . ';dbname=' . $dbName . ';charset=utf8';
$pdo = new PDO($dsn, $dbUser, $dbPass, array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
));
$pdo->exec("SET SESSION sql_mode=''");
PrestaShopStub::setPdo($pdo);
PrestaShopStub::setSharedPdo($pdo);

$schema = array(
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . "carrier` (
        `id_carrier` INT UNSIGNED NOT NULL,
        `id_reference` INT UNSIGNED NOT NULL,
        `name` VARCHAR(64) NOT NULL,
        PRIMARY KEY (`id_carrier`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . "orders` (
        `id_order` INT UNSIGNED NOT NULL,
        `id_carrier` INT UNSIGNED NOT NULL,
        `id_shop` INT UNSIGNED NOT NULL,
        `date_add` DATETIME NOT NULL,
        PRIMARY KEY (`id_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
    'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . "order_history` (
        `id_order_history` INT UNSIGNED NOT NULL,
        `id_order` INT UNSIGNED NOT NULL,
        `id_order_state` INT UNSIGNED NOT NULL,
        `date_add` DATETIME NOT NULL,
        PRIMARY KEY (`id_order_history`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
);
foreach ($schema as $sql) {
    $pdo->exec($sql);
}
