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

        this.init();
    }

    async init() {
        this.bindEvents();
        await this.connect();
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
            this.log('Connecting to daemon socket...', 'ansi-dim');
            const data = await App.request(`/api/client/servers/${this.serverUuid}/websocket`);
            this.token = data.data.token;
            this.socketUrl = data.data.socket;

            this.socket = new WebSocket(this.socketUrl);

            this.socket.onopen = () => {
                this.log('Connected to daemon socket. Authenticating...', 'ansi-dim');
                this.sendEvent('auth', [this.token]);
            };

            this.socket.onmessage = (event) => {
                this.handleMessage(event.data);
            };

            this.socket.onclose = () => {
                this.log('Connection to daemon closed. Reconnecting in 3s...', 'ansi-yellow');
                clearTimeout(this.reconnectTimer);
                this.reconnectTimer = setTimeout(() => this.connect(), 3000);
            };

            this.socket.onerror = () => {
                this.log('WebSocket error encountered.', 'ansi-red');
            };
        } catch (e) {
            this.log(`Failed to initialize WebSocket: ${e.message}`, 'ansi-red');
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
                    this.log('Socket authenticated. Live stream connected.', 'ansi-green');
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
                    // Refresh token
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
        if (!this.statusBadge) return;
        this.statusBadge.textContent = status || 'offline';
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

    appendConsole(text) {
        if (!this.terminalWindow) return;
        const line = document.createElement('div');
        line.innerHTML = this.parseAnsi(text);
        this.terminalWindow.appendChild(line);

        if (this.autoScroll) {
            this.terminalWindow.scrollTop = this.terminalWindow.scrollHeight;
        }
    }

    log(text, cssClass = '') {
        this.appendConsole(`<span class="${cssClass}">[Pterodactyl] ${App.escapeHtml(text)}</span>`);
    }

    parseAnsi(input) {
        if (!input) return '';
        // Escape HTML tags first
        let html = input.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

        // ANSI Color mapping
        const colorMap = {
            30: 'ansi-black', 31: 'ansi-red', 32: 'ansi-green', 33: 'ansi-yellow',
            34: 'ansi-blue', 35: 'ansi-magenta', 36: 'ansi-cyan', 37: 'ansi-white',
            90: 'ansi-bright-black', 91: 'ansi-bright-red', 92: 'ansi-bright-green', 93: 'ansi-bright-yellow',
            94: 'ansi-bright-blue', 95: 'ansi-bright-magenta', 96: 'ansi-bright-cyan', 97: 'ansi-bright-white',
            40: 'ansi-bg-black', 41: 'ansi-bg-red', 42: 'ansi-bg-green', 43: 'ansi-bg-yellow',
            44: 'ansi-bg-blue', 45: 'ansi-bg-magenta', 46: 'ansi-bg-cyan', 47: 'ansi-bg-white',
            1: 'ansi-bold', 2: 'ansi-dim', 3: 'ansi-italic', 4: 'ansi-underline'
        };

        // Replace ANSI sequences
        return html.replace(/\u001b\[([0-9;]+)m/g, (match, codes) => {
            const parts = codes.split(';');
            if (parts.includes('0')) {
                return '</span>';
            }
            const classes = parts.map(c => colorMap[c] || '').filter(Boolean).join(' ');
            return classes ? `<span class="${classes}">` : '';
        });
    }

    sendCommand() {
        const cmd = this.commandInput.value.trim();
        if (!cmd) return;

        // Push to history
        this.history.push(cmd);
        if (this.history.length > 50) this.history.shift();
        localStorage.setItem(`cmd_history_${this.serverUuid}`, JSON.stringify(this.history));
        this.historyIndex = this.history.length;

        // Send via WebSocket or fallback to HTTP
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
