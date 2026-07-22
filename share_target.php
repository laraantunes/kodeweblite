<?php
// share_target.php - Handles Web Share Target API uploads directly
require_once __DIR__ . '/api/base.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sharedCount = 0;
    
    // Save to a "shared" folder in the current workspace
    $sharedRelPath = 'shared';
    $sharedDir = get_absolute_path($sharedRelPath);
    
    if (!is_dir($sharedDir)) {
        mkdir($sharedDir, 0755, true);
    }
    
    $fileNames = [];
    
    if (isset($_FILES['shared_file'])) {
        $files = $_FILES['shared_file'];
        if (is_array($files['name'])) {
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $name = basename($files['name'][$i]);
                    if (empty($name)) $name = 'shared_file_' . time() . '_' . $i;
                    move_uploaded_file($files['tmp_name'][$i], $sharedDir . DIRECTORY_SEPARATOR . $name);
                    $fileNames[] = $name;
                    $sharedCount++;
                }
            }
        } else {
            if ($files['error'] === UPLOAD_ERR_OK) {
                $name = basename($files['name']);
                if (empty($name)) $name = 'shared_file_' . time();
                move_uploaded_file($files['tmp_name'], $sharedDir . DIRECTORY_SEPARATOR . $name);
                $fileNames[] = $name;
                $sharedCount++;
            }
        }
    }
    
    $filesQuery = urlencode(implode(',', $fileNames));
    header("Location: index.php?server_shared_files=" . $sharedCount . "&shared_names=" . $filesQuery);
    exit;
}

header("Location: index.php?share_error=invalid_method");
exit;
