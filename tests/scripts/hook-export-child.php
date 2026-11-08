<?php
error_reporting(-1);
ini_set('display_errors', '1');
$repoRoot = dirname(dirname(__DIR__));
require_once $repoRoot . '/tests/bootstrap.php';

if (isset($argv[1]) && $argv[1] !== '') {
    $decoded = json_decode($argv[1], true);
    if (is_array($decoded)) {
        foreach ($decoded as $key => $value) {
            Tools::setValue($key, $value);
        }
    }
}

$module = new statscarrier();
$module->hookDisplayAdminStatsModules(array());
echo "SENTINEL_AFTER\n";
