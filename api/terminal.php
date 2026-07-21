<?php
// api/terminal.php - Terminal command execution for KodeWeb Lite
require_once __DIR__ . '/base.php';

try {
    switch ($action) {
        case 'terminal_cmd':
            ob_start();
            $cmd = $_POST['cmd'] ?? '';
            $terminal_id = $_POST['terminal_id'] ?? 'default';
            
            if (!isset($_SESSION['terminal_cwd']) || !is_array($_SESSION['terminal_cwd'])) {
                $_SESSION['terminal_cwd'] = [];
            }
            $reset_cwd = isset($_POST['reset']) && $_POST['reset'] === 'true';
            if (!isset($_SESSION['terminal_cwd'][$terminal_id]) || $reset_cwd) {
                $_SESSION['terminal_cwd'][$terminal_id] = WORKSPACE_ROOT;
            }
            
            if ($cmd === '') {
                ob_end_clean();
                $autocomplete_list = [];
                if (is_dir($_SESSION['terminal_cwd'][$terminal_id])) {
                    $items = @scandir($_SESSION['terminal_cwd'][$terminal_id]);
                    if ($items !== false) {
                        foreach ($items as $item) {
                            if ($item === '.' || $item === '..') continue;
                            $isDir = @is_dir($_SESSION['terminal_cwd'][$terminal_id] . DIRECTORY_SEPARATOR . $item);
                            $autocomplete_list[] = $item . ($isDir ? '/' : '');
                        }
                    }
                }
                echo json_encode(['success' => true, 'output' => '', 'cwd' => $_SESSION['terminal_cwd'][$terminal_id], 'autocomplete_list' => $autocomplete_list]);
                break;
            }
            
            $current_cwd = $_SESSION['terminal_cwd'][$terminal_id];
            $delimiter = "---KODEWEB_PWD_DELIMITER---";
            
            // Build the execution command that outputs the new directory at the end
            $is_win = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            if ($is_win) {
                // Windows cmd.exe chaining
                $full_cmd = "cd /d " . escapeshellarg($current_cwd) . " & " . $cmd . " 2>&1 & echo " . $delimiter . " & cd";
            } else {
                // Unix sh chaining
                $full_cmd = "cd " . escapeshellarg($current_cwd) . " && eval " . escapeshellarg($cmd) . " 2>&1; echo " . escapeshellarg($delimiter) . "; pwd";
            }
            
            $output = @shell_exec($full_cmd);
            if ($output === null) {
                $output = '';
            }
            $clean_output = $output;
            $new_cwd = $current_cwd;
            
            if (strpos($output, $delimiter) !== false) {
                $parts = explode($delimiter, $output);
                $clean_output = rtrim($parts[0]);
                $new_cwd = trim($parts[1]);
                
                if (is_dir($new_cwd)) {
                    $_SESSION['terminal_cwd'][$terminal_id] = $new_cwd;
                } else {
                    $new_cwd = $current_cwd;
                }
            }
            
            // Convert potentially non-UTF-8 output from Windows CMD to UTF-8
            if (!mb_check_encoding($clean_output, 'UTF-8')) {
                $clean_output = mb_convert_encoding($clean_output, 'UTF-8', 'ISO-8859-1, CP850, auto');
            }
            
            // Generate autocomplete list
            $autocomplete_list = [];
            if (is_dir($_SESSION['terminal_cwd'][$terminal_id])) {
                $items = @scandir($_SESSION['terminal_cwd'][$terminal_id]);
                if ($items !== false) {
                    foreach ($items as $item) {
                        if ($item === '.' || $item === '..') continue;
                        $isDir = @is_dir($_SESSION['terminal_cwd'][$terminal_id] . DIRECTORY_SEPARATOR . $item);
                        $autocomplete_list[] = $item . ($isDir ? '/' : '');
                    }
                }
            }
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'output' => $clean_output,
                'cwd' => $_SESSION['terminal_cwd'][$terminal_id],
                'autocomplete_list' => $autocomplete_list
            ], JSON_INVALID_UTF8_SUBSTITUTE);
            break;

        default:
            throw new Exception("Ação desconhecida ou não suportada neste módulo: $action");
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
