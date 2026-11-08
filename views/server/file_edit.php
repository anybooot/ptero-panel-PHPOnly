<?php $this->extend('layouts.server'); ?>

<div class="page-header" style="margin-bottom: 16px;">
    <div>
        <h1 class="page-title"><?= empty($file) ? 'Create New File' : 'Editing: ' . \Pterodactyl\Core\View::e(basename($file)) ?></h1>
        <p class="page-subtitle"><?= \Pterodactyl\Core\View::e($file ?: 'Specify path and file name below') ?></p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="/server/<?= $server['uuidShort'] ?>/files" class="btn btn-secondary btn-sm">Back to Files</a>
        <button id="save-file-btn" class="btn btn-primary btn-sm">Save Content</button>
    </div>
</div>

<div class="card" style="margin-bottom: 0;">
    <div class="card-header" style="background-color: #0f1520;">
        <input type="text" id="file-path-input" class="form-control" style="font-family: var(--font-mono); font-size: 13px; max-width: 400px;" value="<?= \Pterodactyl\Core\View::e($file ?? '') ?>" placeholder="/path/to/file.txt">
    </div>
    <div class="card-body" style="padding: 0;">
        <textarea id="file-editor-content" style="width: 100%; height: 560px; background-color: #080c12; color: #f1f5f9; font-family: var(--font-mono); font-size: 13px; padding: 16px; border: none; outline: none; resize: vertical; line-height: 1.5; tab-size: 4;" spellcheck="false"><?= htmlspecialchars((string)($content ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const saveBtn = document.getElementById('save-file-btn');
    const pathInput = document.getElementById('file-path-input');
    const editor = document.getElementById('file-editor-content');

    const saveFile = async () => {
        const file = pathInput.value.trim();
        if (!file) {
            App.toast('Please specify a file path before saving.', 'danger');
            return;
        }

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        try {
            await App.request('/api/client/servers/<?= $server['uuid'] ?>/files/write', {
                method: 'POST',
                body: {
                    file: file,
                    content: editor.value
                }
            });
            App.toast('File saved successfully.', 'success');
        } catch (e) {
            App.toast('Failed to save file: ' + e.message, 'danger');
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Content';
        }
    };

    saveBtn.addEventListener('click', saveFile);

    // Ctrl+S / Cmd+S shortcut
    window.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            saveFile();
        }
    });
});
</script>
