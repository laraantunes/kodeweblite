<?php
// config.php - Configuration for KodeWeb Lite
$app_version = "v1.2.3-lite";

$local = false;
$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
    $env = parse_ini_file($env_file);
    if (isset($env['LOCAL_ENV']) && $env['LOCAL_ENV'] == '1') {
        $local = true;
    }
}
