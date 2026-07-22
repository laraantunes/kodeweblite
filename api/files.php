<?php
// api/files.php - File management operations for KodeWeb Lite
require_once __DIR__ . '/base.php';

try {
    switch ($action) {
        case 'clean_shared':
            $sharedDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'shared';
            $count = 0;
            if (is_dir($sharedDir)) {
                $files = glob($sharedDir . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                        $count++;
                    }
                }
            }
            echo json_encode(['success' => true, 'count' => $count]);
            break;

        case 'files_list':
            // List files in a directory on-demand
            $relativePath = $_GET['path'] ?? '';
            $absPath = get_absolute_path($relativePath);
            
            // Auto-cleanup shared folder (older than 7 days) on file list load (usually app boot)
            if ($relativePath === '') {
                $sharedDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'shared';
                if (is_dir($sharedDir)) {
                    $files = glob($sharedDir . '/*');
                    $oneWeekAgo = time() - (7 * 24 * 60 * 60);
                    foreach ($files as $file) {
                        if (is_file($file) && filemtime($file) < $oneWeekAgo) {
                            @unlink($file);
                        }
                    }
                }
            }
            
            if (!is_dir($absPath)) {
                throw new Exception("Diretório não encontrado: " . htmlspecialchars($relativePath));
            }
            
            $items = [];
            $files = scandir($absPath);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                
                $fullPath = $absPath . DIRECTORY_SEPARATOR . $file;
                $isDir = is_dir($fullPath);
                
                // Get path relative to WORKSPACE_ROOT
                $itemRelPath = ltrim(str_replace(WORKSPACE_ROOT, '', $fullPath), DIRECTORY_SEPARATOR);
                
                $items[] = [
                    'name' => $file,
                    'path' => $itemRelPath,
                    'is_dir' => $isDir,
                    'size' => $isDir ? 0 : filesize($fullPath),
                    'ext' => $isDir ? '' : pathinfo($file, PATHINFO_EXTENSION)
                ];
            }
            
            // Sort directories first, then files alphabetically
            usort($items, function($a, $b) {
                if ($a['is_dir'] && !$b['is_dir']) return -1;
                if (!$a['is_dir'] && $b['is_dir']) return 1;
                return strcasecmp($a['name'], $b['name']);
            });
            
            echo json_encode(['success' => true, 'files' => $items]);
            break;

        case 'file_read':
            $relativePath = $_POST['path'] ?? $_GET['path'] ?? '';
            
            // Ignore virtual tabs
            if ($relativePath === 'db_explorer' || $relativePath === 'git_explorer' || strpos($relativePath, 'ftp_explorer_') === 0 || strpos($relativePath, 'plugin_') === 0) {
                echo json_encode(['success' => true, 'content' => '']);
                break;
            }
            
            $absPath = get_absolute_path($relativePath);
            
            if (!file_exists($absPath) || is_dir($absPath)) {
                throw new Exception("Arquivo não encontrado: " . htmlspecialchars($relativePath));
            }
            
            $content = file_get_contents($absPath);
            echo json_encode(['success' => true, 'content' => $content]);
            break;

        case 'file_serve':
            $relativePath = $_GET['path'] ?? '';
            $absPath = get_absolute_path($relativePath);
            
            if (!file_exists($absPath) || is_dir($absPath)) {
                http_response_code(404);
                exit("Arquivo não encontrado.");
            }
            
            $mimeType = @mime_content_type($absPath);
            if (!$mimeType) {
                $mimeType = 'application/octet-stream';
            }
            
            $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
            $mimes = [
                'svg' => 'image/svg+xml',
                'css' => 'text/css',
                'js' => 'application/javascript',
                'json' => 'application/json',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'ico' => 'image/x-icon'
            ];
            if (isset($mimes[$ext])) {
                $mimeType = $mimes[$ext];
            }
            
            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . filesize($absPath));
            readfile($absPath);
            exit;

        case 'file_save':
            $relativePath = $_POST['path'] ?? '';
            $absPath = get_absolute_path($relativePath);
            
            // Ensure parent directory exists
            $parentDir = dirname($absPath);
            if (!is_dir($parentDir)) {
                mkdir($parentDir, 0755, true);
            }
            
            if (isset($_FILES['content']) && $_FILES['content']['error'] === UPLOAD_ERR_OK) {
                if (!move_uploaded_file($_FILES['content']['tmp_name'], $absPath)) {
                    throw new Exception("Falha ao mover arquivo enviado.");
                }
            } else {
                $content = $_POST['content'] ?? '';
                if (file_put_contents($absPath, $content) === false) {
                    throw new Exception("Falha ao salvar o arquivo.");
                }
            }
            
            echo json_encode(['success' => true]);
            break;

        case 'file_create':
            $parentPath = $_POST['parent_path'] ?? '';
            $name = $_POST['name'] ?? '';
            $type = $_POST['type'] ?? 'file'; // 'file' or 'dir'
            
            if (empty($name)) {
                throw new Exception("O nome não pode ser vazio.");
            }
            
            $absParent = get_absolute_path($parentPath);
            $absPath = $absParent . DIRECTORY_SEPARATOR . $name;
            
            if (file_exists($absPath)) {
                throw new Exception("Arquivo ou pasta já existe.");
            }
            
            if ($type === 'dir') {
                if (!mkdir($absPath, 0755, true)) {
                    throw new Exception("Falha ao criar diretório.");
                }
            } else {
                if (file_put_contents($absPath, '') === false) {
                    throw new Exception("Falha ao criar arquivo.");
                }
            }
            
            $relPath = ltrim(str_replace(WORKSPACE_ROOT, '', $absPath), DIRECTORY_SEPARATOR);
            echo json_encode(['success' => true, 'path' => $relPath]);
            break;

        case 'file_rename':
            $relativePath = $_POST['path'] ?? '';
            $newName = $_POST['new_name'] ?? '';
            
            if (empty($newName)) {
                throw new Exception("O novo nome não pode ser vazio.");
            }
            
            $absPath = get_absolute_path($relativePath);
            $absParent = dirname($absPath);
            $newAbsPath = $absParent . DIRECTORY_SEPARATOR . $newName;
            
            if (file_exists($newAbsPath)) {
                throw new Exception("Um arquivo com o novo nome já existe.");
            }
            
            if (!rename($absPath, $newAbsPath)) {
                throw new Exception("Falha ao renomear.");
            }
            
            $newRelPath = ltrim(str_replace(WORKSPACE_ROOT, '', $newAbsPath), DIRECTORY_SEPARATOR);
            echo json_encode(['success' => true, 'path' => $newRelPath]);
            break;

        case 'file_delete':
            $relativePath = $_POST['path'] ?? '';
            $absPath = get_absolute_path($relativePath);
            
            if (!file_exists($absPath)) {
                throw new Exception("Item não existe.");
            }
            
            if (is_dir($absPath)) {
                $deleteDir = function($dirPath) use (&$deleteDir) {
                    $files = array_diff(scandir($dirPath), ['.', '..']);
                    foreach ($files as $file) {
                        $p = $dirPath . DIRECTORY_SEPARATOR . $file;
                        if (is_dir($p)) {
                            $deleteDir($p);
                        } else {
                            @chmod($p, 0777);
                            @unlink($p);
                        }
                    }
                    @chmod($dirPath, 0777);
                    return @rmdir($dirPath);
                };
                if (!$deleteDir($absPath)) {
                    $err = error_get_last();
                    $errMsg = $err ? $err['message'] : 'Erro desconhecido';
                    throw new Exception("Falha ao deletar diretório: " . $errMsg);
                }
            } else {
                @chmod($absPath, 0777);
                if (!@unlink($absPath)) {
                    $err = error_get_last();
                    $errMsg = $err ? $err['message'] : 'Erro desconhecido';
                    throw new Exception("Falha ao deletar arquivo: " . $errMsg);
                }
            }
            
            echo json_encode(['success' => true]);
            break;

        case 'files_list_recursive':
            $dir_param = $_GET['dir'] ?? $_POST['dir'] ?? '';
            $base_dir = empty($dir_param) ? WORKSPACE_ROOT : get_absolute_path($dir_param);
            
            if (!is_dir($base_dir)) {
                if (file_exists($base_dir)) {
                    $itemRelPath = ltrim(str_replace(WORKSPACE_ROOT, '', $base_dir), DIRECTORY_SEPARATOR);
                    echo json_encode(['success' => true, 'files' => [['name' => basename($base_dir), 'path' => $itemRelPath]]]);
                } else {
                    throw new Exception("Diretório ou arquivo não encontrado.");
                }
                break;
            }
            
            $items = [];
            $exclude_dirs = ['node_modules', 'vendor', '.git', 'connections', '.gemini', 'data'];
            
            $scan = function($dir) use (&$scan, &$items, $exclude_dirs) {
                if (!is_dir($dir)) return;
                $files = scandir($dir);
                if ($files === false) return;
                
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') continue;
                    
                    $fullPath = $dir . DIRECTORY_SEPARATOR . $file;
                    $isDir = is_dir($fullPath);
                    
                    if ($isDir) {
                        if (in_array($file, $exclude_dirs)) continue;
                        $scan($fullPath);
                    } else {
                        $itemRelPath = ltrim(str_replace(WORKSPACE_ROOT, '', $fullPath), DIRECTORY_SEPARATOR);
                        $items[] = [
                            'name' => $file,
                            'path' => $itemRelPath
                        ];
                    }
                }
            };
            
            $scan($base_dir);
            echo json_encode([
                'success' => true,
                'files' => $items
            ]);
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
