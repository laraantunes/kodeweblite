<?php
// session.php - Centralized session configuration for KodeWeb Lite

$session_path = __DIR__ . '/data/sessions';
if (!is_dir($session_path)) {
    @mkdir($session_path, 0755, true);
}
session_save_path($session_path);

$lifetime = 2592000; // 30 days
ini_set('session.gc_maxlifetime', $lifetime);
session_set_cookie_params($lifetime);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
