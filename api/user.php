<?php
// api/user.php - User operations and workspace settings for KodeWeb Lite
require_once __DIR__ . '/base.php';

use Symfony\Component\Yaml\Yaml;

try {
    switch ($action) {
        case 'status':
            $auth_file = $rootDir . '/data/auth.enc';
            $username = 'user';
            if (file_exists($auth_file)) {
                $encData = file_get_contents($auth_file);
                $decData = KodeWebEncryption::decrypt($encData);
                if ($decData) {
                    $authData = json_decode($decData, true);
                    $username = $authData['username'] ?? 'user';
                }
            }
            
            $user_settings_file = $rootDir . '/data/user-settings.yaml';
            $theme = 'dracula';
            if (file_exists($user_settings_file) && class_exists('Symfony\Component\Yaml\Yaml')) {
                try {
                    $user_settings = Yaml::parseFile($user_settings_file) ?: [];
                    $theme = $user_settings['editor']['theme'] ?? 'dracula';
                } catch (Exception $e) {}
            }
            
            echo json_encode([
                'success' => true,
                'username' => $username,
                'theme' => $theme,
                'workspace_path' => WORKSPACE_ROOT,
                'app_version' => $app_version
            ]);
            break;

        case 'update_user':
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            if (empty($username)) {
                throw new Exception("Usuário é obrigatório.");
            }
            $auth_file = $rootDir . '/data/auth.enc';
            
            if (empty($password) && file_exists($auth_file)) {
                $encData = file_get_contents($auth_file);
                $decData = KodeWebEncryption::decrypt($encData);
                if ($decData) {
                    $authData = json_decode($decData, true);
                    $password_hash = $authData['password'] ?? '';
                } else {
                    $password_hash = '';
                }
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
            }
            
            $jsonString = json_encode([
                'username' => $username,
                'password' => $password_hash
            ]);
            $encrypted = KodeWebEncryption::encrypt($jsonString);
            if (file_put_contents($auth_file, $encrypted) === false) {
                throw new Exception("Erro ao salvar arquivo de autenticação.");
            }
            echo json_encode(['success' => true]);
            break;

        case 'save_editor_theme':
            $theme = $_POST['theme'] ?? 'dracula';
            if ($theme === 'darcula') $theme = 'dracula';
            $user_settings_file = $rootDir . '/data/user-settings.yaml';
            $user_settings = [];
            
            if (file_exists($user_settings_file) && class_exists('Symfony\Component\Yaml\Yaml')) {
                try {
                    $user_settings = Yaml::parseFile($user_settings_file) ?: [];
                } catch (Exception $e) {}
            }
            
            if (!isset($user_settings['editor'])) $user_settings['editor'] = [];
            $user_settings['editor']['theme'] = $theme;
            
            if (class_exists('Symfony\Component\Yaml\Yaml')) {
                file_put_contents($user_settings_file, Yaml::dump($user_settings, 4, 2));
            }
            echo json_encode(['success' => true]);
            break;

        case 'save_workspace_path':
            $workspacePath = $_POST['workspace_path'] ?? '';
            
            if (empty($workspacePath)) {
                throw new Exception("Caminho do workspace não pode ser vazio.");
            }
            
            $env_file = $rootDir . '/.env';
            $envContent = '';
            if (file_exists($env_file)) {
                $envContent = file_get_contents($env_file);
            }
            
            // Clean out old lines of LOCAL_ENV or WORKSPACE_PATH if they exist
            $lines = explode("\n", $envContent);
            $newLines = [];
            $hasLocalEnv = false;
            foreach ($lines as $line) {
                if (strpos($line, 'WORKSPACE_PATH=') === 0) {
                    continue;
                }
                if (strpos($line, 'LOCAL_ENV=') === 0) {
                    $hasLocalEnv = true;
                }
                $newLines[] = $line;
            }
            
            $envContent = implode("\n", $newLines);
            $envContent = trim($envContent);
            if (empty($envContent) && !$hasLocalEnv) {
                $envContent = "LOCAL_ENV=0";
            }
            $envContent .= "\nWORKSPACE_PATH=" . $workspacePath . "\n";
            
            if (file_put_contents($env_file, $envContent) === false) {
                throw new Exception("Falha ao salvar as alterações no arquivo .env.");
            }
            
            echo json_encode(['success' => true]);
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
