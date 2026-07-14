<?php
// login.php - Login for KodeWeb Lite

require_once __DIR__ . '/session.php';

$auth_file = __DIR__ . '/data/auth.enc';

if (!file_exists($auth_file)) {
    header("Location: install.php");
    exit;
}

if (!empty($_SESSION['logged_in'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'encryption.php';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Por favor, preencha todos os campos.';
    } else {
        $encData = file_get_contents($auth_file);
        $decData = KodeWebEncryption::decrypt($encData);
        if ($decData) {
            $authData = json_decode($decData, true);
            if ($authData && $username === $authData['username'] && password_verify($password, $authData['password'])) {
                $_SESSION['logged_in'] = true;
                header("Location: index.php");
                exit;
            } else {
                $error = 'Usuário ou senha incorretos.';
            }
        } else {
            $error = 'Erro ao ler o arquivo de autenticação.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KodeWeb Lite - Login</title>
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <link rel="stylesheet" href="style.css">
    
    <!-- PWA configuration -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#140523">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="logo.svg">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: var(--bg-primary, #0b0114);
            padding: 20px;
        }
        .login-card {
            background-color: var(--bg-secondary, #140523);
            border: 1px solid var(--border-color, rgba(255,255,255,0.1));
            border-radius: 12px;
            padding: 24px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.6);
            text-align: center;
        }
        .login-card img {
            width: 72px;
            height: 72px;
            margin-bottom: 12px;
        }
        .login-card h2 {
            margin-bottom: 20px;
            color: var(--text-active, #ffffff);
            font-size: 22px;
            font-weight: 600;
        }
        .form-group {
            text-align: left;
            margin-bottom: 16px;
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
            outline: none;
            box-sizing: border-box;
        }
        .form-input:focus {
            border-color: var(--accent, #bd00ff);
        }
        .error-message {
            color: var(--accent-error, #ff0055);
            background-color: rgba(255, 0, 85, 0.1);
            border: 1px solid rgba(255, 0, 85, 0.2);
            padding: 10px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 16px;
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
    </style>
</head>
<body>

    <div class="login-card">
        <img src="logo.svg" alt="KodeWeb Lite Logo">
        <h2>KodeWeb Lite</h2>
        
        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Usuário</label>
                <input type="text" class="form-input" id="username" name="username" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" class="form-input" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn-full">Entrar</button>
        </form>
    </div>

    <!-- Register Service Worker in login page -->
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js')
                .then(reg => console.log('Service Worker registrado (login):', reg.scope))
                .catch(err => console.error('Erro ao registrar Service Worker (login):', err));
        }
    </script>
</body>
</html>
