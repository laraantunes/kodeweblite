<?php
// api/ftp.php - FTP connection and file operations for KodeWeb Lite
require_once __DIR__ . '/base.php';

try {
    switch ($action) {
        case 'ftp_connections_list':
            $connections_dir = $rootDir . '/connections';
            if (!is_dir($connections_dir)) {
                mkdir($connections_dir, 0700, true);
            }
            
            $connections = [];
            $files = glob($connections_dir . '/*.enc');
            foreach ($files as $file) {
                $encryptedData = file_get_contents($file);
                $decryptedData = KodeWebEncryption::decrypt($encryptedData);
                if ($decryptedData) {
                    $data = json_decode($decryptedData, true);
                    if ($data && isset($data['type']) && $data['type'] === 'ftp') {
                        unset($data['password']);
                        $connections[] = $data;
                    }
                }
            }
            
            echo json_encode(['success' => true, 'connections' => $connections]);
            break;

        case 'ftp_connection_save':
            $id = $_POST['id'] ?? '';
            $name = $_POST['name'] ?? '';
            $host = $_POST['host'] ?? '';
            $port = $_POST['port'] ?? '21';
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if (empty($name) || empty($host)) {
                throw new Exception("Nome e Host são obrigatórios.");
            }
            
            if (empty($id)) {
                $id = uniqid('ftp_conn_');
            } else {
                if (empty($password)) {
                    $existingFile = $rootDir . '/connections/' . $id . '.enc';
                    if (file_exists($existingFile)) {
                        $encData = file_get_contents($existingFile);
                        $decData = KodeWebEncryption::decrypt($encData);
                        if ($decData) {
                            $oldConn = json_decode($decData, true);
                            $password = $oldConn['password'] ?? '';
                        }
                    }
                }
            }
            
            $connectionData = [
                'id' => $id,
                'type' => 'ftp',
                'name' => $name,
                'host' => $host,
                'port' => $port,
                'username' => $username,
                'password' => $password
            ];
            
            $connections_dir = $rootDir . '/connections';
            if (!is_dir($connections_dir)) {
                mkdir($connections_dir, 0700, true);
            }
            
            $jsonString = json_encode($connectionData);
            $encrypted = KodeWebEncryption::encrypt($jsonString);
            
            if (file_put_contents($connections_dir . '/' . $id . '.enc', $encrypted) === false) {
                throw new Exception("Erro ao salvar arquivo de conexão FTP.");
            }
            
            echo json_encode(['success' => true, 'id' => $id]);
            break;

        case 'ftp_connection_delete':
            $id = $_POST['id'] ?? '';
            if (empty($id)) {
                throw new Exception("ID de conexão inválido.");
            }
            $file = $rootDir . '/connections/' . $id . '.enc';
            if (file_exists($file)) {
                unlink($file);
            }
            echo json_encode(['success' => true]);
            break;

        case 'ftp_test':
            $host = $_POST['host'] ?? '';
            $port = empty($_POST['port']) ? 21 : (int)$_POST['port'];
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if (!function_exists('ftp_connect')) {
                throw new Exception("Extensão FTP não está habilitada.");
            }
            
            $ftp = @ftp_connect($host, $port, 5);
            if (!$ftp) {
                throw new Exception("Falha de conexão com $host:$port");
            }
            if (!@ftp_login($ftp, $username, $password)) {
                ftp_close($ftp);
                throw new Exception("Falha de autenticação.");
            }
            
            ftp_close($ftp);
            echo json_encode(['success' => true]);
            break;

        case 'ftp_list':
            $connection_id = $_GET['connection_id'] ?? $_POST['connection_id'] ?? '';
            $path = $_GET['path'] ?? $_POST['path'] ?? '/';
            
            $connInfo = get_ftp_connection($connection_id);
            $ftp = connect_ftp($connInfo);
            
            $items = [];
            
            $mlsd = @ftp_mlsd($ftp, $path);
            if (is_array($mlsd) && count($mlsd) >= 0) {
                foreach ($mlsd as $entry) {
                    $name = $entry['name'];
                    if ($name === '.' || $name === '..') continue;
                    
                    $isDir = isset($entry['type']) && $entry['type'] === 'cdir' ? true : (isset($entry['type']) && $entry['type'] === 'dir');
                    if (isset($entry['type']) && ($entry['type'] === 'cdir' || $entry['type'] === 'pdir')) continue;
                    
                    $fullPath = rtrim($path, '/') . '/' . $name;
                    $items[] = [
                        'name' => $name,
                        'path' => $fullPath,
                        'is_dir' => $isDir,
                        'size' => isset($entry['size']) ? (int)$entry['size'] : 0
                    ];
                }
            } else {
                $rawlist = @ftp_rawlist($ftp, $path);
                if (is_array($rawlist)) {
                    foreach ($rawlist as $line) {
                        if (preg_match('/^([d\-l])[rwx\-]{9}\s+\d+\s+\S+\s+\S+\s+(\d+)\s+\S+\s+\d+\s+[\d:]+\s+(.+)$/', $line, $matches)) {
                            $type = $matches[1];
                            $size = (int)$matches[2];
                            $name = $matches[3];
                            
                            if ($name === '.' || $name === '..') continue;
                            $items[] = [
                                'name' => $name,
                                'path' => rtrim($path, '/') . '/' . $name,
                                'is_dir' => ($type === 'd' || $type === 'l'),
                                'size' => $size
                            ];
                        } 
                        elseif (preg_match('/^\d{2}\-\d{2}\-\d{2}\s+\d{2}:\d{2}[AP]M\s+(<DIR>|[\d]+)\s+(.+)$/', $line, $matches)) {
                            $isDir = trim($matches[1]) === '<DIR>';
                            $size = $isDir ? 0 : (int)$matches[1];
                            $name = trim($matches[2]);
                            
                            if ($name === '.' || $name === '..') continue;
                            $items[] = [
                                'name' => $name,
                                'path' => rtrim($path, '/') . '/' . $name,
                                'is_dir' => $isDir,
                                'size' => $size
                            ];
                        }
                    }
                }
            }
            
            usort($items, function($a, $b) {
                if ($a['is_dir'] && !$b['is_dir']) return -1;
                if (!$a['is_dir'] && $b['is_dir']) return 1;
                return strcasecmp($a['name'], $b['name']);
            });
            
            ftp_close($ftp);
            echo json_encode(['success' => true, 'items' => $items]);
            break;

        case 'ftp_file_read':
            $connection_id = $_POST['connection_id'] ?? '';
            $path = $_POST['path'] ?? '';
            
            if (empty($path)) throw new Exception("Caminho não especificado.");
            
            $connInfo = get_ftp_connection($connection_id);
            $ftp = connect_ftp($connInfo);
            
            $tempFile = tempnam(sys_get_temp_dir(), 'ftp_');
            if (@ftp_get($ftp, $tempFile, $path, FTP_BINARY)) {
                $content = file_get_contents($tempFile);
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $isImage = in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'ico']);
                if ($isImage) {
                    $mime = mime_content_type($tempFile) ?: 'image/jpeg';
                    $content = 'data:' . $mime . ';base64,' . base64_encode($content);
                }
                unlink($tempFile);
                ftp_close($ftp);
                
                echo json_encode(['success' => true, 'content' => $content]);
            } else {
                ftp_close($ftp);
                throw new Exception("Erro ao baixar arquivo do FTP.");
            }
            break;

        case 'ftp_file_save':
            $connection_id = $_POST['connection_id'] ?? '';
            $path = $_POST['path'] ?? '';
            $content = $_POST['content'] ?? '';
            
            if (empty($path)) throw new Exception("Caminho não especificado.");
            
            $connInfo = get_ftp_connection($connection_id);
            $ftp = connect_ftp($connInfo);
            
            $tempFile = tempnam(sys_get_temp_dir(), 'ftp_');
            file_put_contents($tempFile, $content);
            
            if (@ftp_put($ftp, $path, $tempFile, FTP_BINARY)) {
                unlink($tempFile);
                ftp_close($ftp);
                echo json_encode(['success' => true]);
            } else {
                unlink($tempFile);
                ftp_close($ftp);
                throw new Exception("Erro ao salvar arquivo no FTP.");
            }
            break;

        case 'ftp_mkdir':
            $connection_id = $_POST['connection_id'] ?? '';
            $path = $_POST['path'] ?? '';
            $connInfo = get_ftp_connection($connection_id);
            $ftp = connect_ftp($connInfo);
            
            if (@ftp_mkdir($ftp, $path)) {
                echo json_encode(['success' => true]);
            } else {
                throw new Exception("Erro ao criar diretório.");
            }
            ftp_close($ftp);
            break;
            
        case 'ftp_delete':
            $connection_id = $_POST['connection_id'] ?? '';
            $path = $_POST['path'] ?? '';
            $is_dir = isset($_POST['is_dir']) && $_POST['is_dir'] === 'true';
            $connInfo = get_ftp_connection($connection_id);
            $ftp = connect_ftp($connInfo);
            
            if ($is_dir) {
                $delete_recursive = function($conn, $dirPath) use (&$delete_recursive) {
                    $items = @ftp_mlsd($conn, $dirPath);
                    if ($items !== false) {
                        foreach ($items as $item) {
                            if ($item['name'] === '.' || $item['name'] === '..') continue;
                            $fullPath = rtrim($dirPath, '/') . '/' . ltrim($item['name'], '/');
                            if ($item['type'] === 'dir') {
                                $delete_recursive($conn, $fullPath);
                            } else {
                                @ftp_delete($conn, $fullPath);
                            }
                        }
                    }
                    return @ftp_rmdir($conn, $dirPath);
                };
                $success = $delete_recursive($ftp, $path);
            } else {
                $success = @ftp_delete($ftp, $path);
            }
            
            if ($success) {
                echo json_encode(['success' => true]);
            } else {
                $err = error_get_last();
                $errMsg = $err ? $err['message'] : 'Erro desconhecido';
                throw new Exception("Erro ao deletar: " . $errMsg);
            }
            ftp_close($ftp);
            break;
            
        case 'ftp_rename':
            $connection_id = $_POST['connection_id'] ?? '';
            $old_path = $_POST['old_path'] ?? '';
            $new_path = $_POST['new_path'] ?? '';
            $connInfo = get_ftp_connection($connection_id);
            $ftp = connect_ftp($connInfo);
            
            if (@ftp_rename($ftp, $old_path, $new_path)) {
                echo json_encode(['success' => true]);
            } else {
                throw new Exception("Erro ao renomear.");
            }
            ftp_close($ftp);
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
