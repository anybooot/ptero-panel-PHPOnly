/**
 * Pterodactyl Panel - Modern File Manager Client
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
        this.selectAllCheckbox = document.getElementById('select-all-files');
        this.bulkToolbar = document.getElementById('files-bulk-actions');
        this.selectedCountEl = document.getElementById('selected-files-count');
        this.btnBulkArchive = document.getElementById('btn-bulk-archive');
        this.btnBulkDelete = document.getElementById('btn-bulk-delete');

        // Modals
        this.renameForm = document.getElementById('rename-file-form');
        this.chmodForm = document.getElementById('chmod-file-form');

        this.init();
    }

    init() {
        this.bindEvents();
        this.bindChmodHelpers();
        const urlParams = new URLSearchParams(window.location.search);
        const queryDir = urlParams.get('directory');
        if (queryDir) {
            this.currentDirectory = queryDir;
        } else if (this.currentDirectory === '/') {
            const savedDir = sessionStorage.getItem('ptero_last_dir_' + this.serverUuid);
            if (savedDir && savedDir !== '/') {
                this.currentDirectory = savedDir;
            }
        }
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

        if (this.selectAllCheckbox) {
            this.selectAllCheckbox.addEventListener('change', () => {
                const checked = this.selectAllCheckbox.checked;
                const boxes = document.querySelectorAll('.file-select-box');
                boxes.forEach(b => b.checked = checked);
                this.updateBulkToolbar();
            });
        }

        if (this.btnBulkArchive) {
            this.btnBulkArchive.addEventListener('click', () => this.archiveSelected());
        }

        if (this.btnBulkDelete) {
            this.btnBulkDelete.addEventListener('click', () => this.deleteSelected());
        }

        window.addEventListener('popstate', () => {
            const params = new URLSearchParams(window.location.search);
            const dir = params.get('directory') || '/';
            if (dir !== this.currentDirectory) {
                this.loadDirectory(dir);
            }
        });

        const btnDeselect = document.getElementById('btn-bulk-deselect');
        if (btnDeselect) {
            btnDeselect.addEventListener('click', () => {
                document.querySelectorAll('.file-select-box').forEach(b => b.checked = false);
                if (this.selectAllCheckbox) this.selectAllCheckbox.checked = false;
                this.updateBulkToolbar();
            });
        }

        if (this.renameForm) {
            this.renameForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.executeRename();
            });
        }

        if (this.chmodForm) {
            this.chmodForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.executeChmod();
            });
        }

        // Close file action dropdowns on outside click or window scroll
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.file-actions-dropdown')) {
                document.querySelectorAll('.file-dropdown-menu').forEach(m => m.style.display = 'none');
            }
        });

        window.addEventListener('scroll', () => {
            document.querySelectorAll('.file-dropdown-menu').forEach(m => m.style.display = 'none');
        }, { passive: true });

        window.addEventListener('resize', () => {
            document.querySelectorAll('.file-dropdown-menu').forEach(m => m.style.display = 'none');
        }, { passive: true });
    }

    bindChmodHelpers() {
        const modeInput = document.getElementById('chmod-mode-input');
        if (!modeInput) return;

        const checks = {
            ur: document.getElementById('perm-ur'),
            uw: document.getElementById('perm-uw'),
            ux: document.getElementById('perm-ux'),
            gr: document.getElementById('perm-gr'),
            gw: document.getElementById('perm-gw'),
            gx: document.getElementById('perm-gx'),
            or: document.getElementById('perm-or'),
            ow: document.getElementById('perm-ow'),
            ox: document.getElementById('perm-ox')
        };

        const updateOctalFromChecks = () => {
            let u = (checks.ur.checked ? 4 : 0) + (checks.uw.checked ? 2 : 0) + (checks.ux.checked ? 1 : 0);
            let g = (checks.gr.checked ? 4 : 0) + (checks.gw.checked ? 2 : 0) + (checks.gx.checked ? 1 : 0);
            let o = (checks.or.checked ? 4 : 0) + (checks.ow.checked ? 2 : 0) + (checks.ox.checked ? 1 : 0);
            modeInput.value = `0${u}${g}${o}`;
        };

        const updateChecksFromOctal = () => {
            let val = modeInput.value.replace(/^0+/, '');
            while (val.length < 3) val = '0' + val;
            val = val.slice(-3);

            const u = parseInt(val[0], 10) || 0;
            const g = parseInt(val[1], 10) || 0;
            const o = parseInt(val[2], 10) || 0;

            checks.ur.checked = (u & 4) !== 0;
            checks.uw.checked = (u & 2) !== 0;
            checks.ux.checked = (u & 1) !== 0;

            checks.gr.checked = (g & 4) !== 0;
            checks.gw.checked = (g & 2) !== 0;
            checks.gx.checked = (g & 1) !== 0;

            checks.or.checked = (o & 4) !== 0;
            checks.ow.checked = (o & 2) !== 0;
            checks.ox.checked = (o & 1) !== 0;
        };

        Object.values(checks).forEach(cb => {
            if (cb) cb.addEventListener('change', updateOctalFromChecks);
        });

        modeInput.addEventListener('input', updateChecksFromOctal);
    }

    async loadDirectory(dir) {
        this.currentDirectory = dir || '/';
        try {
            sessionStorage.setItem('ptero_last_dir_' + this.serverUuid, this.currentDirectory);
        } catch (e) {}

        try {
            const url = new URL(window.location.href);
            if (this.currentDirectory && this.currentDirectory !== '/') {
                url.searchParams.set('directory', this.currentDirectory);
            } else {
                url.searchParams.delete('directory');
            }
            window.history.replaceState({ directory: this.currentDirectory }, '', url.toString());
        } catch (e) {}

        const newFileBtn = document.getElementById('btn-new-file');
        if (newFileBtn) {
            newFileBtn.href = `/server/${this.serverUuid.substring(0, 8)}/files/edit` + (this.currentDirectory !== '/' ? `?directory=${encodeURIComponent(this.currentDirectory)}` : '');
        }

        this.renderBreadcrumbs();
        if (this.selectAllCheckbox) this.selectAllCheckbox.checked = false;
        this.updateBulkToolbar();

        if (this.tbody) {
            this.tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">Loading directory contents...</td></tr>';
        }

        try {
            const res = await App.request(`/api/client/servers/${this.serverUuid}/files/list?directory=${encodeURIComponent(this.currentDirectory)}`).catch(() => ({ error: 'Unable to reach the node.' }));
            if (res && res.error) {
                if (this.tbody) {
                    this.tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: #ef4444; padding: 36px 20px; font-weight: 600; font-size: 14px;">Unable to reach the node.</td></tr>';
                }
                return;
            }
            this.renderFiles(res.data || []);
        } catch (e) {
            if (this.tbody) {
                this.tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: #ef4444; padding: 36px 20px; font-weight: 600; font-size: 14px;">Unable to reach the node.</td></tr>';
            }
        }
    }

    renderBreadcrumbs() {
        if (!this.breadcrumbs) return;

        const parts = this.currentDirectory.split('/').filter(Boolean);
        let html = `<a href="javascript:void(0)" onclick="window.fileManager.loadDirectory('/')" style="color: #ffffff; font-weight: 600;">/home/container</a>`;

        let cumPath = '';
        parts.forEach((p, idx) => {
            cumPath += '/' + p;
            const isLast = idx === parts.length - 1;
            if (isLast) {
                html += ` <span style="color: var(--text-muted);">/</span> <span style="color: #eceff4; font-weight: 600;">${App.escapeHtml(p)}</span>`;
            } else {
                const targetPath = cumPath;
                html += ` <span style="color: var(--text-muted);">/</span> <a href="javascript:void(0)" onclick="window.fileManager.loadDirectory('${targetPath}')" style="color: #cbd5e1;">${App.escapeHtml(p)}</a>`;
            }
        });

        this.breadcrumbs.innerHTML = html;
    }

    checkIsDir(f) {
        const item = f.attributes || f;
        if (item.directory !== undefined) return Boolean(item.directory);
        if (item.is_directory !== undefined) return Boolean(item.is_directory);
        if (item.is_file !== undefined) return !item.is_file;
        if (item.file !== undefined) return !item.file;
        if (item.mode && typeof item.mode === 'string') return item.mode.startsWith('d');
        if (item.mimetype && item.mimetype === 'inode/directory') return true;
        return false;
    }

    renderFiles(files) {
        if (!this.tbody) return;

        if (files.length === 0) {
            this.tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 28px;">This directory is empty.</td></tr>';
            return;
        }

        // Sort: directories first, then alphabetically
        files.sort((a, b) => {
            const aItem = a.attributes || a;
            const bItem = b.attributes || b;
            const aDir = this.checkIsDir(aItem);
            const bDir = this.checkIsDir(bItem);
            if (aDir === bDir) {
                return (aItem.name || '').localeCompare(bItem.name || '');
            }
            return aDir ? -1 : 1;
        });

        let rows = '';
        files.forEach((f, idx) => {
            const item = f.attributes || f;
            const isDir = this.checkIsDir(item);
            const fileName = item.name || '';
            const fullPath = (this.currentDirectory === '/' ? '' : this.currentDirectory) + '/' + fileName;
            const icon = isDir ? '📁' : '📄';
            const sizeStr = isDir ? '-' : this.formatBytes(item.size || 0);
            const rawDate = item.modified || item.modified_at || item.created || item.created_at;
            const dateStr = rawDate ? new Date(rawDate).toLocaleString() : '-';
            const rawMode = item.mode || (isDir ? '0755' : '0644');

            const fp = window.filePermissions || { canCreate: true, canReadContent: true, canUpdate: true, canDelete: true, canArchive: true };
            let actionItems = '';
            if (fp.canUpdate) {
                actionItems += `<a href="javascript:void(0)" onclick="window.fileManager.openRenameModal('${App.escapeHtml(fileName)}')" style="display: block; padding: 8px 14px; font-size: 12.5px; color: #eceff4; text-decoration: none;">Rename</a>`;
                actionItems += `<a href="javascript:void(0)" onclick="window.fileManager.openChmodModal('${App.escapeHtml(fileName)}', '${App.escapeHtml(rawMode)}')" style="display: block; padding: 8px 14px; font-size: 12.5px; color: #eceff4; text-decoration: none;">Permissions</a>`;
            }
            if (!isDir && fp.canReadContent) {
                actionItems += `<a href="javascript:void(0)" onclick="window.fileManager.downloadFile('${fullPath}')" style="display: block; padding: 8px 14px; font-size: 12.5px; color: #eceff4; text-decoration: none;">Download</a>`;
            }
            if (fp.canDelete) {
                if (actionItems) actionItems += `<div style="height: 1px; background: rgba(255,255,255,0.06);"></div>`;
                actionItems += `<a href="javascript:void(0)" onclick="window.fileManager.deleteFile('${App.escapeHtml(fileName)}')" style="display: block; padding: 8px 14px; font-size: 12.5px; color: #f87171; text-decoration: none;">Delete</a>`;
            }

            rows += `
                <tr class="file-table-row">
                    <td style="text-align: center;">
                        <input type="checkbox" class="file-select-box" value="${App.escapeHtml(fileName)}" style="cursor: pointer;">
                    </td>
                    <td>
                        <span style="margin-right: 8px;">${icon}</span>
                        ${isDir
                            ? `<a href="javascript:void(0)" onclick="window.fileManager.loadDirectory('${fullPath}')" style="font-weight: 600; color: #eceff4;">${App.escapeHtml(fileName)}</a>`
                            : (fp.canReadContent
                                ? `<a href="/server/${this.serverUuid.substring(0, 8)}/files/edit?file=${encodeURIComponent(fullPath)}&directory=${encodeURIComponent(this.currentDirectory)}" style="color: #cbd5e1;">${App.escapeHtml(fileName)}</a>`
                                : `<span style="color: #cbd5e1;">${App.escapeHtml(fileName)}</span>`)
                        }
                    </td>
                    <td style="font-family: var(--font-mono); font-size: 12px; color: var(--text-secondary);">${sizeStr}</td>
                    <td style="font-size: 12px; color: var(--text-muted);">${dateStr}</td>
                    <td style="text-align: right; position: relative;">
                        ${actionItems ? `
                        <div class="file-actions-dropdown" style="display: inline-block; position: relative;">
                            <button type="button" class="btn btn-secondary btn-sm file-dropdown-btn" onclick="window.fileManager.toggleDropdown(event, 'drop-${idx}')" style="padding: 2px 8px; font-weight: 700; font-size: 14px; line-height: 1;">&#8942;</button>
                            <div id="drop-${idx}" class="file-dropdown-menu" style="display: none; position: absolute; right: 0; top: 100%; margin-top: 4px; background: #181920; border: 1px solid var(--border-color); border-radius: 8px; min-width: 140px; z-index: 50; box-shadow: 0 10px 20px rgba(0,0,0,0.5); text-align: left; overflow: hidden;">
                                ${actionItems}
                            </div>
                        </div>` : ''}
                    </td>
                </tr>
            `;
        });

        this.tbody.innerHTML = rows;

        // Re-attach checkbox change events
        const boxes = document.querySelectorAll('.file-select-box');
        boxes.forEach(b => {
            b.addEventListener('change', () => this.updateBulkToolbar());
        });
    }

    toggleDropdown(e, dropdownId) {
        e.stopPropagation();
        const btn = e.currentTarget || e.target.closest('button');
        const menu = document.getElementById(dropdownId);
        if (!menu) return;

        const isShown = menu.style.display === 'block';

        // Close all menus first
        document.querySelectorAll('.file-dropdown-menu').forEach(m => {
            m.style.display = 'none';
        });

        if (!isShown) {
            menu.style.display = 'block';
            menu.style.position = 'fixed';
            menu.style.zIndex = '99999';

            const rect = btn.getBoundingClientRect();
            const menuWidth = menu.offsetWidth || 150;
            const menuHeight = menu.offsetHeight || 150;

            let left = rect.right - menuWidth;
            if (left < 10) left = 10;

            let top = rect.bottom + 4;
            // If overflowing the bottom of window, flip upwards
            if (top + menuHeight > window.innerHeight - 10) {
                top = Math.max(10, rect.top - menuHeight - 4);
            }

            menu.style.top = `${top}px`;
            menu.style.left = `${left}px`;
            menu.style.right = 'auto';
            menu.style.bottom = 'auto';
        }
    }

    getSelectedFiles() {
        const boxes = document.querySelectorAll('.file-select-box:checked');
        return Array.from(boxes).map(b => b.value);
    }

    updateBulkToolbar() {
        const selected = this.getSelectedFiles();
        const allBoxes = document.querySelectorAll('.file-select-box');

        if (this.selectAllCheckbox && allBoxes.length > 0) {
            this.selectAllCheckbox.checked = (selected.length === allBoxes.length);
        }

        if (this.bulkToolbar) {
            if (selected.length > 0) {
                this.bulkToolbar.style.display = 'flex';
                if (this.selectedCountEl) {
                    this.selectedCountEl.textContent = `${selected.length} item${selected.length > 1 ? 's' : ''} selected`;
                }
            } else {
                this.bulkToolbar.style.display = 'none';
            }
        }
    }

    async archiveSelected() {
        const files = this.getSelectedFiles();
        if (files.length === 0) return;

        App.toast(`Archiving ${files.length} item(s) to .tar.gz...`, 'info');
        try {
            await App.request(`/api/client/servers/${this.serverUuid}/files/compress`, {
                method: 'POST',
                body: {
                    root: this.currentDirectory,
                    files: files
                }
            });
            App.toast('Archive created successfully.', 'success');
            this.loadDirectory(this.currentDirectory);
        } catch (e) {
            App.toast('Failed to create archive: ' + e.message, 'danger');
        }
    }

    async deleteSelected() {
        const files = this.getSelectedFiles();
        if (files.length === 0) return;

        if (!await App.confirm(`Are you sure you want to delete ${files.length} selected item(s)?`)) return;

        try {
            await App.request(`/api/client/servers/${this.serverUuid}/files/delete`, {
                method: 'POST',
                body: {
                    root: this.currentDirectory,
                    files: files
                }
            });
            App.toast(`Deleted ${files.length} item(s).`, 'success');
            this.loadDirectory(this.currentDirectory);
        } catch (e) {
            App.toast('Failed to delete files: ' + e.message, 'danger');
        }
    }

    openRenameModal(fileName) {
        document.querySelectorAll('.file-dropdown-menu').forEach(m => m.style.display = 'none');
        document.getElementById('rename-old-name').value = fileName;
        document.getElementById('rename-new-name').value = fileName;
        App.openModal('modal-rename-file');
    }

    async executeRename() {
        const oldName = document.getElementById('rename-old-name').value;
        const newName = document.getElementById('rename-new-name').value.trim();

        if (!newName || newName === oldName) {
            App.closeModal('modal-rename-file');
            return;
        }

        try {
            await App.request(`/api/client/servers/${this.serverUuid}/files/rename`, {
                method: 'PUT',
                body: {
                    root: this.currentDirectory,
                    files: [{ from: oldName, to: newName }]
                }
            });
            App.closeModal('modal-rename-file');
            App.toast(`Renamed '${oldName}' to '${newName}'.`, 'success');
            this.loadDirectory(this.currentDirectory);
        } catch (e) {
            App.toast('Failed to rename: ' + e.message, 'danger');
        }
    }

    openChmodModal(fileName, mode) {
        document.querySelectorAll('.file-dropdown-menu').forEach(m => m.style.display = 'none');
        document.getElementById('chmod-target-name').value = fileName;
        document.getElementById('chmod-target-label').textContent = fileName;

        // Clean mode to octal format
        let cleanMode = '0644';
        if (mode && typeof mode === 'string') {
            const matches = mode.match(/[0-7]{3,4}/);
            if (matches) cleanMode = matches[0].length === 3 ? '0' + matches[0] : matches[0];
        }

        const modeInput = document.getElementById('chmod-mode-input');
        if (modeInput) {
            modeInput.value = cleanMode;
            modeInput.dispatchEvent(new Event('input'));
        }

        App.openModal('modal-chmod-file');
    }

    async executeChmod() {
        const fileName = document.getElementById('chmod-target-name').value;
        const modeInput = document.getElementById('chmod-mode-input').value.trim();

        try {
            await App.request(`/api/client/servers/${this.serverUuid}/files/chmod`, {
                method: 'POST',
                body: {
                    root: this.currentDirectory,
                    files: [{ file: fileName, mode: modeInput }]
                }
            });
            App.closeModal('modal-chmod-file');
            App.toast(`Permissions updated for '${fileName}'.`, 'success');
            this.loadDirectory(this.currentDirectory);
        } catch (e) {
            App.toast('Failed to update permissions: ' + e.message, 'danger');
        }
    }

    async createDirectory() {
        const input = document.getElementById('new-folder-name');
        const name = input ? input.value.trim() : '';
        if (!name) return;

        try {
            await App.request(`/api/client/servers/${this.serverUuid}/files/create-folder`, {
                method: 'POST',
                body: {
                    path: this.currentDirectory,
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
        document.querySelectorAll('.file-dropdown-menu').forEach(m => m.style.display = 'none');
        if (!await App.confirm(`Are you sure you want to delete '${name}'?`)) return;

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
        document.querySelectorAll('.file-dropdown-menu').forEach(m => m.style.display = 'none');
        try {
            const res = await App.request(`/api/client/servers/${this.serverUuid}/files/download?file=${encodeURIComponent(filePath)}`);
            const dlUrl = res.url || (res.attributes && res.attributes.url);
            if (dlUrl) {
                window.location.href = dlUrl;
            } else {
                throw new Error('Download URL not returned by server.');
            }
        } catch (e) {
            App.toast('Download error: ' + e.message, 'danger');
        }
    }

    async handleUpload() {
        const files = this.uploadInput.files;
        if (!files || files.length === 0) return;

        const overlayModal = document.getElementById('modal-upload-overlay');
        const overlayName = document.getElementById('upload-modal-filename');
        const overlaySize = document.getElementById('upload-modal-size');
        const overlayBar = document.getElementById('upload-modal-bar');
        const overlayPercent = document.getElementById('upload-modal-percent');
        const overlayStatus = document.getElementById('upload-modal-status');

        if (overlayModal) overlayModal.classList.add('active');

        try {
            const urlData = await App.request(`/api/client/servers/${this.serverUuid}/files/upload`);
            const uploadUrl = urlData.url || (urlData.attributes && urlData.attributes.url);
            if (!uploadUrl) {
                throw new Error('Unable to obtain signed upload URL.');
            }

            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const totalMb = (file.size / (1024 * 1024)).toFixed(2);

                if (overlayName) overlayName.textContent = file.name;
                if (overlaySize) overlaySize.textContent = `0 MB / ${totalMb} MB (File ${i + 1} of ${files.length})`;
                if (overlayBar) overlayBar.style.width = '0%';
                if (overlayPercent) overlayPercent.textContent = '0%';
                if (overlayStatus) overlayStatus.textContent = `Uploading directly to node...`;

                const formData = new FormData();
                formData.append('files', file);

                const xhr = new XMLHttpRequest();
                const targetUrl = `${uploadUrl}&directory=${encodeURIComponent(this.currentDirectory)}`;

                await new Promise((resolve, reject) => {
                    xhr.upload.onprogress = (e) => {
                        if (e.lengthComputable) {
                            const loadedMb = (e.loaded / (1024 * 1024)).toFixed(2);
                            const percent = Math.min(100, Math.round((e.loaded / e.total) * 100));
                            if (overlayBar) overlayBar.style.width = `${percent}%`;
                            if (overlayPercent) overlayPercent.textContent = `${percent}%`;
                            if (overlaySize) overlaySize.textContent = `${loadedMb} MB / ${totalMb} MB (File ${i + 1} of ${files.length})`;
                            if (this.progressBar) this.progressBar.style.width = `${percent}%`;
                        }
                    };
                    xhr.onload = () => {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            resolve();
                        } else {
                            reject(new Error(`Upload failed with status ${xhr.status}`));
                        }
                    };
                    xhr.onerror = () => reject(new Error('Upload network error: Node unreachable'));
                    xhr.open('POST', targetUrl);
                    xhr.send(formData);
                });
            }

            if (overlayStatus) overlayStatus.textContent = 'Upload complete!';
            App.toast('Files uploaded successfully!', 'success');
            setTimeout(() => {
                if (overlayModal) overlayModal.classList.remove('active');
            }, 600);
            this.uploadInput.value = '';
            if (this.progressBar) this.progressBar.style.width = '0%';
            this.loadDirectory(this.currentDirectory);
        } catch (e) {
            App.toast('Upload error: ' + e.message, 'danger');
            if (overlayModal) overlayModal.classList.remove('active');
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
