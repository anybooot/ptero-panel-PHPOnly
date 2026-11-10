/**
 * Pterodactyl Panel - File Manager JavaScript Client
 */

class FileManager {
    constructor(serverUuid, initialDirectory = '/') {
        this.serverUuid = serverUuid;
        this.currentDirectory = initialDirectory || '/';
        this.tbody = document.getElementById('files-table-body');
        this.breadcrumbs = document.getElementById('files-breadcrumbs');
        this.uploadInput = document.getElementById('file-upload-input');
        this.progressBar = document.getElementById('upload-progress-bar');
        this.newFolderForm = document.getElementById('new-folder-form');

        this.init();
    }

    init() {
        this.bindEvents();
        this.loadDirectory(this.currentDirectory);
    }

    bindEvents() {
        if (this.uploadInput) {
            this.uploadInput.addEventListener('change', () => this.handleUpload());
        }

        if (this.newFolderForm) {
            this.newFolderForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.createDirectory();
            });
        }
    }

    async loadDirectory(dir) {
        this.currentDirectory = dir || '/';
        this.renderBreadcrumbs();
        
        if (this.tbody) {
            this.tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">Loading directory...</td></tr>';
        }

        try {
            const res = await App.request(`/api/client/servers/${this.serverUuid}/files/list?directory=${encodeURIComponent(this.currentDirectory)}`);
            this.renderFiles(res.data || []);
        } catch (e) {
            if (this.tbody) {
                this.tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: #ef4444; padding: 24px;">Failed to load files: ${App.escapeHtml(e.message)}</td></tr>`;
            }
        }
    }

    renderBreadcrumbs() {
        if (!this.breadcrumbs) return;

        const parts = this.currentDirectory.split('/').filter(Boolean);
        let html = `<a href="javascript:void(0)" onclick="window.fileManager.loadDirectory('/')" style="color: #60a5fa; font-weight: 600;">/home/container</a>`;

        let cumPath = '';
        parts.forEach((p, idx) => {
            cumPath += '/' + p;
            const isLast = idx === parts.length - 1;
            if (isLast) {
                html += ` <span style="color: var(--text-muted);">/</span> <span style="color: #fff; font-weight: 600;">${App.escapeHtml(p)}</span>`;
            } else {
                const targetPath = cumPath;
                html += ` <span style="color: var(--text-muted);">/</span> <a href="javascript:void(0)" onclick="window.fileManager.loadDirectory('${targetPath}')" style="color: #60a5fa;">${App.escapeHtml(p)}</a>`;
            }
        });

        this.breadcrumbs.innerHTML = html;
    }

    renderFiles(files) {
        if (!this.tbody) return;

        if (files.length === 0) {
            this.tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">This directory is empty.</td></tr>';
            return;
        }

        // Sort: directories first, then files alphabetically
        files.sort((a, b) => {
            if (a.is_file === b.is_file) {
                return a.name.localeCompare(b.name);
            }
            return a.is_file ? 1 : -1;
        });

        let rows = '';
        files.forEach(f => {
            const isDir = !f.is_file;
            const fullPath = (this.currentDirectory === '/' ? '' : this.currentDirectory) + '/' + f.name;
            const icon = isDir ? '📁' : '📄';
            const sizeStr = isDir ? '-' : this.formatBytes(f.size || 0);
            const dateStr = f.modified ? new Date(f.modified).toLocaleString() : '-';

            rows += `
                <tr>
                    <td style="text-align: center;"><input type="checkbox" class="file-select-box" value="${App.escapeHtml(f.name)}"></td>
                    <td>
                        <span style="margin-right: 8px;">${icon}</span>
                        ${isDir
                            ? `<a href="javascript:void(0)" onclick="window.fileManager.loadDirectory('${fullPath}')" style="font-weight: 600; color: #fff;">${App.escapeHtml(f.name)}</a>`
                            : `<a href="/server/${this.serverUuid.substring(0, 8)}/files/edit?file=${encodeURIComponent(fullPath)}" style="color: #cbd5e1;">${App.escapeHtml(f.name)}</a>`
                        }
                    </td>
                    <td style="font-family: var(--font-mono); font-size: 12px; color: var(--text-secondary);">${sizeStr}</td>
                    <td style="font-size: 12px; color: var(--text-muted);">${dateStr}</td>
                    <td style="text-align: right;">
                        ${!isDir ? `<a href="javascript:void(0)" onclick="window.fileManager.downloadFile('${fullPath}')" class="btn btn-secondary btn-sm" style="padding: 2px 8px;">Download</a>` : ''}
                        <button class="btn btn-danger btn-sm" style="padding: 2px 8px;" onclick="window.fileManager.deleteFile('${App.escapeHtml(f.name)}')">Delete</button>
                    </td>
                </tr>
            `;
        });

        this.tbody.innerHTML = rows;
    }

    async createDirectory() {
        const input = document.getElementById('new-folder-name');
        const name = input ? input.value.trim() : '';
        if (!name) return;

        try {
            await App.request(`/api/client/servers/${this.serverUuid}/files/create-folder`, {
                method: 'POST',
                body: {
                    root: this.currentDirectory,
                    name: name
                }
            });
            App.closeModal('modal-new-folder');
            input.value = '';
            App.toast(`Directory '${name}' created.`, 'success');
            this.loadDirectory(this.currentDirectory);
        } catch (e) {
            App.toast('Failed to create folder: ' + e.message, 'danger');
        }
    }

    async deleteFile(name) {
        if (!confirm(`Are you sure you want to delete '${name}'?`)) return;

        try {
            await App.request(`/api/client/servers/${this.serverUuid}/files/delete`, {
                method: 'POST',
                body: {
                    root: this.currentDirectory,
                    files: [name]
                }
            });
            App.toast(`Deleted '${name}'.`, 'success');
            this.loadDirectory(this.currentDirectory);
        } catch (e) {
            App.toast('Failed to delete: ' + e.message, 'danger');
        }
    }

    async downloadFile(filePath) {
        try {
            const res = await App.request(`/api/client/servers/${this.serverUuid}/files/download?file=${encodeURIComponent(filePath)}`);
            if (res.url) {
                window.location.href = res.url;
            }
        } catch (e) {
            App.toast('Download error: ' + e.message, 'danger');
        }
    }

    async handleUpload() {
        const files = this.uploadInput.files;
        if (!files || files.length === 0) return;

        if (this.progressBar) this.progressBar.style.width = '10%';

        try {
            const urlData = await App.request(`/api/client/servers/${this.serverUuid}/files/upload`);
            const uploadUrl = urlData.url;

            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const formData = new FormData();
                formData.append('files', file);

                const xhr = new XMLHttpRequest();
                const targetUrl = `${uploadUrl}&directory=${encodeURIComponent(this.currentDirectory)}`;

                await new Promise((resolve, reject) => {
                    xhr.upload.onprogress = (e) => {
                        if (e.lengthComputable && this.progressBar) {
                            const percent = Math.round((e.loaded / e.total) * 100);
                            this.progressBar.style.width = `${percent}%`;
                        }
                    };
                    xhr.onload = () => {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            resolve();
                        } else {
                            reject(new Error(`Upload failed with status ${xhr.status}`));
                        }
                    };
                    xhr.onerror = () => reject(new Error('Upload network error'));
                    xhr.open('POST', targetUrl);
                    xhr.send(formData);
                });
            }

            App.toast('Upload complete!', 'success');
            this.uploadInput.value = '';
            if (this.progressBar) this.progressBar.style.width = '0%';
            this.loadDirectory(this.currentDirectory);
        } catch (e) {
            App.toast('Upload error: ' + e.message, 'danger');
            if (this.progressBar) this.progressBar.style.width = '0%';
        }
    }

    formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
}

window.FileManager = FileManager;
