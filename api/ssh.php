<?php
require_once __DIR__ . '/base.php';

try {
    switch ($action) {
        case 'ssh_connections_list':
            $connections_dir = $rootDir . '/connections';
            if (!is_dir($connections_dir)) mkdir($connections_dir, 0700, true);
            $connections = [];
            $files = glob($connections_dir . '/*.enc');
            foreach ($files as $file) {
                $encryptedData = file_get_contents($file);
                $decryptedData = KodeWebEncryption::decrypt($encryptedData);
                if ($decryptedData) {
                    $data = json_decode($decryptedData, true);
                    if ($data && isset($data['type']) && $data['type'] === 'ssh') {
                        $data['has_password'] = !empty($data['password']);
                        unset($data['password']);
                        $connections[] = $data;
                    }
                }
            }
            echo json_encode(['success' => true, 'connections' => $connections]);
            break;

        case 'ssh_connection_save':
            $id = $_POST['id'] ?? '';
            $name = $_POST['name'] ?? '';
            $host = $_POST['host'] ?? '';
            $port = $_POST['port'] ?? '';
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            if (empty($name) || empty($host) || empty($username)) throw new Exception("Nome, Host e Usuário são obrigatórios.");
            if (empty($id)) {
                $id = uniqid('ssh_');
            } else {
                if ($password === '********') {
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
                'id' => $id, 'type' => 'ssh', 'name' => $name, 'host' => $host, 'port' => $port, 'username' => $username, 'password' => $password
            ];
            $connections_dir = $rootDir . '/connections';
            if (!is_dir($connections_dir)) mkdir($connections_dir, 0700, true);
            $jsonString = json_encode($connectionData);
            $encrypted = KodeWebEncryption::encrypt($jsonString);
            if (file_put_contents($connections_dir . '/' . $id . '.enc', $encrypted) === false) throw new Exception("Erro ao salvar arquivo de conexão SSH.");
            echo json_encode(['success' => true, 'id' => $id]);
            break;

        case 'ssh_connection_delete':
            $id = $_POST['id'] ?? '';
            if (empty($id)) throw new Exception("ID de conexão inválido.");
            $file = $rootDir . '/connections/' . $id . '.enc';
            if (file_exists($file)) unlink($file);
            echo json_encode(['success' => true]);
            break;

        case 'ssh_test_connection':
            $connection_id = $_POST['connection_id'] ?? '';
            if (!empty($connection_id)) {
                $connInfo = get_ssh_connection($connection_id);
            } else {
                $connInfo = [
                    'host' => $_POST['host'] ?? '',
                    'port' => $_POST['port'] ?? 22,
                    'username' => $_POST['username'] ?? '',
                    'password' => $_POST['password'] ?? ''
                ];
            }
            $ssh = connect_ssh($connInfo);
            echo json_encode(['success' => true, 'message' => 'Conexão SSH bem-sucedida!']);
            break;

        case 'ssh_terminal_cmd':
            $connection_id = $_POST['connection_id'] ?? '';
            $cmd = $_POST['cmd'] ?? '';
            $terminal_id = $_POST['terminal_id'] ?? 'default';
            
            $connInfo = get_ssh_connection($connection_id);
            $ssh = connect_ssh($connInfo);
            
            if (!isset($_SESSION['ssh_cwd'])) $_SESSION['ssh_cwd'] = [];
            if (!isset($_SESSION['ssh_cwd'][$terminal_id])) {
                $pwdOutput = $ssh->exec('pwd');
                $_SESSION['ssh_cwd'][$terminal_id] = trim($pwdOutput);
            }
            
            if ($cmd === '') {
                echo json_encode(['success' => true, 'output' => '', 'cwd' => $_SESSION['ssh_cwd'][$terminal_id], 'autocomplete_list' => []]);
                break;
            }
            
            $current_cwd = $_SESSION['ssh_cwd'][$terminal_id];
            $delimiter = "---KODEWEB_PWD_DELIMITER---";
            
            $full_cmd = "cd " . escapeshellarg($current_cwd) . " && eval " . escapeshellarg($cmd) . " 2>&1; echo " . escapeshellarg($delimiter) . "; pwd";
            
            $output = $ssh->exec($full_cmd);
            $clean_output = $output;
            $new_cwd = $current_cwd;
            
            if (strpos($output, $delimiter) !== false) {
                $parts = explode($delimiter, $output);
                $clean_output = rtrim($parts[0]);
                $new_cwd = trim($parts[1]);
                $_SESSION['ssh_cwd'][$terminal_id] = $new_cwd;
            }
            
            $autocomplete_list = [];
            $lsOutput = $ssh->exec("cd " . escapeshellarg($_SESSION['ssh_cwd'][$terminal_id]) . " && ls -p");
            if ($lsOutput) {
                $lines = explode("\n", trim($lsOutput));
                foreach ($lines as $line) {
                    if (!empty($line)) $autocomplete_list[] = trim($line);
                }
            }
            
            echo json_encode([
                'success' => true,
                'output' => $clean_output,
                'cwd' => $_SESSION['ssh_cwd'][$terminal_id],
                'autocomplete_list' => $autocomplete_list
            ], JSON_INVALID_UTF8_SUBSTITUTE);
            break;

        default:
            throw new Exception("Ação desconhecida ou não suportada neste módulo: $action");
    }
} catch (Exception $e) {
    http_response_code(400);
    
    $log_file = $rootDir . '/kodeweb_error.log';
    $log_message = date('Y-m-d H:i:s') . " - Action [{$action}]: " . $e->getMessage() . "\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
