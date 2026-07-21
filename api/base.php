<?php
// api/base.php - Common base for KodeWeb Lite APIs
header('Content-Type: application/json; charset=utf-8');

define('IS_API', true);
$rootDir = dirname(__DIR__);
require_once $rootDir . '/auth.php';
require_once $rootDir . '/config.php';
require_once $rootDir . '/encryption.php';
require_once $rootDir . '/vendor/autoload.php';

// Define the root workspace path (parent of kodeweb-lite directory or custom)
$workspace_path = dirname($rootDir);
if (isset($env['WORKSPACE_PATH']) && trim($env['WORKSPACE_PATH']) !== '') {
    $workspace_path = trim($env['WORKSPACE_PATH']);
}
define('WORKSPACE_ROOT', realpath($workspace_path) ?: $workspace_path);

// Helper function to resolve relative paths safely within the workspace
function get_absolute_path($relativePath) {
    // Sanitize relative path to prevent directory traversal
    $relativePath = str_replace(['../', '..\\'], '', $relativePath);
    $relativePath = trim($relativePath, '/\\');
    
    if (empty($relativePath)) {
        return WORKSPACE_ROOT;
    }
    
    return WORKSPACE_ROOT . DIRECTORY_SEPARATOR . $relativePath;
}

// Helper to get PDO connection from connection ID
function get_pdo_connection($connection_id, $database_override = null) {
    global $rootDir;
    if (empty($connection_id)) {
        throw new Exception("Conexão não especificada.");
    }
    
    $file = $rootDir . '/connections/' . $connection_id . '.enc';
    if (!file_exists($file)) {
        throw new Exception("Conexão não encontrada.");
    }
    
    $encryptedData = file_get_contents($file);
    $decryptedData = KodeWebEncryption::decrypt($encryptedData);
    if (!$decryptedData) {
        throw new Exception("Erro de descriptografia dos dados de conexão.");
    }
    
    $connInfo = json_decode($decryptedData, true);
    if (!$connInfo) {
        throw new Exception("Dados de conexão inválidos.");
    }
    
    $driver = $connInfo['driver'];
    $host = $connInfo['host'];
    $port = $connInfo['port'];
    $username = $connInfo['username'];
    $password = $connInfo['password'];
    $database = ($database_override !== null && $database_override !== '') ? $database_override : $connInfo['database'];
    
    $dsn = '';
    if ($driver === 'mysql') {
        if ($database !== null && $database !== '') {
            $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
        } else {
            $dsn = "mysql:host=$host;charset=utf8mb4";
        }
        if (!empty($port)) {
            $dsn .= ";port=$port";
        }
    } elseif ($driver === 'pgsql') {
        if ($database !== null && $database !== '') {
            $dsn = "pgsql:host=$host;dbname=$database";
        } else {
            $dsn = "pgsql:host=$host";
        }
        if (!empty($port)) {
            $dsn .= ";port=$port";
        }
    } elseif ($driver === 'sqlite') {
        $dsn = "sqlite:" . get_absolute_path($database);
    } else {
        throw new Exception("Driver do banco de dados não suportado: $driver");
    }
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5
    ];
    
    return [
        'pdo' => new PDO($dsn, $username, $password, $options),
        'driver' => $driver,
        'database' => $database
    ];
}

// Helper function to get FTP connection
function get_ftp_connection($connection_id) {
    global $rootDir;
    if (empty($connection_id)) {
        throw new Exception("Conexão FTP não especificada.");
    }
    
    $file = $rootDir . '/connections/' . $connection_id . '.enc';
    if (!file_exists($file)) {
        throw new Exception("Conexão FTP não encontrada.");
    }
    
    $encryptedData = file_get_contents($file);
    $decryptedData = KodeWebEncryption::decrypt($encryptedData);
    if (!$decryptedData) {
        throw new Exception("Erro de descriptografia dos dados de conexão FTP.");
    }
    
    $connInfo = json_decode($decryptedData, true);
    if (!$connInfo || !isset($connInfo['type']) || $connInfo['type'] !== 'ftp') {
        throw new Exception("Dados de conexão FTP inválidos.");
    }
    return $connInfo;
}

function get_ssh_connection($connection_id) {
    global $rootDir;
    if (empty($connection_id)) throw new Exception("Conexão SSH não especificada.");
    $file = $rootDir . '/connections/' . $connection_id . '.enc';
    if (!file_exists($file)) throw new Exception("Conexão SSH não encontrada.");
    $encryptedData = file_get_contents($file);
    $decryptedData = KodeWebEncryption::decrypt($encryptedData);
    if (!$decryptedData) throw new Exception("Erro de descriptografia dos dados de conexão SSH.");
    $connInfo = json_decode($decryptedData, true);
    if (!$connInfo || !isset($connInfo['type']) || $connInfo['type'] !== 'ssh') throw new Exception("Dados de conexão SSH inválidos.");
    return $connInfo;
}

function connect_ssh($connInfo) {
    if (!class_exists('\phpseclib3\Net\SSH2')) throw new Exception("A biblioteca phpseclib não está carregada.");
    $host = $connInfo['host'];
    $port = !empty($connInfo['port']) ? (int)$connInfo['port'] : 22;
    $username = $connInfo['username'];
    $password = $connInfo['password'];
    $ssh = new \phpseclib3\Net\SSH2($host, $port);
    if (!$ssh->login($username, $password)) throw new Exception("Falha de autenticação SSH.");
    return $ssh;
}

function connect_ftp($connInfo) {
    $host = $connInfo['host'];
    $port = !empty($connInfo['port']) ? (int)$connInfo['port'] : 21;
    $username = $connInfo['username'];
    $password = $connInfo['password'];
    
    if (!function_exists('ftp_connect')) {
        throw new Exception("Extensão FTP não está habilitada no PHP.");
    }
    
    $ftp = @ftp_connect($host, $port, 10);
    if (!$ftp) {
        throw new Exception("Não foi possível conectar ao servidor FTP $host:$port.");
    }
    
    if (!@ftp_login($ftp, $username, $password)) {
        throw new Exception("Falha na autenticação FTP para o usuário $username.");
    }
    
    ftp_pasv($ftp, true);
    return $ftp;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
