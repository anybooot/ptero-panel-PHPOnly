<?php $this->extend('layouts.server'); ?>

<div class="page-header" style="margin-bottom: 16px;">
    <div>
        <h1 class="page-title">File Manager</h1>
        <div id="files-breadcrumbs" style="color: var(--text-secondary); font-size: 13px; margin-top: 4px;">/</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="/server/<?= $server['uuidShort'] ?>/files/edit" class="btn btn-secondary btn-sm" id="btn-new-file">New File</a>
        <button class="btn btn-secondary btn-sm" onclick="App.openModal('modal-new-folder')">New Folder</button>
        <label class="btn btn-primary btn-sm" style="margin: 0; cursor: pointer;">
            Upload File
            <input type="file" id="file-upload-input" multiple style="display: none;">
        </label>
    </div>
</div>

<!-- Floating Bulk Actions Toolbar (fixed bottom center, over everything) -->
<div id="files-bulk-actions" class="floating-bulk-bar" style="display: none;">
    <div style="display: flex; align-items: center; gap: 10px;">
        <span class="bulk-counter-pill" id="selected-files-count">0 items selected</span>
        <button type="button" id="btn-bulk-deselect" class="btn btn-secondary btn-sm" style="padding: 3px 8px; font-size: 11px;">Deselect</button>
    </div>
    <div style="height: 18px; width: 1px; background: rgba(255,255,255,0.12); margin: 0 4px;"></div>
    <div style="display: flex; gap: 8px;">
        <button id="btn-bulk-archive" class="btn btn-secondary btn-sm" style="display: flex; align-items: center; gap: 6px;">
            <span>📦</span> Archive
        </button>
        <button id="btn-bulk-delete" class="btn btn-danger btn-sm" style="display: flex; align-items: center; gap: 6px;">
            <span>🗑</span> Delete
        </button>
    </div>
</div>

<!-- Upload Progress Bar (hidden by default) -->
<div style="width: 100%; height: 4px; background: rgba(255,255,255,0.05); margin-bottom: 12px; border-radius: 2px; overflow: hidden;">
    <div id="upload-progress-bar" style="width: 0%; height: 100%; background: #e5e7eb; transition: width 0.2s ease;"></div>
</div>

<div class="card" style="position: relative; overflow: visible;">
    <div class="card-body" style="padding: 0; overflow: visible;">
        <div class="table-responsive" style="overflow-x: auto; overflow-y: visible; min-height: 220px;">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;"><input type="checkbox" id="select-all-files" style="cursor: pointer;"></th>
                        <th>Name</th>
                        <th>Size</th>
                        <th>Modified</th>
                        <th style="width: 70px; text-align: right;"></th>
                    </tr>
                </thead>
                <tbody id="files-table-body">
                    <tr><td colspan="5" style="text-align: center; padding: 20px;">Initializing file manager...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- New Folder Modal -->
<div class="modal-backdrop" id="modal-new-folder">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Create Directory</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form id="new-folder-form">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="new-folder-name">Directory Name</label>
                    <input type="text" id="new-folder-name" class="form-control" required placeholder="e.g. plugins, config">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Rename Modal -->
<div class="modal-backdrop" id="modal-rename-file">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Rename File / Folder</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form id="rename-file-form">
            <input type="hidden" id="rename-old-name">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="rename-new-name">New Name</label>
                    <input type="text" id="rename-new-name" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Rename</button>
            </div>
        </form>
    </div>
</div>

<!-- Chmod Permissions Modal -->
<div class="modal-backdrop" id="modal-chmod-file">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">File Permissions (Chmod)</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form id="chmod-file-form">
            <input type="hidden" id="chmod-target-name">
            <div class="modal-body">
                <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 16px;">
                    Editing permissions for: <strong id="chmod-target-label" style="color: #eceff4; font-family: var(--font-mono);"></strong>
                </p>
                <div class="form-group">
                    <label class="form-label" for="chmod-mode-input">Octal Permission Mode</label>
                    <input type="text" id="chmod-mode-input" class="form-control" maxlength="4" style="font-family: var(--font-mono); font-size: 16px; font-weight: 700; width: 140px;" value="0644">
                </div>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; background: rgba(0,0,0,0.25); padding: 14px; border-radius: 8px;">
                    <div>
                        <div style="font-weight: 600; color: #eceff4; font-size: 12px; margin-bottom: 6px;">Owner (User)</div>
                        <label style="display: block; font-size: 12px; margin-bottom: 4px;"><input type="checkbox" id="perm-ur"> Read</label>
                        <label style="display: block; font-size: 12px; margin-bottom: 4px;"><input type="checkbox" id="perm-uw"> Write</label>
                        <label style="display: block; font-size: 12px;"><input type="checkbox" id="perm-ux"> Execute</label>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: #eceff4; font-size: 12px; margin-bottom: 6px;">Group</div>
                        <label style="display: block; font-size: 12px; margin-bottom: 4px;"><input type="checkbox" id="perm-gr"> Read</label>
                        <label style="display: block; font-size: 12px; margin-bottom: 4px;"><input type="checkbox" id="perm-gw"> Write</label>
                        <label style="display: block; font-size: 12px;"><input type="checkbox" id="perm-gx"> Execute</label>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: #eceff4; font-size: 12px; margin-bottom: 6px;">Others</div>
                        <label style="display: block; font-size: 12px; margin-bottom: 4px;"><input type="checkbox" id="perm-or"> Read</label>
                        <label style="display: block; font-size: 12px; margin-bottom: 4px;"><input type="checkbox" id="perm-ow"> Write</label>
                        <label style="display: block; font-size: 12px;"><input type="checkbox" id="perm-ox"> Execute</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Update Permissions</button>
            </div>
        </form>
    </div>
</div>

<!-- Large Upload Overlay Modal -->
<div class="modal-backdrop" id="modal-upload-overlay" style="align-items: center; justify-content: center;">
    <div class="modal-dialog" style="max-width: 520px; text-align: center; padding: 32px 24px;">
        <div style="font-size: 42px; margin-bottom: 12px; animation: bounce 1.5s infinite;">📤</div>
        <h3 style="color: #eceff4; font-size: 20px; font-weight: 700; margin-bottom: 6px;">Uploading File(s)</h3>
        <p id="upload-modal-filename" style="font-family: var(--font-mono); font-size: 14px; color: #ffffff; margin-bottom: 4px; word-break: break-all;">filename.zip</p>
        <p id="upload-modal-size" style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">0 MB / 0 MB</p>

        <!-- Big Progress Bar -->
        <div style="width: 100%; height: 18px; background: rgba(255,255,255,0.06); border-radius: 9px; overflow: hidden; position: relative; margin-bottom: 12px; border: 1px solid rgba(255,255,255,0.08);">
            <div id="upload-modal-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #9ca3af, #ffffff); transition: width 0.15s ease;"></div>
        </div>
        <div id="upload-modal-percent" style="font-weight: 700; font-size: 15px; color: #eceff4;">0%</div>
        <div id="upload-modal-status" style="font-size: 12px; color: var(--text-secondary); margin-top: 6px;">Uploading directly to node...</div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    window.fileManager = new FileManager('<?= $server['uuid'] ?>', '<?= \Pterodactyl\Core\View::e($directory ?? '/') ?>');
});
</script>
