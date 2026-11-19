<?php $this->extend('layouts.server'); ?>

<?php
$cleanFile = !empty($file) ? str_replace('\\', '/', (string)$file) : '';
$fileName = !empty($cleanFile) ? basename($cleanFile) : '';
$dirName = !empty($cleanFile) ? dirname($cleanFile) : '';
if ($dirName === '.' || $dirName === '') $dirName = '/';
?>

<div class="page-header" style="margin-bottom: 16px;">
    <div>
        <h1 class="page-title"><?= empty($cleanFile) ? 'Create New File' : 'Editing: ' . \Pterodactyl\Core\View::e($fileName) ?></h1>
        <p class="page-subtitle" style="font-family: var(--font-mono); font-size: 13px;">
            <span style="color: var(--text-muted);">/home/container/</span><span style="color: #60a5fa;"><?= \Pterodactyl\Core\View::e(ltrim($cleanFile, '/')) ?: 'new-file.txt' ?></span>
        </p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="/server/<?= $server['uuidShort'] ?>/files" class="btn btn-secondary btn-sm">Back to Files</a>
        <button id="save-file-btn" class="btn btn-primary btn-sm">Save Content</button>
    </div>
</div>

<div class="card" style="margin-bottom: 0;">
    <div class="card-header" style="background-color: #12141a; display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 18px;">
        <div style="display: flex; align-items: center; gap: 10px; width: 100%; max-width: 520px;">
            <span style="color: var(--text-muted); font-size: 13px; font-family: var(--font-mono); white-space: nowrap;">Path: /home/container/</span>
            <input type="text" id="file-path-input" class="form-control" style="font-family: var(--font-mono); font-size: 13px; padding: 6px 12px;" value="<?= \Pterodactyl\Core\View::e(ltrim($cleanFile, '/')) ?>" placeholder="path/to/file.txt">
        </div>
        <div style="font-size: 12px; color: var(--text-muted);">
            Press <kbd style="background: rgba(255,255,255,0.08); padding: 2px 6px; border-radius: 4px; color: #e2e8f0;">Ctrl + S</kbd> to save
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <textarea id="file-editor-content" style="width: 100%; height: 600px; background-color: #0b0c10; color: #eceff4; font-family: var(--font-mono); font-size: 13.5px; padding: 18px; border: none; outline: none; resize: vertical; line-height: 1.6; tab-size: 4;" spellcheck="false"><?= htmlspecialchars((string)($content ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const saveBtn = document.getElementById('save-file-btn');
    const pathInput = document.getElementById('file-path-input');
    const editor = document.getElementById('file-editor-content');

    // Enable TAB indentation in editor
    editor.addEventListener('keydown', (e) => {
        if (e.key === 'Tab') {
            e.preventDefault();
            const start = editor.selectionStart;
            const end = editor.selectionEnd;
            editor.value = editor.value.substring(0, start) + '    ' + editor.value.substring(end);
            editor.selectionStart = editor.selectionEnd = start + 4;
        }
    });

    const saveFile = async () => {
        let filePath = pathInput.value.trim().replace(/\\/g, '/');
        if (!filePath) {
            App.toast('Please specify a file path before saving.', 'danger');
            return;
        }
        if (!filePath.startsWith('/')) {
            filePath = '/' + filePath;
        }

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        try {
            await App.request('/api/client/servers/<?= $server['uuid'] ?>/files/write', {
                method: 'POST',
                body: {
                    file: filePath,
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
