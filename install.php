<?php
// install.php - Installer for KodeWeb Lite

$auth_file = __DIR__ . '/data/auth.enc';
$is_installed = file_exists($auth_file);
$message = '';
$message_type = ''; // 'success' or 'error'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'encryption.php';
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $workspace = $_POST['workspace_path'] ?? '';
    $is_local = isset($_POST['is_local']) ? '1' : '0';

    if (empty($username) || empty($password)) {
        $message = 'Usuário e senha são obrigatórios.';
        $message_type = 'error';
    } else {
        // Create data folder if it doesn't exist
        $data_dir = __DIR__ . '/data';
        if (!is_dir($data_dir)) {
            mkdir($data_dir, 0755, true);
        }

        // Create data/.htaccess for protection
        file_put_contents($data_dir . '/.htaccess', "Require all denied\nDeny from all");

        // Create initial user-settings.yaml if it doesn't exist
        $user_settings_file = $data_dir . '/user-settings.yaml';
        if (!file_exists($user_settings_file)) {
            $default_settings = "# Configurações do Usuário\neditor:\n  theme: dracula\n";
            file_put_contents($user_settings_file, $default_settings);
        }

        // Hash the password
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $auth_data = json_encode(['username' => $username, 'password' => $hash]);
        
        // Encrypt and save
        $encrypted = KodeWebEncryption::encrypt($auth_data);
        if (file_put_contents($auth_file, $encrypted) !== false) {
            
            // Create .env
            $env_content = "LOCAL_ENV=" . $is_local . "\n";
            if (!empty($workspace)) {
                $env_content .= "WORKSPACE_PATH=" . $workspace . "\n";
            }
            file_put_contents(__DIR__ . '/.env', $env_content);
            
            header("Location: login.php");
            exit;
        } else {
            $message = 'Erro ao salvar o arquivo de autenticação. Verifique as permissões.';
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KodeWeb Lite - Instalação</title>
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: var(--bg-primary, #0b0114);
            padding: 20px;
        }
        .install-card {
            background-color: var(--bg-secondary, #140523);
            border: 1px solid var(--border-color, rgba(255,255,255,0.1));
            border-radius: 12px;
            padding: 24px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.6);
            text-align: center;
        }
        .install-card img {
            width: 72px;
            height: 72px;
            margin-bottom: 12px;
        }
        .install-card h2 {
            margin-bottom: 6px;
            color: var(--text-active, #ffffff);
            font-size: 22px;
            font-weight: 600;
        }
        .install-card p {
            font-size: 13px;
            color: var(--text-muted, #a0a0a0);
            margin-bottom: 20px;
            line-height: 1.4;
        }
        .form-group {
            text-align: left;
            margin-bottom: 14px;
        }
        .form-group label {
            display: block;
            font-size: 12px;
            margin-bottom: 5px;
            color: var(--text-muted, #a0a0a0);
        }
        .form-input {
            width: 100%;
            background-color: var(--bg-input, #1d0c2c);
            border: 1px solid var(--border-input, rgba(255,255,255,0.15));
            border-radius: 6px;
            padding: 10px 12px;
            color: var(--text-primary, #f0f0f0);
            font-size: 14px;
            font-family: inherit;
            outline: none;
            box-sizing: border-box;
        }
        .form-input:focus {
            border-color: var(--accent, #bd00ff);
        }
        .alert-error {
            color: var(--accent-error, #ff0055);
            background-color: rgba(255, 0, 85, 0.1);
            border: 1px solid rgba(255, 0, 85, 0.2);
            padding: 10px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 16px;
        }
        .alert-warning {
            color: #ffc107;
            background-color: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.2);
            padding: 10px;
            border-radius: 6px;
            font-size: 12px;
            margin-bottom: 16px;
            text-align: left;
            line-height: 1.4;
        }
        .btn-full {
            width: 100%;
            background-color: var(--accent, #bd00ff);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
            margin-top: 10px;
        }
        .btn-full:hover {
            opacity: 0.9;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 16px 0;
            cursor: pointer;
            user-select: none;
        }
        .checkbox-group input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--accent, #bd00ff);
            cursor: pointer;
        }
        .checkbox-group label {
            cursor: pointer;
            margin-bottom: 0;
            font-size: 13px;
        }
    </style>
</head>
<body>

    <div class="install-card">
        <img src="logo.svg" alt="KodeWeb Lite Logo">
        <h2>KodeWeb Lite</h2>
        <p>Defina as credenciais de acesso mestre para o seu celular.</p>
        
        <?php if ($message && $message_type === 'error'): ?>
            <div class="alert-error"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($is_installed): ?>
            <div class="alert-warning">
                <strong>Atenção:</strong> O KodeWeb Lite já está instalado. 
                Ao prosseguir, você estará substituindo a conta de acesso atual.
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Usuário Administrador</label>
                <input type="text" class="form-input" id="username" name="username" placeholder="ex: admin" required <?= !$is_installed ? 'autofocus' : '' ?>>
            </div>
            
            <div class="form-group">
                <label for="password">Senha de Acesso</label>
                <input type="password" class="form-input" id="password" name="password" placeholder="••••••••" required>
            </div>

            <div class="form-group">
                <label for="workspace_path">Caminho da Pasta Workspace (Opcional)</label>
                <input type="text" class="form-input" id="workspace_path" name="workspace_path" placeholder="Deixe em branco para o diretório padrão">
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="is_local" name="is_local" value="1" checked>
                <label for="is_local">Ambiente Local (Desenvolvimento)</label>
            </div>
            
            <button type="submit" class="btn-full">
                <?= $is_installed ? 'Atualizar Credenciais' : 'Instalar e Concluir' ?>
            </button>
            
            <?php if ($is_installed): ?>
                <div style="margin-top: 15px;">
                    <a href="login.php" style="color: var(--text-muted, #a0a0a0); font-size: 12px; text-decoration: none;">Voltar para o Login</a>
                </div>
            <?php endif; ?>
        </form>
    </div>

</body>
</html>
