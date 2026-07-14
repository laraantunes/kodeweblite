<?php
// api/db.php - Database connection and querying for KodeWeb Lite
require_once __DIR__ . '/base.php';

try {
    switch ($action) {
        case 'db_connections_list':
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
                    if ($data) {
                        // Skip non-DB connections (FTP and SSH)
                        if (isset($data['type']) && in_array($data['type'], ['ftp', 'ssh'])) {
                            continue;
                        }
                        unset($data['password']);
                        $connections[] = $data;
                    }
                }
            }
            
            echo json_encode(['success' => true, 'connections' => $connections]);
            break;

        case 'db_connection_save':
            $id = $_POST['id'] ?? '';
            $name = $_POST['name'] ?? '';
            $driver = $_POST['driver'] ?? 'mysql';
            $host = $_POST['host'] ?? '';
            $port = $_POST['port'] ?? '';
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $database = $_POST['database'] ?? '';
            
            if (empty($name)) {
                throw new Exception("O nome da conexão é obrigatório.");
            }
            
            if (empty($id)) {
                $id = uniqid('conn_');
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
                'name' => $name,
                'driver' => $driver,
                'host' => $host,
                'port' => $port,
                'username' => $username,
                'password' => $password,
                'database' => $database
            ];
            
            $connections_dir = $rootDir . '/connections';
            if (!is_dir($connections_dir)) {
                mkdir($connections_dir, 0700, true);
            }
            
            $jsonString = json_encode($connectionData);
            $encrypted = KodeWebEncryption::encrypt($jsonString);
            
            if (file_put_contents($connections_dir . '/' . $id . '.enc', $encrypted) === false) {
                throw new Exception("Erro ao salvar arquivo de conexão.");
            }
            
            echo json_encode(['success' => true, 'id' => $id]);
            break;

        case 'db_connection_delete':
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

        case 'db_test':
            $driver = $_POST['driver'] ?? 'mysql';
            $host = $_POST['host'] ?? '';
            $port = $_POST['port'] ?? '';
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $database = $_POST['database'] ?? '';
            
            $dsn = '';
            if ($driver === 'mysql') {
                $dsn = "mysql:host=$host;charset=utf8mb4";
                if (!empty($database)) $dsn .= ";dbname=$database";
                if (!empty($port)) $dsn .= ";port=$port";
            } elseif ($driver === 'pgsql') {
                $dsn = "pgsql:host=$host";
                if (!empty($database)) $dsn .= ";dbname=$database";
                if (!empty($port)) $dsn .= ";port=$port";
            } elseif ($driver === 'sqlite') {
                $dsn = "sqlite:" . get_absolute_path($database);
            } else {
                throw new Exception("Driver não suportado: $driver");
            }
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 3
            ];
            
            $pdo = new PDO($dsn, $username, $password, $options);
            echo json_encode(['success' => true]);
            break;

        case 'db_list_databases':
            $connection_id = $_GET['connection_id'] ?? $_POST['connection_id'] ?? '';
            $conn = get_pdo_connection($connection_id);
            $pdo = $conn['pdo'];
            $driver = $conn['driver'];
            $databases = [];
            
            if ($driver === 'mysql') {
                $stmt = $pdo->query("SHOW DATABASES");
                $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($driver === 'pgsql') {
                $stmt = $pdo->query("SELECT datname FROM pg_database WHERE datistemplate = false");
                $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($driver === 'sqlite') {
                $databases = [$conn['database']];
            }
            echo json_encode(['success' => true, 'databases' => $databases, 'driver' => $driver]);
            break;

        case 'db_list_tables':
            $connection_id = $_GET['connection_id'] ?? $_POST['connection_id'] ?? '';
            $database = $_GET['database'] ?? $_POST['database'] ?? '';
            $conn = get_pdo_connection($connection_id, empty($database) ? null : $database);
            $pdo = $conn['pdo'];
            $driver = $conn['driver'];
            $tables = [];
            
            if ($driver === 'mysql') {
                $stmt = $pdo->query("SHOW TABLES");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($driver === 'pgsql') {
                $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($driver === 'sqlite') {
                $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            echo json_encode(['success' => true, 'tables' => $tables]);
            break;

        case 'db_query_execute':
            $connection_id = $_POST['connection_id'] ?? '';
            $sql = $_POST['sql'] ?? '';
            $database = $_POST['database'] ?? '';
            
            if (empty($sql)) {
                throw new Exception("Instrução SQL vazia.");
            }
            
            $conn = get_pdo_connection($connection_id, empty($database) ? null : $database);
            $pdo = $conn['pdo'];
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            
            $results = [];
            $columns = [];
            $affected_rows = 0;
            $is_select = false;
            
            if (preg_match('/^\s*(select|show|describe|explain|pragma)\b/i', $sql)) {
                $is_select = true;
                $results = $stmt->fetchAll();
                if (count($results) > 0) {
                    $columns = array_keys($results[0]);
                } else {
                    for ($i = 0; $i < $stmt->columnCount(); $i++) {
                        $meta = $stmt->getColumnMeta($i);
                        if ($meta) {
                            $columns[] = $meta['name'];
                        }
                    }
                }
            } else {
                $affected_rows = $stmt->rowCount();
            }
            
            echo json_encode([
                'success' => true,
                'is_select' => $is_select,
                'columns' => $columns,
                'rows' => $results,
                'affected_rows' => $affected_rows
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
