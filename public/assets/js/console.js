/**
 * Pterodactyl Panel - Console & Wings WebSocket Client
 */

class ServerConsole {
    constructor(serverUuid, serverId) {
        this.serverUuid = serverUuid;
        this.serverId = serverId;
        this.socket = null;
        this.token = null;
        this.socketUrl = null;
        this.terminalWindow = document.getElementById('terminal-window');
        this.commandInput = document.getElementById('terminal-command-input');
        this.statusBadge = document.getElementById('server-status-badge');
        this.history = JSON.parse(localStorage.getItem(`cmd_history_${this.serverUuid}`) || '[]');
        this.historyIndex = this.history.length;
        this.reconnectTimer = null;
        this.autoScroll = true;
        this.storageKey = `server_console_buffer_${this.serverUuid}`;

        this.init();
    }

    async init() {
        this.restoreBuffer();
        this.bindEvents();
        await this.connect();
    }

    restoreBuffer() {
        if (!this.terminalWindow) return;
        try {
            const saved = sessionStorage.getItem(this.storageKey);
            if (saved) {
                // Clean out any historical connection info lines
                const cleaned = saved.replace(/<div[^>]*>.*?Connecting to the node.*?<\/div>/gi, '')
                                     .replace(/<div[^>]*>.*?Connected to the node.*?<\/div>/gi, '');
                this.terminalWindow.innerHTML = cleaned;
                this.terminalWindow.scrollTop = this.terminalWindow.scrollHeight;
            }
        } catch (e) {}
    }

    persistBuffer() {
        if (!this.terminalWindow) return;
        try {
            // Cap at 400 lines to avoid sessionStorage limits
            while (this.terminalWindow.children.length > 400) {
                this.terminalWindow.removeChild(this.terminalWindow.firstChild);
            }
            sessionStorage.setItem(this.storageKey, this.terminalWindow.innerHTML);
        } catch (e) {}
    }

    bindEvents() {
        // Auto-scroll toggle on user manual scroll
        if (this.terminalWindow) {
            this.terminalWindow.addEventListener('scroll', () => {
                const threshold = 30;
                const atBottom = this.terminalWindow.scrollHeight - this.terminalWindow.scrollTop - this.terminalWindow.clientHeight <= threshold;
                this.autoScroll = atBottom;
            });
        }

        // Terminal command input
        if (this.commandInput) {
            this.commandInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.sendCommand();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (this.historyIndex > 0) {
                        this.historyIndex--;
                        this.commandInput.value = this.history[this.historyIndex] || '';
                    }
                } else if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (this.historyIndex < this.history.length - 1) {
                        this.historyIndex++;
                        this.commandInput.value = this.history[this.historyIndex] || '';
                    } else {
                        this.historyIndex = this.history.length;
                        this.commandInput.value = '';
                    }
                }
            });
        }

        // Power buttons
        document.querySelectorAll('[data-power-action]').forEach(btn => {
            btn.addEventListener('click', () => {
                const action = btn.getAttribute('data-power-action');
                this.sendPower(action);
            });
        });
    }

    async connect() {
        try {
            const data = await App.request(`/api/client/servers/${this.serverUuid}/websocket`);
            this.token = data.data.token;
            this.socketUrl = data.data.socket;

            this.socket = new WebSocket(this.socketUrl);

            this.socket.onopen = () => {
                this.sendEvent('auth', [this.token]);
            };

            this.socket.onmessage = (event) => {
                this.handleMessage(event.data);
            };

            this.socket.onclose = () => {
                clearTimeout(this.reconnectTimer);
                this.reconnectTimer = setTimeout(() => this.connect(), 3000);
            };

            this.socket.onerror = () => {
                this.log('Unable to reach the node. Please ensure the Wings service is running.', 'ansi-red');
            };
        } catch (e) {
            this.log(`Could not connect to the node: ${e.message.includes('403') ? 'Access Denied' : 'Node is currently unavailable'}`, 'ansi-red');
            clearTimeout(this.reconnectTimer);
            this.reconnectTimer = setTimeout(() => this.connect(), 5000);
        }
    }

    sendEvent(event, args = []) {
        if (this.socket && this.socket.readyState === WebSocket.OPEN) {
            this.socket.send(JSON.stringify({ event, args }));
        }
    }

    handleMessage(raw) {
        try {
            const data = JSON.parse(raw);
            const event = data.event;
            const args = data.args || [];

            switch (event) {
                case 'auth success':
                    this.sendEvent('send logs');
                    this.sendEvent('send stats');
                    break;

                case 'status':
                    this.updateStatus(args[0]);
                    break;

                case 'console output':
                    this.appendConsole(args[0]);
                    break;

                case 'stats':
                    if (window.ServerStats && typeof window.ServerStats.update === 'function') {
                        try {
                            const statsObj = typeof args[0] === 'string' ? JSON.parse(args[0]) : args[0];
                            window.ServerStats.update(statsObj);
                        } catch (e) {}
                    }
                    break;

                case 'token expiring':
                    App.request(`/api/client/servers/${this.serverUuid}/websocket`).then(res => {
                        this.sendEvent('auth', [res.data.token]);
                    });
                    break;

                case 'token expired':
                    this.connect();
                    break;
            }
        } catch (e) {}
    }

    updateStatus(status) {
        if (!status) status = 'offline';
        if (this.statusBadge) {
            this.statusBadge.textContent = status;
            this.statusBadge.className = 'badge';

            if (status === 'running') {
                this.statusBadge.classList.add('badge-success');
            } else if (status === 'starting') {
                this.statusBadge.classList.add('badge-warning');
            } else if (status === 'stopping') {
                this.statusBadge.classList.add('badge-danger');
            } else {
                this.statusBadge.classList.add('badge-secondary');
            }
        }

        // Also update any status dots if present
        const statusDots = document.querySelectorAll('#stat-status-dot, .stat-status-dot, .status-pulse-dot');
        const dotColor = status === 'running' ? '#10b981' : (status === 'starting' ? '#f59e0b' : (status === 'stopping' ? '#ef4444' : '#64748b'));
        statusDots.forEach(dot => {
            dot.style.backgroundColor = dotColor;
        });

        // Notify backend to update database status so server list stays in sync
        try {
            App.request(`/api/client/servers/${this.serverUuid}/status`, {
                method: 'POST',
                body: { status }
            }).catch(() => {});
        } catch (e) {}
    }

    appendConsole(text) {
        if (!this.terminalWindow || text === undefined || text === null) return;
        const lines = String(text).split(/\r?\n/);
        for (let i = 0; i < lines.length; i++) {
            const rawLine = lines[i];
            if (rawLine === '' && i === lines.length - 1 && lines.length > 1) continue;
            const line = document.createElement('div');
            line.className = 'terminal-line';
            line.innerHTML = this.parseAnsiLine(rawLine);
            this.terminalWindow.appendChild(line);
        }

        if (this.autoScroll) {
            this.terminalWindow.scrollTop = this.terminalWindow.scrollHeight;
        }
        this.persistBuffer();
    }

    log(text, cssClass = '') {
        if (!this.terminalWindow) return;
        const line = document.createElement('div');
        line.className = 'terminal-line terminal-system-line';

        const prefix = document.createElement('span');
        prefix.className = 'terminal-system-badge';
        prefix.textContent = '[' + (window.PterodactylAppName || 'Pterodactyl') + '] ';
        line.appendChild(prefix);

        const content = document.createElement('span');
        if (cssClass) {
            content.className = cssClass;
        }
        content.textContent = text;
        line.appendChild(content);

        this.terminalWindow.appendChild(line);

        if (this.autoScroll) {
            this.terminalWindow.scrollTop = this.terminalWindow.scrollHeight;
        }
        this.persistBuffer();
    }

    parseAnsiLine(rawLine) {
        if (!rawLine) return '';

        // 1. Strip carriage returns, leading [m / \x1b[m / stray spaces
        let line = rawLine.replace(/\r+/g, '');
        line = line.replace(/^\s*(?:\u001b|\x1b)?\[m\s*/, '');
        line = line.replace(/(?:\u001b|\x1b)?\[m\s*(?=\[|\b)/g, '');
        line = line.replace(/[\u001b\x1b](?!\[)/g, '');

        // 2. HTML escape
        let html = line.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

        // 3. Minecraft section sign color codes (§0 - §f, §r, §l, etc.)
        const mcColorMap = {
            '0': 'ansi-black', '1': 'ansi-blue', '2': 'ansi-green', '3': 'ansi-cyan',
            '4': 'ansi-red', '5': 'ansi-magenta', '6': 'ansi-yellow', '7': 'ansi-white',
            '8': 'ansi-bright-black', '9': 'ansi-bright-blue', 'a': 'ansi-bright-green',
            'b': 'ansi-bright-cyan', 'c': 'ansi-bright-red', 'd': 'ansi-bright-magenta',
            'e': 'ansi-bright-yellow', 'f': 'ansi-bright-white', 'l': 'ansi-bold',
            'o': 'ansi-italic', 'n': 'ansi-underline', 'r': 'reset'
        };
        html = html.replace(/§([0-9a-fk-or])/gi, (m, code) => {
            const c = code.toLowerCase();
            if (c === 'r') return '</span>';
            const cls = mcColorMap[c];
            return cls ? `<span class="${cls}">` : '';
        });

        // 4. ANSI 16/256-color map
        const colorMap = {
            30: 'ansi-black', 31: 'ansi-red', 32: 'ansi-green', 33: 'ansi-yellow',
            34: 'ansi-blue', 35: 'ansi-magenta', 36: 'ansi-cyan', 37: 'ansi-white',
            90: 'ansi-bright-black', 91: 'ansi-bright-red', 92: 'ansi-bright-green', 93: 'ansi-bright-yellow',
            94: 'ansi-bright-blue', 95: 'ansi-bright-magenta', 96: 'ansi-bright-cyan', 97: 'ansi-bright-white',
            40: 'ansi-bg-black', 41: 'ansi-bg-red', 42: 'ansi-bg-green', 43: 'ansi-bg-yellow',
            44: 'ansi-bg-blue', 45: 'ansi-bg-magenta', 46: 'ansi-bg-cyan', 47: 'ansi-bg-white',
            1: 'ansi-bold', 2: 'ansi-dim', 3: 'ansi-italic', 4: 'ansi-underline'
        };

        // Note: * instead of + ensures \u001b[m (empty codes) matches and acts as reset!
        html = html.replace(/(?:\u001b|\x1b)\[([0-9;]*)m/g, (match, codes) => {
            if (!codes || codes === '0' || codes === '') {
                return '</span>';
            }
            const parts = codes.split(';');
            if (parts.includes('0')) {
                return '</span>';
            }
            const classes = parts.map(c => colorMap[c] || '').filter(Boolean).join(' ');
            return classes ? `<span class="${classes}">` : '';
        });

        // 5. Clean up any remaining mangled ANSI artifacts (e.g. stray [0m, [33m)
        html = html.replace(/(?:^|\s+)\[[0-9;]*m\s*/g, ' ');

        // 6. Intelligent log level styling: if line contains WARN/ERROR or Paper warning banner
        const plain = html.replace(/<[^>]+>/g, '').trim();
        const isWarn = /\b(?:WARN|WARNING)\b/i.test(plain) || /^\*{10,}$/.test(plain);
        const isError = /\b(?:ERROR|FATAL|Exception|Severe)\b/i.test(plain);

        if (isWarn) {
            if (!html.includes('ansi-yellow') && !html.includes('ansi-bright-yellow')) {
                html = `<span class="ansi-yellow">${html}</span>`;
            }
        } else if (isError) {
            if (!html.includes('ansi-red') && !html.includes('ansi-bright-red')) {
                html = `<span class="ansi-red">${html}</span>`;
            }
        }

        // 7. Make URLs clickable links
        html = html.replace(/(https?:\/\/[^\s<"']+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer" style="color: #cbd5e1; text-decoration: underline;">$1</a>');

        return html;
    }

    parseAnsi(input) {
        if (!input) return '';
        return input.split(/\r?\n/).map(l => this.parseAnsiLine(l)).join('\n');
    }

    sendCommand() {
        const cmd = this.commandInput.value.trim();
        if (!cmd) return;

        this.history.push(cmd);
        if (this.history.length > 50) this.history.shift();
        localStorage.setItem(`cmd_history_${this.serverUuid}`, JSON.stringify(this.history));
        this.historyIndex = this.history.length;

        if (this.socket && this.socket.readyState === WebSocket.OPEN) {
            this.sendEvent('send command', [cmd]);
        } else {
            App.request(`/api/client/servers/${this.serverUuid}/command`, {
                method: 'POST',
                body: { command: cmd }
            }).catch(e => App.toast(e.message, 'danger'));
        }

        this.commandInput.value = '';
    }

    sendPower(action) {
        if (this.socket && this.socket.readyState === WebSocket.OPEN) {
            this.sendEvent('set state', [action]);
            App.toast(`Signal '${action}' sent to server.`, 'info');
        } else {
            App.request(`/api/client/servers/${this.serverUuid}/power`, {
                method: 'POST',
                body: { action }
            }).then(() => {
                App.toast(`Signal '${action}' sent to server.`, 'info');
            }).catch(e => {
                App.toast(e.message, 'danger');
            });
        }
    }
}

window.ServerConsole = ServerConsole;
