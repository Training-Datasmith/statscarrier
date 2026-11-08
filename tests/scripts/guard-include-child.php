<?php
error_reporting(-1);
ini_set('display_errors', '1');
include $argv[1];
echo "SENTINEL_AFTER\n";
