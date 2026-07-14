// app.js - Main Client-Side Controller for KodeWeb Lite

// Application State
const state = {
    openTabs: [],
    activeTabId: null,
    activeFtpConnId: null,
    activeFtpPath: '/',
    activeDbConnId: null,
    activeDbName: '',
    editor: null,
    terminalCwd: '/',
    terminalHistory: [],
    terminalHistoryIdx: -1,
    autocompleteList: [],
    autocompleteIdx: -1
};

// Initialize Application
document.addEventListener('DOMContentLoaded', () => {
    initEditor();
    loadWorkspaceStatus();
    loadLocalFiles();
    loadFtpConnections();
    loadDbConnections();
    initTerminal();
    setupKeyListeners();

    window.addEventListener('resize', () => {
        if (state.editor) {
            state.editor.resize();
        }
    });

    // Auto-open sidebar menu on load
    toggleSidebar(true);

    // Register PWA service worker
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js')
            .then(reg => console.log('Service Worker registrado (app):', reg.scope))
            .catch(err => console.error('Erro ao registrar Service Worker (app):', err));
    }
});

// Ace Editor Initialization
function initEditor() {
    state.editor = ace.edit("editor");
    state.editor.setTheme("ace/theme/" + (window.EDITOR_THEME || "dracula"));
    state.editor.session.setMode("ace/mode/text");
    
    state.editor.setOptions({
        fontSize: "13px",
        fontFamily: "'Fira Code', monospace",
        enableBasicAutocompletion: true,
        enableLiveAutocompletion: true,
        showPrintMargin: false,
        useWorker: false // Disable worker for offline/hybrid compatibility
    });

    // Make editor read-only if no file is open
    state.editor.setReadOnly(true);
}

// Setup Global Key Listeners
function setupKeyListeners() {
    document.addEventListener('keydown', (e) => {
        // Ctrl + S: Save file
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            saveActiveFile();
        }
        // Alt + W: Close current tab
        if (e.altKey && e.key.toLowerCase() === 'w') {
            e.preventDefault();
            if (state.activeTabId) {
                closeTab(state.activeTabId);
            }
        }
    });

    // Ctrl+Enter support in DB query editor
    const sqlTextarea = document.getElementById('db-sql-input');
    if (sqlTextarea) {
        sqlTextarea.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                runSql();
            }
        });
    }
}

// Toast Notifications
function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <span>${message}</span>
        <span style="margin-left: 10px; cursor: pointer;" onclick="this.parentElement.remove()">✕</span>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.5s ease-out';
        setTimeout(() => toast.remove(), 500);
    }, 3000);
}

// Toggle Sidebar Navigation
function toggleSidebar(show) {
    const sidebar = document.getElementById('sidebar-drawer');
    const overlay = document.getElementById('sidebar-overlay');
    if (show) {
        sidebar.classList.add('open');
        overlay.classList.add('visible');
    } else {
        sidebar.classList.remove('open');
        overlay.classList.remove('visible');
    }
}

// Collapsible Accordion Tabs in Sidebar
function toggleAccordion(id) {
    const section = document.getElementById(id);
    if (!section) return;
    section.classList.toggle('collapsed');
}

// Switch main workspace view
function switchView(viewId) {
    document.querySelectorAll('.workspace-view').forEach(view => {
        view.classList.remove('active-view');
    });
    const target = document.getElementById(viewId);
    if (target) {
        target.classList.add('active-view');
    }
}

// Switch between Editor, DB, Terminal, etc.
function handleTabChange(tab) {
    if (tab.type === 'file' || tab.type === 'ftp') {
        switchView('view-editor');
        document.getElementById('editor-placeholder').classList.add('hidden');
        document.getElementById('editor').classList.remove('hidden');
        
        state.editor.setReadOnly(false);
        state.editor.setValue(tab.content || '', -1);
        setEditorMode(tab.name);
        state.editor.focus();
        state.editor.resize();
    } else if (tab.type === 'terminal') {
        switchView('view-terminal');
        document.getElementById('terminal-cmd-input').focus();
    } else if (tab.type === 'db') {
        switchView('view-db');
    }
}

// Set Editor Syntax Highlight Mode
function setEditorMode(filename) {
    const ext = filename.split('.').pop().toLowerCase();
    let mode = 'text';
    const modes = {
        'html': 'html',
        'css': 'css',
        'js': 'javascript',
        'ts': 'typescript',
        'json': 'json',
        'php': 'php',
        'sql': 'sql',
        'md': 'markdown',
        'py': 'python',
        'sh': 'sh',
        'bash': 'sh',
        'yaml': 'yaml',
        'yml': 'yaml',
        'xml': 'xml'
    };
    if (modes[ext]) {
        mode = modes[ext];
    }
    state.editor.session.setMode("ace/mode/" + mode);
}

// Tab Manager API
function openFile(name, path, isFtp = false, ftpConnId = '') {
    const tabId = isFtp ? `ftp-${ftpConnId}-${path}` : `local-${path}`;
    
    // Check if tab is already open
    const existing = state.openTabs.find(t => t.id === tabId);
    if (existing) {
        activateTab(tabId);
        toggleSidebar(false);
        return;
    }

    showToast(`Carregando ${name}...`);

    let url = 'api/files.php?action=file_read';
    let formData = new FormData();
    formData.append('path', path);

    if (isFtp) {
        url = 'api/ftp.php?action=ftp_file_read';
        formData.append('connection_id', ftpConnId);
    }

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(res => {
        if (!res.ok) throw new Error("Falha ao abrir arquivo");
        return res.json();
    })
    .then(data => {
        if (data.success) {
            // Save current editor content before loading new tab
            saveCurrentTabState();

            const tab = {
                id: tabId,
                name: name,
                path: path,
                type: isFtp ? 'ftp' : 'file',
                connectionId: ftpConnId,
                content: data.content
            };
            
            state.openTabs.push(tab);
            renderTabs();
            activateTab(tabId);
            toggleSidebar(false);
        } else {
            showToast(data.message || 'Erro ao carregar arquivo', 'error');
        }
    })
    .catch(err => {
        showToast(err.message, 'error');
    });
}

function openSpecialTab(type, id, name) {
    const tabId = `${type}-${id}`;
    const existing = state.openTabs.find(t => t.id === tabId);
    if (existing) {
        activateTab(tabId);
        toggleSidebar(false);
        return;
    }

    saveCurrentTabState();

    const tab = {
        id: tabId,
        name: name,
        path: '',
        type: type,
        connectionId: id,
        content: ''
    };

    state.openTabs.push(tab);
    renderTabs();
    activateTab(tabId);
    toggleSidebar(false);
}

function saveCurrentTabState() {
    if (state.activeTabId) {
        const activeTab = state.openTabs.find(t => t.id === state.activeTabId);
        if (activeTab && (activeTab.type === 'file' || activeTab.type === 'ftp')) {
            activeTab.content = state.editor.getValue();
        }
    }
}

function renderTabs() {
    const container = document.getElementById('tabs-container');
    if (!container) return;

    container.innerHTML = '';
    state.openTabs.forEach(tab => {
        const tabEl = document.createElement('div');
        tabEl.className = `tab ${tab.id === state.activeTabId ? 'active' : ''}`;
        tabEl.onclick = () => activateTab(tab.id);

        let icon = '📄';
        if (tab.type === 'ftp') icon = '⚡';
        if (tab.type === 'terminal') icon = '🖥️';
        if (tab.type === 'db') icon = '🗄️';

        // Content span
        const contentSpan = document.createElement('span');
        contentSpan.innerHTML = `${icon} ${tab.name}`;
        tabEl.appendChild(contentSpan);

        // Close button span programmatically configured
        const closeBtn = document.createElement('span');
        closeBtn.className = 'tab-close';
        closeBtn.innerText = '✕';
        closeBtn.onclick = (event) => {
            event.stopPropagation();
            closeTab(tab.id);
        };
        tabEl.appendChild(closeBtn);

        container.appendChild(tabEl);
    });
}

function activateTab(tabId) {
    saveCurrentTabState();
    
    state.activeTabId = tabId;
    renderTabs();

    const tab = state.openTabs.find(t => t.id === tabId);
    if (tab) {
        handleTabChange(tab);
    } else {
        // Reset to placeholder if no active tab
        switchView('view-editor');
        document.getElementById('editor-placeholder').classList.remove('hidden');
        document.getElementById('editor').classList.add('hidden');
        state.editor.setValue('', -1);
        state.editor.setReadOnly(true);
    }
}

function closeTab(tabId, event) {
    if (event) event.stopPropagation();

    const idx = state.openTabs.findIndex(t => t.id === tabId);
    if (idx === -1) return;

    state.openTabs.splice(idx, 1);
    renderTabs();

    if (state.activeTabId === tabId) {
        if (state.openTabs.length > 0) {
            const nextTab = state.openTabs[Math.max(0, idx - 1)];
            activateTab(nextTab.id);
        } else {
            activateTab(null);
        }
    }
}

// Save active file
function saveActiveFile() {
    if (!state.activeTabId) return;

    const tab = state.openTabs.find(t => t.id === state.activeTabId);
    if (!tab || (tab.type !== 'file' && tab.type !== 'ftp')) return;

    const content = state.editor.getValue();
    showToast('Salvando arquivo...');

    let url = 'api/files.php?action=file_save';
    let formData = new FormData();
    formData.append('path', tab.path);
    formData.append('content', content);

    if (tab.type === 'ftp') {
        url = 'api/ftp.php?action=ftp_file_save';
        formData.append('connection_id', tab.connectionId);
    }

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(res => {
        if (!res.ok) throw new Error("Erro ao salvar arquivo");
        return res.json();
    })
    .then(data => {
        if (data.success) {
            showToast('Salvo com sucesso!', 'success');
            tab.content = content;
        } else {
            showToast(data.message || 'Erro ao salvar', 'error');
        }
    })
    .catch(err => {
        showToast(err.message, 'error');
    });
}

// Local File Explorer Tree Builder
function loadLocalFiles() {
    fetch('api/files.php?action=files_list')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const tree = document.getElementById('local-file-tree');
            tree.innerHTML = '';
            renderFileTreeNodes(tree, data.files, '');
        } else {
            showToast('Erro ao carregar lista de arquivos locais', 'error');
        }
    });
}

function renderFileTreeNodes(container, items, parentPath) {
    items.forEach(item => {
        const node = document.createElement('div');
        node.style.margin = '4px 0';
        
        const nodeHeader = document.createElement('div');
        nodeHeader.className = `file-tree-node ${item.is_dir ? 'directory' : 'file'}`;
        
        let icon = item.is_dir ? '📁' : '📄';
        if (!item.is_dir) {
            const ext = item.name.split('.').pop().toLowerCase();
            if (['jpg','png','gif','png','webp','ico'].includes(ext)) icon = '🖼️';
            else if (ext === 'html') icon = '🌐';
            else if (ext === 'css') icon = '🎨';
            else if (ext === 'js' || ext === 'ts') icon = '⚡';
            else if (ext === 'php') icon = '🐘';
            else if (ext === 'sql') icon = '🗄️';
        }

        nodeHeader.innerHTML = `
            <span style="flex:1; display:flex; align-items:center; gap:6px;">
                <span>${icon}</span>
                <span>${item.name}</span>
            </span>
            <span class="actions" style="display:flex; gap:6px;">
                ${item.is_dir ? `<span onclick="promptCreateItem('${item.path}', true, event)" title="Nova Pasta">📁+</span>` : ''}
                ${item.is_dir ? `<span onclick="promptCreateItem('${item.path}', false, event)" title="Novo Arquivo">📄+</span>` : ''}
                <span onclick="promptRenameItem('${item.path}', '${item.name}', event)" title="Renomear">✏️</span>
                <span onclick="confirmDeleteItem('${item.path}', ${item.is_dir}, event)" title="Excluir" style="color:var(--accent-error);">🗑️</span>
            </span>
        `;

        node.appendChild(nodeHeader);

        if (item.is_dir) {
            const childContainer = document.createElement('div');
            childContainer.className = 'file-tree-children hidden';
            childContainer.id = `dir-${item.path.replace(/[^a-zA-Z0-9]/g, '_')}`;
            node.appendChild(childContainer);

            nodeHeader.onclick = (e) => {
                // If they click on actions, ignore folder expand toggle
                if (e.target.closest('.actions')) return;
                toggleFolder(item.path, childContainer);
            };
        } else {
            nodeHeader.onclick = () => {
                openFile(item.name, item.path, false);
            };
        }

        container.appendChild(node);
    });
}

function toggleFolder(path, childContainer) {
    const isHidden = childContainer.classList.contains('hidden');
    if (isHidden) {
        childContainer.classList.remove('hidden');
        childContainer.innerHTML = '<div style="font-size:12px; color:var(--text-muted); padding:6px 12px;">Carregando...</div>';
        
        fetch(`api/files.php?action=files_list&path=${encodeURIComponent(path)}`)
        .then(res => res.json())
        .then(data => {
            childContainer.innerHTML = '';
            if (data.success && data.files.length > 0) {
                renderFileTreeNodes(childContainer, data.files, path);
            } else {
                childContainer.innerHTML = '<div style="font-size:12px; color:var(--text-muted); padding:6px 12px;">Pasta vazia</div>';
            }
        });
    } else {
        childContainer.classList.add('hidden');
    }
}

// Tree Item manipulation Prompts
function promptCreateItem(parentPath, isDir, event) {
    if (event) event.stopPropagation();
    
    document.getElementById('prompt-action-type').value = isDir ? 'create-dir' : 'create-file';
    document.getElementById('prompt-target-path').value = parentPath;
    document.getElementById('prompt-title').innerText = isDir ? 'Nova Pasta' : 'Novo Arquivo';
    document.getElementById('prompt-label').innerText = isDir ? 'Nome da nova pasta:' : 'Nome do novo arquivo:';
    document.getElementById('prompt-input').value = '';
    openModal('modal-prompt');
}

function promptRenameItem(path, oldName, event) {
    if (event) event.stopPropagation();
    
    document.getElementById('prompt-action-type').value = 'rename';
    document.getElementById('prompt-target-path').value = path;
    document.getElementById('prompt-title').innerText = 'Renomear Item';
    document.getElementById('prompt-label').innerText = 'Novo nome para ' + oldName + ':';
    document.getElementById('prompt-input').value = oldName;
    openModal('modal-prompt');
}

function confirmDeleteItem(path, isDir, event) {
    if (event) event.stopPropagation();
    
    if (confirm(`Tem certeza que deseja excluir ${isDir ? 'esta pasta e todos os seus arquivos' : 'este arquivo'}?\nCaminho: ${path}`)) {
        showToast('Excluindo...');
        
        let formData = new FormData();
        formData.append('path', path);
        
        fetch('api/files.php?action=file_delete', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('Excluído com sucesso!', 'success');
                // Remove closed tab if it was open
                const tabId = `local-${path}`;
                closeTab(tabId);
                loadLocalFiles();
            } else {
                showToast(data.message || 'Erro ao excluir', 'error');
            }
        });
    }
}

function submitPromptModal() {
    const action = document.getElementById('prompt-action-type').value;
    const targetPath = document.getElementById('prompt-target-path').value;
    const name = document.getElementById('prompt-input').value.trim();

    if (name === '') {
        showToast('O nome não pode ser vazio', 'error');
        return;
    }

    let url = '';
    let formData = new FormData();

    if (action === 'create-file' || action === 'create-dir') {
        url = 'api/files.php?action=file_create';
        formData.append('parent_path', targetPath);
        formData.append('name', name);
        formData.append('type', action === 'create-dir' ? 'dir' : 'file');
    } else if (action === 'rename') {
        url = 'api/files.php?action=file_rename';
        formData.append('path', targetPath);
        formData.append('new_name', name);
    }

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeModal('modal-prompt');
            showToast('Operação realizada!', 'success');
            loadLocalFiles();
            if (action === 'create-file') {
                openFile(name, data.path, false);
            }
        } else {
            showToast(data.message || 'Ocorreu um erro', 'error');
        }
    });
}

// Modal open/close actions
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.add('open');
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.remove('open');
}

// Workspace Changer Operations
function loadWorkspaceStatus() {
    fetch('api/user.php?action=status')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('ws-username-input').value = data.username;
            document.getElementById('ws-path-input').value = data.workspace_path;
            document.getElementById('theme-selector').value = data.theme;
            
            // Set current editor theme dynamically
            state.editor.setTheme("ace/theme/" + data.theme);
            
            // Update mobile path label
            const labelEl = document.getElementById('current-ws-path-label');
            if (labelEl) {
                labelEl.innerText = data.workspace_path;
                labelEl.title = data.workspace_path;
            }
            const onlyPathInput = document.getElementById('ws-only-path-input');
            if (onlyPathInput) {
                onlyPathInput.value = data.workspace_path;
            }
        }
    });
}

function openWorkspaceModal() {
    loadWorkspaceStatus();
    openModal('modal-workspace');
}

function saveWorkspaceSettings() {
    const username = document.getElementById('ws-username-input').value.trim();
    const password = document.getElementById('ws-password-input').value;
    const workspacePath = document.getElementById('ws-path-input').value.trim();

    if (username === '' || workspacePath === '') {
        showToast('Usuário e caminho são obrigatórios', 'error');
        return;
    }

    showToast('Salvando configurações...');

    // Save user info
    let uData = new FormData();
    uData.append('username', username);
    if (password !== '') uData.append('password', password);

    fetch('api/user.php?action=update_user', { method: 'POST', body: uData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Save workspace path
            let wData = new FormData();
            wData.append('workspace_path', workspacePath);
            return fetch('api/user.php?action=save_workspace_path', { method: 'POST', body: wData });
        } else {
            throw new Error(data.message || 'Erro ao salvar credenciais');
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeModal('modal-workspace');
            showToast('Configurações salvas! Recarregando workspace...', 'success');
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showToast(data.message || 'Erro ao alterar o caminho do workspace', 'error');
        }
    })
    .catch(err => {
        showToast(err.message, 'error');
    });
}

function openChangePathModal(event) {
    if (event) event.stopPropagation();
    loadWorkspaceStatus();
    openModal('modal-workspace-path');
}

function saveWorkspaceOnlyPath() {
    const path = document.getElementById('ws-only-path-input').value.trim();
    if (path === '') {
        showToast('O caminho não pode ser vazio', 'error');
        return;
    }

    showToast('Alterando pasta do workspace...');
    
    let formData = new FormData();
    formData.append('workspace_path', path);

    fetch('api/user.php?action=save_workspace_path', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeModal('modal-workspace-path');
            showToast('Workspace atualizado com sucesso!', 'success');
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showToast(data.message || 'Erro ao alterar pasta', 'error');
        }
    })
    .catch(err => {
        showToast(err.message, 'error');
    });
}

function changeEditorTheme(themeName) {
    let formData = new FormData();
    formData.append('theme', themeName);

    fetch('api/user.php?action=save_editor_theme', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            state.editor.setTheme("ace/theme/" + themeName);
            showToast('Tema alterado!', 'success');
        }
    });
}

// FTP Operations
function loadFtpConnections() {
    fetch('api/ftp.php?action=ftp_connections_list')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const list = document.getElementById('ftp-connections-list');
            list.innerHTML = '';
            
            if (data.connections.length === 0) {
                list.innerHTML = '<div style="font-size:12px; color:var(--text-muted); padding:4px;">Nenhuma conexão salva</div>';
                return;
            }

            data.connections.forEach(conn => {
                const item = document.createElement('div');
                item.className = 'sidebar-btn';
                item.style.justifyContent = 'space-between';
                item.innerHTML = `
                    <span onclick="connectFtp('${conn.id}', '${conn.name}')" style="display:flex; align-items:center; gap:6px; flex:1;">⚡ ${conn.name}</span>
                    <span onclick="editFtpConnection('${conn.id}', '${conn.name}', '${conn.host}', '${conn.port}', '${conn.username}')" style="cursor:pointer;" title="Editar">⚙️</span>
                `;
                list.appendChild(item);
            });
        }
    });
}

function openFtpModal() {
    document.getElementById('ftp-modal-title').innerText = 'Nova Conexão FTP';
    document.getElementById('ftp-conn-id').value = '';
    document.getElementById('ftp-conn-name').value = '';
    document.getElementById('ftp-conn-host').value = '';
    document.getElementById('ftp-conn-port').value = '21';
    document.getElementById('ftp-conn-user').value = '';
    document.getElementById('ftp-conn-pass').value = '';
    document.getElementById('ftp-btn-delete').style.display = 'none';
    openModal('modal-ftp');
}

function editFtpConnection(id, name, host, port, user) {
    document.getElementById('ftp-modal-title').innerText = 'Editar Conexão FTP';
    document.getElementById('ftp-conn-id').value = id;
    document.getElementById('ftp-conn-name').value = name;
    document.getElementById('ftp-conn-host').value = host;
    document.getElementById('ftp-conn-port').value = port;
    document.getElementById('ftp-conn-user').value = user;
    document.getElementById('ftp-conn-pass').value = '';
    document.getElementById('ftp-btn-delete').style.display = 'block';
    openModal('modal-ftp');
}

function saveFtpConnection() {
    const id = document.getElementById('ftp-conn-id').value;
    const name = document.getElementById('ftp-conn-name').value.trim();
    const host = document.getElementById('ftp-conn-host').value.trim();
    const port = document.getElementById('ftp-conn-port').value;
    const username = document.getElementById('ftp-conn-user').value.trim();
    const password = document.getElementById('ftp-conn-pass').value;

    if (name === '' || host === '' || username === '') {
        showToast('Campos obrigatórios faltando', 'error');
        return;
    }

    showToast('Salvando conexão FTP...');

    let formData = new FormData();
    if (id !== '') formData.append('id', id);
    formData.append('name', name);
    formData.append('host', host);
    formData.append('port', port);
    formData.append('username', username);
    if (password !== '') formData.append('password', password);

    fetch('api/ftp.php?action=ftp_connection_save', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeModal('modal-ftp');
            showToast('Conexão FTP salva!', 'success');
            loadFtpConnections();
        } else {
            showToast(data.message || 'Erro ao salvar conexão FTP', 'error');
        }
    });
}

function deleteFtpConnection() {
    const id = document.getElementById('ftp-conn-id').value;
    if (confirm('Tem certeza que deseja excluir esta conexão FTP?')) {
        let formData = new FormData();
        formData.append('id', id);

        fetch('api/ftp.php?action=ftp_connection_delete', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('modal-ftp');
                showToast('Conexão excluída', 'success');
                loadFtpConnections();
            }
        });
    }
}

function connectFtp(connectionId, name) {
    showToast(`Conectando ao FTP: ${name}...`);
    state.activeFtpConnId = connectionId;
    state.activeFtpPath = '/';

    document.getElementById('ftp-connections-list').classList.add('hidden');
    document.getElementById('ftp-active-conn-name').innerText = `FTP: ${name}`;
    document.getElementById('ftp-file-tree').classList.remove('hidden');

    loadFtpFiles('/');
}

function disconnectFtp() {
    state.activeFtpConnId = null;
    state.activeFtpPath = '/';
    document.getElementById('ftp-file-tree').classList.add('hidden');
    document.getElementById('ftp-connections-list').classList.remove('hidden');
    showToast('Desconectado do FTP');
}

function loadFtpFiles(path) {
    const container = document.getElementById('ftp-tree-container');
    container.innerHTML = '<div style="font-size:12px; color:var(--text-muted); padding:6px 0;">Carregando diretórios remotos...</div>';

    fetch(`api/ftp.php?action=ftp_list&connection_id=${state.activeFtpConnId}&path=${encodeURIComponent(path)}`)
    .then(res => res.json())
    .then(data => {
        container.innerHTML = '';
        if (data.success) {
            // Render basic file tree
            data.items.forEach(item => {
                const node = document.createElement('div');
                node.className = `file-tree-node ${item.is_dir ? 'directory' : 'file'}`;
                let icon = item.is_dir ? '📁' : '📄';
                node.innerHTML = `<span>${icon}</span> <span>${item.name}</span>`;
                
                node.onclick = () => {
                    if (item.is_dir) {
                        loadFtpFiles(item.path);
                    } else {
                        openFile(item.name, item.path, true, state.activeFtpConnId);
                    }
                };
                container.appendChild(node);
            });

            // Back option if not root
            if (path !== '/' && path !== '') {
                const backNode = document.createElement('div');
                backNode.className = 'file-tree-node directory';
                backNode.innerHTML = '<span>🔙</span> <strong>[Subir um nível]</strong>';
                
                // compute parent path
                const parts = path.split('/');
                parts.pop();
                const parentPath = parts.join('/') || '/';
                
                backNode.onclick = () => loadFtpFiles(parentPath);
                container.insertBefore(backNode, container.firstChild);
            }
        } else {
            container.innerHTML = `<div style="font-size:12px; color:var(--accent-error); padding:6px 0;">${data.message || 'Erro de conexão'}</div>`;
        }
    });
}

// Database Explorer Operations
function loadDbConnections() {
    fetch('api/db.php?action=db_connections_list')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const list = document.getElementById('db-connections-list');
            list.innerHTML = '';
            
            if (data.connections.length === 0) {
                list.innerHTML = '<div style="font-size:12px; color:var(--text-muted); padding:4px;">Nenhuma conexão salva</div>';
                return;
            }

            data.connections.forEach(conn => {
                const item = document.createElement('div');
                item.className = 'sidebar-btn';
                item.style.justifyContent = 'space-between';
                item.innerHTML = `
                    <span onclick="connectDb('${conn.id}', '${conn.name}')" style="display:flex; align-items:center; gap:6px; flex:1;">🗄️ ${conn.name}</span>
                    <span onclick="editDbConnection('${conn.id}', '${conn.name}', '${conn.driver}', '${conn.host}', '${conn.port}', '${conn.username}', '${conn.database}')" style="cursor:pointer;" title="Editar">⚙️</span>
                `;
                list.appendChild(item);
            });
        }
    });
}

function openDbModal() {
    document.getElementById('db-modal-title').innerText = 'Nova Conexão Banco de Dados';
    document.getElementById('db-conn-id').value = '';
    document.getElementById('db-conn-name').value = '';
    document.getElementById('db-conn-driver').value = 'mysql';
    document.getElementById('db-conn-host').value = 'localhost';
    document.getElementById('db-conn-port').value = '3306';
    document.getElementById('db-conn-user').value = 'root';
    document.getElementById('db-conn-pass').value = '';
    document.getElementById('db-conn-database').value = '';
    document.getElementById('db-btn-delete').style.display = 'none';
    onDriverChanged();
    openModal('modal-db');
}

function onDriverChanged() {
    const driver = document.getElementById('db-conn-driver').value;
    const hostRow = document.getElementById('db-host-port-row');
    const userRow = document.getElementById('db-user-row');
    const passRow = document.getElementById('db-pass-row');
    const dbLabel = document.getElementById('db-database-label');

    if (driver === 'sqlite') {
        hostRow.style.display = 'none';
        userRow.style.display = 'none';
        passRow.style.display = 'none';
        dbLabel.innerText = 'Caminho do arquivo SQLite (relativo ao workspace)';
    } else {
        hostRow.style.display = 'flex';
        userRow.style.display = 'block';
        passRow.style.display = 'block';
        dbLabel.innerText = 'Banco de Dados Padrão';
        
        // set default port
        document.getElementById('db-conn-port').value = driver === 'pgsql' ? '5432' : '3306';
    }
}

function editDbConnection(id, name, driver, host, port, user, db) {
    document.getElementById('db-modal-title').innerText = 'Editar Conexão Banco';
    document.getElementById('db-conn-id').value = id;
    document.getElementById('db-conn-name').value = name;
    document.getElementById('db-conn-driver').value = driver;
    document.getElementById('db-conn-host').value = host;
    document.getElementById('db-conn-port').value = port;
    document.getElementById('db-conn-user').value = user;
    document.getElementById('db-conn-pass').value = '';
    document.getElementById('db-conn-database').value = db;
    document.getElementById('db-btn-delete').style.display = 'block';
    onDriverChanged();
    openModal('modal-db');
}

function saveDbConnection() {
    const id = document.getElementById('db-conn-id').value;
    const name = document.getElementById('db-conn-name').value.trim();
    const driver = document.getElementById('db-conn-driver').value;
    const host = document.getElementById('db-conn-host').value.trim();
    const port = document.getElementById('db-conn-port').value.trim();
    const username = document.getElementById('db-conn-user').value.trim();
    const password = document.getElementById('db-conn-pass').value;
    const database = document.getElementById('db-conn-database').value.trim();

    if (name === '') {
        showToast('Nome da conexão é obrigatório', 'error');
        return;
    }

    showToast('Salvando conexão do banco...');

    let formData = new FormData();
    if (id !== '') formData.append('id', id);
    formData.append('name', name);
    formData.append('driver', driver);
    formData.append('host', host);
    formData.append('port', port);
    formData.append('username', username);
    if (password !== '') formData.append('password', password);
    formData.append('database', database);

    fetch('api/db.php?action=db_connection_save', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeModal('modal-db');
            showToast('Conexão salva!', 'success');
            loadDbConnections();
        } else {
            showToast(data.message || 'Erro ao salvar conexão', 'error');
        }
    });
}

function deleteDbConnection() {
    const id = document.getElementById('db-conn-id').value;
    if (confirm('Tem certeza que deseja excluir esta conexão de Banco de Dados?')) {
        let formData = new FormData();
        formData.append('id', id);

        fetch('api/db.php?action=db_connection_delete', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('modal-db');
                showToast('Conexão de banco excluída', 'success');
                loadDbConnections();
            }
        });
    }
}

function connectDb(connectionId, name) {
    showToast(`Conectando ao banco de dados: ${name}...`);
    state.activeDbConnId = connectionId;
    state.activeDbName = '';

    document.getElementById('active-db-name').innerText = name;
    
    // open the DB explorer tab in workspace
    openSpecialTab('db', connectionId, `[DB: ${name}]`);

    // Load Databases
    fetch(`api/db.php?action=db_list_databases&connection_id=${connectionId}`)
    .then(res => res.json())
    .then(data => {
        const select = document.getElementById('db-database-select');
        select.innerHTML = '<option value="">Selecione o Banco</option>';
        if (data.success) {
            data.databases.forEach(db => {
                const opt = document.createElement('option');
                opt.value = db;
                opt.innerText = db;
                select.appendChild(opt);
            });
            if (data.databases.length > 0) {
                // select first db by default
                select.value = data.databases[0];
                onDatabaseChanged();
            }
        } else {
            showToast(data.message || 'Erro ao carregar bancos de dados', 'error');
        }
    });
}

function onDatabaseChanged() {
    const db = document.getElementById('db-database-select').value;
    state.activeDbName = db;

    const select = document.getElementById('db-table-select');
    select.innerHTML = '<option value="">Carregando tabelas...</option>';

    fetch(`api/db.php?action=db_list_tables&connection_id=${state.activeDbConnId}&database=${encodeURIComponent(db)}`)
    .then(res => res.json())
    .then(data => {
        select.innerHTML = '<option value="">Selecione a Tabela</option>';
        if (data.success) {
            data.tables.forEach(tbl => {
                const opt = document.createElement('option');
                opt.value = tbl;
                opt.innerText = tbl;
                select.appendChild(opt);
            });
        }
    });
}

function onTableChanged() {
    const table = document.getElementById('db-table-select').value;
    if (table === '') return;

    // generate a quick SELECT command in query window
    document.getElementById('db-sql-input').value = `SELECT * FROM \`${table}\` LIMIT 30;`;
}

function runSql() {
    const sql = document.getElementById('db-sql-input').value.trim();
    if (sql === '') return;

    const statusEl = document.getElementById('db-status');
    statusEl.innerText = 'Executando instrução SQL...';
    
    let formData = new FormData();
    formData.append('connection_id', state.activeDbConnId);
    formData.append('database', state.activeDbName);
    formData.append('sql', sql);

    fetch('api/db.php?action=db_query_execute', {
        method: 'POST',
        body: formData
    })
    .then(res => {
        if (!res.ok) throw new Error("Erro na consulta SQL");
        return res.json();
    })
    .then(data => {
        const table = document.getElementById('db-results-table');
        if (data.success) {
            if (data.is_select) {
                statusEl.innerText = `Sucesso: ${data.rows.length} registros retornados.`;
                
                // Build columns header
                let thead = '<tr>';
                data.columns.forEach(col => {
                    thead += `<th>${col}</th>`;
                });
                thead += '</tr>';
                table.querySelector('thead').innerHTML = thead;

                // Build rows
                let tbody = '';
                data.rows.forEach(row => {
                    tbody += '<tr>';
                    data.columns.forEach(col => {
                        tbody += `<td>${row[col] === null ? '<span style="color:var(--text-muted);">NULL</span>' : htmlEscape(row[col])}</td>`;
                    });
                    tbody += '</tr>';
                });
                if (data.rows.length === 0) {
                    tbody = `<tr><td colspan="${data.columns.length}">Nenhum registro encontrado.</td></tr>`;
                }
                table.querySelector('tbody').innerHTML = tbody;
            } else {
                statusEl.innerText = `Comando executado. Linhas afetadas: ${data.affected_rows}`;
                table.querySelector('thead').innerHTML = '<tr><th>Status</th></tr>';
                table.querySelector('tbody').innerHTML = `<tr><td>Sucesso. Linhas afetadas: ${data.affected_rows}</td></tr>`;
            }
        } else {
            statusEl.innerText = 'Falha na execução';
            table.querySelector('thead').innerHTML = '<tr><th>Erro</th></tr>';
            table.querySelector('tbody').innerHTML = `<tr><td style="color:var(--accent-error);">${htmlEscape(data.message)}</td></tr>`;
        }
    })
    .catch(err => {
        statusEl.innerText = 'Erro de requisição';
        showToast(err.message, 'error');
    });
}

function htmlEscape(str) {
    if (typeof str !== 'string') return String(str);
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// Terminal Operations
function openTerminalTab() {
    openSpecialTab('terminal', 'default', '[Terminal]');
}

function initTerminal() {
    const input = document.getElementById('terminal-cmd-input');
    if (!input) return;

    // Trigger base cmd to get CWD
    runTerminalCommand('');

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const cmd = input.value.trim();
            if (cmd === 'clear') {
                document.getElementById('terminal-output-area').innerText = '';
                input.value = '';
                return;
            }
            if (cmd !== '') {
                state.terminalHistory.push(cmd);
                state.terminalHistoryIdx = state.terminalHistory.length;
            }
            runTerminalCommand(cmd);
            input.value = '';
            hideAutocomplete();
        }
        
        // History Navigation
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (state.terminalHistory.length > 0 && state.terminalHistoryIdx > 0) {
                state.terminalHistoryIdx--;
                input.value = state.terminalHistory[state.terminalHistoryIdx];
            }
        }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (state.terminalHistory.length > 0 && state.terminalHistoryIdx < state.terminalHistory.length - 1) {
                state.terminalHistoryIdx++;
                input.value = state.terminalHistory[state.terminalHistoryIdx];
            } else {
                state.terminalHistoryIdx = state.terminalHistory.length;
                input.value = '';
            }
        }

        // Auto-complete trigger Tab key
        if (e.key === 'Tab') {
            e.preventDefault();
            handleTerminalTabComplete();
        }
    });
}

function runTerminalCommand(cmd) {
    const outputArea = document.getElementById('terminal-output-area');
    if (cmd !== '') {
        outputArea.innerText += `\n$ ${cmd}\n`;
    }

    let formData = new FormData();
    formData.append('cmd', cmd);
    formData.append('terminal_id', 'default');

    fetch('api/terminal.php?action=terminal_cmd', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (cmd !== '') {
                outputArea.innerText += data.output + '\n';
            }
            state.terminalCwd = data.cwd;
            document.getElementById('terminal-path-indicator').innerText = getBasename(data.cwd) + ' $';
            document.getElementById('terminal-path-indicator').title = data.cwd;
            state.autocompleteList = data.autocomplete_list || [];
            
            // Scroll to bottom
            outputArea.scrollTop = outputArea.scrollHeight;
        }
    });
}

function handleTerminalTabComplete() {
    const input = document.getElementById('terminal-cmd-input');
    const value = input.value;
    const words = value.split(' ');
    const lastWord = words.pop();

    if (lastWord === '') return;

    // Filter autocomplete items
    const matches = state.autocompleteList.filter(item => item.toLowerCase().startsWith(lastWord.toLowerCase()));
    
    if (matches.length === 1) {
        words.push(matches[0]);
        input.value = words.join(' ');
    } else if (matches.length > 1) {
        // Render autocomplete floating dropdown
        showAutocomplete(matches, lastWord);
    }
}

function showAutocomplete(matches, query) {
    const dropdown = document.getElementById('terminal-autocomplete');
    dropdown.innerHTML = '';
    dropdown.style.display = 'block';

    matches.forEach(match => {
        const item = document.createElement('div');
        item.className = 'autocomplete-item';
        item.innerText = match;
        item.onclick = () => {
            const input = document.getElementById('terminal-cmd-input');
            const words = input.value.split(' ');
            words.pop(); // remove query
            words.push(match);
            input.value = words.join(' ');
            hideAutocomplete();
            input.focus();
        };
        dropdown.appendChild(item);
    });
}

function hideAutocomplete() {
    document.getElementById('terminal-autocomplete').style.display = 'none';
}

function getBasename(path) {
    const sep = path.indexOf('\\') !== -1 ? '\\' : '/';
    const parts = path.split(sep);
    return parts.pop() || path;
}
