<?php
// index.php - Main IDE Interface for KodeWeb Lite
require_once 'auth.php';
require_once 'config.php';
require_once 'encryption.php';

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once(__DIR__ . '/vendor/autoload.php');
}
use Symfony\Component\Yaml\Yaml;

$current_username = 'user';
$auth_file = __DIR__ . '/data/auth.enc';
if (file_exists($auth_file)) {
    $encData = file_get_contents($auth_file);
    $decData = KodeWebEncryption::decrypt($encData);
    if ($decData) {
        $authData = json_decode($decData, true);
        if (!empty($authData['username'])) {
            $current_username = $authData['username'];
        }
    }
}

$user_settings_file = __DIR__ . '/data/user-settings.yaml';
$editor_theme = 'dracula'; // default
if (file_exists($user_settings_file) && class_exists('Symfony\Component\Yaml\Yaml')) {
    try {
        $user_settings = Yaml::parseFile($user_settings_file) ?: [];
        $editor_theme = $user_settings['editor']['theme'] ?? 'dracula';
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>KodeWeb Lite</title>
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <link rel="stylesheet" href="style.css">
    
    <!-- PWA configuration -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#140523">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="logo.svg">

    <!-- Ace Editor CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.7/ace.js" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.7/ext-language_tools.min.js" referrerpolicy="no-referrer"></script>
    
    <script>
        const CURRENT_USERNAME = <?= json_encode($current_username) ?>;
        const EDITOR_THEME = <?= json_encode($editor_theme) ?>;
    </script>
</head>
<body>

    <!-- Top Header Bar -->
    <header class="top-header">
        <div class="left-header-section">
            <button class="hamburger-btn" onclick="toggleSidebar(true)">☰</button>
            <div class="logo-section">
                <img src="logo.svg" alt="Logo">
                <span class="app-title">KodeWeb Lite</span>
            </div>
        </div>
        
        <div class="right-header-section">
            <button class="header-action-btn" onclick="saveActiveFile()" title="Salvar Arquivo (Ctrl+S)">💾</button>
            <button class="header-action-btn" onclick="openWorkspaceModal()" title="Configurar Workspace">⚙️</button>
            <a href="logout.php" class="header-action-btn" title="Sair" style="text-decoration: none;">🚪</a>
        </div>
    </header>

    <!-- Horizontal Tabs Bar -->
    <div class="tabs-bar" id="tabs-container">
        <!-- Tabs loaded dynamically -->
    </div>

    <!-- Main Workspace Container -->
    <main class="workspace-views">
        
        <!-- Editor View -->
        <section id="view-editor" class="workspace-view active-view">
            <div class="no-file-placeholder" id="editor-placeholder">
                <img src="logo.svg" alt="Logo">
                <h3>Boas-vindas ao KodeWeb Lite</h3>
                <p>Abra o menu lateral e selecione um recurso para começar.</p>
                
                <div class="shortcut-tips">
                    <h4>Atalhos Rápidos</h4>
                    <div class="shortcut-row">
                        <span>Salvar</span>
                        <kbd>Ctrl + S</kbd>
                    </div>
                    <div class="shortcut-row">
                        <span>Fechar Aba</span>
                        <kbd>Alt + W</kbd>
                    </div>
                    <div class="shortcut-row">
                        <span>Rodar SQL</span>
                        <kbd>Ctrl + Enter</kbd>
                    </div>
                </div>
            </div>
            <div id="editor" class="hidden"></div>
        </section>

        <!-- Database Explorer View -->
        <section id="view-db" class="workspace-view">
            <div class="db-view-container">
                <div class="panel-title">🗄️ Database Explorer (<span id="active-db-name">Nenhuma conexão</span>)</div>
                
                <div class="db-selector-bar">
                    <select class="db-select" id="db-database-select" onchange="onDatabaseChanged()">
                        <option value="">Selecione o Banco</option>
                    </select>
                    <select class="db-select" id="db-table-select" onchange="onTableChanged()">
                        <option value="">Selecione a Tabela</option>
                    </select>
                </div>

                <div class="sql-runner-area">
                    <textarea class="sql-textarea" id="db-sql-input" placeholder="Digite sua consulta SQL aqui... (Ctrl+Enter para executar)"></textarea>
                    <button class="btn btn-primary" onclick="runSql()">Executar Consulta</button>
                </div>

                <div class="db-status-bar" id="db-status">Pronto</div>

                <div class="db-results-container">
                    <table class="db-table" id="db-results-table">
                        <thead>
                            <tr>
                                <th>Resultado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Nenhum dado carregado.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Terminal View -->
        <section id="view-terminal" class="workspace-view">
            <div class="terminal-view-container">
                <div class="terminal-output" id="terminal-output-area">KodeWeb Terminal - Digite os comandos abaixo...
</div>
                <div class="terminal-prompt-line">
                    <span class="terminal-path" id="terminal-path-indicator">/</span>
                    <input type="text" class="terminal-input" id="terminal-cmd-input" placeholder="Digite o comando..." autocomplete="off">
                    
                    <div class="autocomplete-dropdown" id="terminal-autocomplete">
                        <!-- Autocomplete matches will be injected here -->
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- Collapsible Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar(false)"></div>

    <!-- Collapsible Sidebar Menu Drawer -->
    <aside class="sidebar" id="sidebar-drawer">
        <div class="sidebar-header">
            <span class="title" style="display:flex; align-items:center; gap:8px;">
                <span class="sidebar-logo-brand" style="display:flex; align-items:center; gap:6px;">
                    <img src="logo.svg" alt="Logo" style="height:20px; width:20px;">
                    <strong>KodeWeb Lite</strong>
                </span>
                <span class="sidebar-logo-separator">|</span>
                <span>Menu Principal</span>
            </span>
            <button class="sidebar-close-btn" onclick="toggleSidebar(false)">✕</button>
        </div>
        
        <div class="sidebar-content">
            
            <!-- User configuration area -->
            <div class="user-settings-box">
                <div class="user-name-tag">👤 Usuário: <strong style="color: var(--accent-success);"><?= htmlspecialchars($current_username) ?></strong></div>
                <div style="display: flex; flex-direction: column; gap: 4px; margin-top: 4px;">
                    <label style="font-size: 11px; color: var(--text-muted);">Tema do Editor:</label>
                    <select class="db-select" style="padding: 6px; font-size: 12px;" id="theme-selector" onchange="changeEditorTheme(this.value)">
                        <option value="dracula">Dracula</option>
                        <option value="monokai">Monokai</option>
                        <option value="twilight">Twilight</option>
                        <option value="terminal">Terminal</option>
                        <option value="chrome">Chrome</option>
                        <option value="github">Github</option>
                    </select>
                </div>
            </div>

            <!-- Accordion Section: Local Files -->
            <div class="accordion-section" id="sec-local-files">
                <div class="accordion-header" onclick="toggleAccordion('sec-local-files')">
                    <span>📁 Arquivos Locais</span>
                    <span class="arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div style="padding: 6px 8px; font-size: 11px; color: var(--text-muted); border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                        <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 80%;" id="current-ws-path-label" title="Pasta atual do Workspace">...</span>
                        <span style="cursor: pointer; font-size: 14px;" onclick="openChangePathModal(event)" title="Alterar Pasta">✏️</span>
                    </div>
                    <div id="local-file-tree">
                        <!-- Tree nodes will render here -->
                        <div style="font-size:12px; color:var(--text-muted); padding:8px;">Carregando arquivos locais...</div>
                    </div>
                </div>
            </div>

            <!-- Accordion Section: FTP Connection -->
            <div class="accordion-section collapsed" id="sec-ftp-files">
                <div class="accordion-header" onclick="toggleAccordion('sec-ftp-files')">
                    <span>⚡ Arquivos FTP</span>
                    <span class="arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="sidebar-action-item" style="margin-bottom: 8px;">
                        <button class="sidebar-btn btn-accent" onclick="openFtpModal()">➕ Nova Conexão FTP</button>
                    </div>
                    <div id="ftp-connections-list" class="sidebar-action-list">
                        <!-- FTP connections render here -->
                    </div>
                    <div id="ftp-file-tree" class="hidden" style="margin-top: 10px; border-top: 1px solid var(--border-color); padding-top: 10px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <span style="font-size:12px; font-weight:600; color:var(--accent-success);" id="ftp-active-conn-name">FTP Conectado</span>
                            <button class="btn" style="padding: 2px 6px; font-size: 11px;" onclick="disconnectFtp()">Desconectar</button>
                        </div>
                        <div id="ftp-tree-container"></div>
                    </div>
                </div>
            </div>

            <!-- Accordion Section: Database Explorer -->
            <div class="accordion-section collapsed" id="sec-db-explorer">
                <div class="accordion-header" onclick="toggleAccordion('sec-db-explorer')">
                    <span>🗄️ Banco de Dados</span>
                    <span class="arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="sidebar-action-item" style="margin-bottom: 8px;">
                        <button class="sidebar-btn btn-accent" onclick="openDbModal()">➕ Nova Conexão DB</button>
                    </div>
                    <div id="db-connections-list" class="sidebar-action-list">
                        <!-- DB connections render here -->
                    </div>
                </div>
            </div>

            <!-- Accordion Section: Terminal -->
            <div class="accordion-section collapsed" id="sec-terminal">
                <div class="accordion-header" onclick="toggleAccordion('sec-terminal')">
                    <span>🖥️ Terminal</span>
                    <span class="arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <button class="sidebar-btn" onclick="openTerminalTab()">🖥️ Abrir Aba de Terminal</button>
                </div>
            </div>

        </div>
    </aside>

    <!-- Workspace Configuration Modal -->
    <div class="modal-overlay" id="modal-workspace">
        <div class="modal-card">
            <div class="modal-card-header">
                <h3>Configurações do Workspace</h3>
                <button class="sidebar-close-btn" onclick="closeModal('modal-workspace')">✕</button>
            </div>
            <div class="modal-card-body">
                <div style="margin-bottom: 12px;">
                    <label class="form-label" style="display:block; margin-bottom:6px; font-size:12px; color:var(--text-muted);">Usuário Mestre</label>
                    <input type="text" class="form-input" id="ws-username-input" value="<?= htmlspecialchars($current_username) ?>">
                </div>
                <div style="margin-bottom: 12px;">
                    <label class="form-label" style="display:block; margin-bottom:6px; font-size:12px; color:var(--text-muted);">Nova Senha Mestre (Deixe em branco para não alterar)</label>
                    <input type="password" class="form-input" id="ws-password-input" placeholder="••••••••">
                </div>
                <div style="margin-bottom: 12px;">
                    <label class="form-label" style="display:block; margin-bottom:6px; font-size:12px; color:var(--text-muted);">Caminho do Workspace Local (WORKSPACE_PATH)</label>
                    <input type="text" class="form-input" id="ws-path-input" placeholder="Caminho do diretório local">
                </div>
            </div>
            <div class="modal-card-footer">
                <button class="btn" onclick="closeModal('modal-workspace')">Cancelar</button>
                <button class="btn btn-primary" onclick="saveWorkspaceSettings()">Salvar</button>
            </div>
        </div>
    </div>

    <!-- Change Workspace Path Modal -->
    <div class="modal-overlay" id="modal-workspace-path">
        <div class="modal-card" style="max-width: 380px;">
            <div class="modal-card-header">
                <h3>Alterar Pasta do Workspace</h3>
                <button class="sidebar-close-btn" onclick="closeModal('modal-workspace-path')">✕</button>
            </div>
            <div class="modal-card-body">
                <div style="margin-bottom: 12px;">
                    <label class="form-label" style="display:block; margin-bottom:6px; font-size:12px; color:var(--text-muted);">Novo Caminho do Workspace</label>
                    <input type="text" class="form-input" id="ws-only-path-input" placeholder="Caminho do diretório local">
                </div>
            </div>
            <div class="modal-card-footer">
                <button class="btn" onclick="closeModal('modal-workspace-path')">Cancelar</button>
                <button class="btn btn-primary" onclick="saveWorkspaceOnlyPath()">Salvar</button>
            </div>
        </div>
    </div>

    <!-- FTP Connection Modal -->
    <div class="modal-overlay" id="modal-ftp">
        <div class="modal-card">
            <div class="modal-card-header">
                <h3 id="ftp-modal-title">Nova Conexão FTP</h3>
                <button class="sidebar-close-btn" onclick="closeModal('modal-ftp')">✕</button>
            </div>
            <div class="modal-card-body">
                <input type="hidden" id="ftp-conn-id" value="">
                <div style="margin-bottom: 10px;">
                    <label class="form-label" style="display:block; margin-bottom:4px; font-size:12px;">Nome da Conexão</label>
                    <input type="text" class="form-input" id="ftp-conn-name" placeholder="ex: Servidor Produção" required>
                </div>
                <div style="display:flex; gap:10px; margin-bottom:10px;">
                    <div style="flex:3;">
                        <label class="form-label" style="display:block; margin-bottom:4px; font-size:12px;">Host / Endereço FTP</label>
                        <input type="text" class="form-input" id="ftp-conn-host" placeholder="ftp.meusite.com" required>
                    </div>
                    <div style="flex:1;">
                        <label class="form-label" style="display:block; margin-bottom:4px; font-size:12px;">Porta</label>
                        <input type="number" class="form-input" id="ftp-conn-port" value="21" required>
                    </div>
                </div>
                <div style="margin-bottom: 10px;">
                    <label class="form-label" style="display:block; margin-bottom:4px; font-size:12px;">Usuário</label>
                    <input type="text" class="form-input" id="ftp-conn-user" placeholder="login" required>
                </div>
                <div style="margin-bottom: 10px;">
                    <label class="form-label" style="display:block; margin-bottom:4px; font-size:12px;">Senha</label>
                    <input type="password" class="form-input" id="ftp-conn-pass" placeholder="••••••••">
                </div>
            </div>
            <div class="modal-card-footer">
                <button class="btn btn-danger" id="ftp-btn-delete" style="display:none;" onclick="deleteFtpConnection()">Excluir</button>
                <button class="btn" onclick="closeModal('modal-ftp')">Cancelar</button>
                <button class="btn btn-primary" onclick="saveFtpConnection()">Salvar</button>
            </div>
        </div>
    </div>

    <!-- DB Connection Modal -->
    <div class="modal-overlay" id="modal-db">
        <div class="modal-card">
            <div class="modal-card-header">
                <h3 id="db-modal-title">Nova Conexão Banco de Dados</h3>
                <button class="sidebar-close-btn" onclick="closeModal('modal-db')">✕</button>
            </div>
            <div class="modal-card-body">
                <input type="hidden" id="db-conn-id" value="">
                <div style="margin-bottom: 10px;">
                    <label class="form-label" style="display:block; margin-bottom:4px; font-size:12px;">Nome da Conexão</label>
                    <input type="text" class="form-input" id="db-conn-name" placeholder="ex: Banco MySQL Local" required>
                </div>
                <div style="margin-bottom: 10px;">
                    <label class="form-label" style="display:block; margin-bottom:4px; font-size:12px;">Driver do Banco de Dados</label>
                    <select class="db-select" style="width:100%;" id="db-conn-driver" onchange="onDriverChanged()">
                        <option value="mysql">MySQL / MariaDB</option>
                        <option value="pgsql">PostgreSQL</option>
                        <option value="sqlite">SQLite</option>
                    </select>
                </div>
                <div style="display:flex; gap:10px; margin-bottom:10px;" id="db-host-port-row">
                    <div style="flex:3;">
                        <label class="form-label" style="display:block; margin-bottom:4px; font-size:12px;">Servidor / Host</label>
                        <input type="text" class="form-input" id="db-conn-host" placeholder="localhost">
                    </div>
                    <div style="flex:1;">
                        <label class="form-label" style="display:block; margin-bottom:4px; font-size:12px;">Porta</label>
                        <input type="text" class="form-input" id="db-conn-port" placeholder="3306">
                    </div>
                </div>
                <div style="margin-bottom: 10px;" id="db-user-row">
                    <label class="form-label" style="display:block; margin-bottom:4px; font-size:12px;">Usuário</label>
                    <input type="text" class="form-input" id="db-conn-user" placeholder="root">
                </div>
                <div style="margin-bottom: 10px;" id="db-pass-row">
                    <label class="form-label" style="display:block; margin-bottom:4px; font-size:12px;">Senha</label>
                    <input type="password" class="form-input" id="db-conn-pass" placeholder="••••••••">
                </div>
                <div style="margin-bottom: 10px;">
                    <label class="form-label" style="display:block; margin-bottom:4px; font-size:12px;" id="db-database-label">Banco de Dados Padrão</label>
                    <input type="text" class="form-input" id="db-conn-database" placeholder="ex: minha_loja">
                </div>
            </div>
            <div class="modal-card-footer">
                <button class="btn btn-danger" id="db-btn-delete" style="display:none;" onclick="deleteDbConnection()">Excluir</button>
                <button class="btn" onclick="closeModal('modal-db')">Cancelar</button>
                <button class="btn btn-primary" onclick="saveDbConnection()">Salvar</button>
            </div>
        </div>
    </div>

    <!-- Dynamic Dialog Prompt for New Files/Folders -->
    <div class="modal-overlay" id="modal-prompt">
        <div class="modal-card" style="max-width: 350px;">
            <div class="modal-card-header">
                <h3 id="prompt-title">Novo Item</h3>
                <button class="sidebar-close-btn" onclick="closeModal('modal-prompt')">✕</button>
            </div>
            <div class="modal-card-body">
                <input type="hidden" id="prompt-action-type" value="">
                <input type="hidden" id="prompt-target-path" value="">
                <div style="margin-bottom: 10px;">
                    <label class="form-label" style="display:block; margin-bottom:6px; font-size:12px;" id="prompt-label">Nome do arquivo ou pasta</label>
                    <input type="text" class="form-input" id="prompt-input" required autofocus>
                </div>
            </div>
            <div class="modal-card-footer">
                <button class="btn" onclick="closeModal('modal-prompt')">Cancelar</button>
                <button class="btn btn-primary" onclick="submitPromptModal()">Confirmar</button>
            </div>
        </div>
    </div>

    <!-- Toast Notifications Container -->
    <div id="toast-container"></div>

    <!-- Link App logic -->
    <script src="app.js"></script>

</body>
</html>
