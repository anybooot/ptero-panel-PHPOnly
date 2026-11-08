<?php $this->extend('layouts.server'); ?>

<div class="page-header" style="margin-bottom: 16px;">
    <div>
        <h1 class="page-title">File Manager</h1>
        <div id="files-breadcrumbs" style="color: var(--text-secondary); font-size: 13px; margin-top: 4px;">/</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="/server/<?= $server['uuidShort'] ?>/files/edit" class="btn btn-secondary btn-sm">New File</a>
        <button class="btn btn-secondary btn-sm" onclick="App.openModal('modal-new-folder')">New Folder</button>
        <label class="btn btn-primary btn-sm" style="margin: 0; cursor: pointer;">
            Upload File
            <input type="file" id="file-upload-input" multiple style="display: none;">
        </label>
    </div>
</div>

<!-- Upload Progress Bar (hidden by default) -->
<div style="width: 100%; height: 4px; background: rgba(255,255,255,0.05); margin-bottom: 12px; border-radius: 2px; overflow: hidden;">
    <div id="upload-progress-bar" style="width: 0%; height: 100%; background: #3b82f6; transition: width 0.2s ease;"></div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;"><input type="checkbox" id="select-all-files"></th>
                        <th>Name</th>
                        <th>Size</th>
                        <th>Modified</th>
                        <th style="text-align: right;">Actions</th>
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

<script>
document.addEventListener('DOMContentLoaded', () => {
    window.fileManager = new FileManager('<?= $server['uuid'] ?>', '<?= \Pterodactyl\Core\View::e($directory ?? '/') ?>');
});
</script>
