<?php
// auth.php - Session and Authentication Controller for KodeWeb Lite

require_once __DIR__ . '/session.php';

$is_api = defined('IS_API') && IS_API;
$auth_file = __DIR__ . '/data/auth.enc';

// Check if installation is complete
if (!file_exists($auth_file)) {
    if ($is_api) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Not installed']);
        exit;
    } else {
        header("Location: install.php");
        exit;
    }
}

// Check if user is logged in
if (empty($_SESSION['logged_in'])) {
    if ($is_api) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    } else {
        header("Location: login.php");
        exit;
    }
}
