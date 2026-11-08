<?php
error_reporting(-1);
ini_set('display_errors', '1');
$repoRoot = dirname(dirname(__DIR__));
include $repoRoot . '/statscarrier.php';
echo "SENTINEL_AFTER\n";
