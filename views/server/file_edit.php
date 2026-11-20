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
            <span style="color: var(--text-muted);">/home/container/</span><span style="color: #eceff4; font-weight: 600;"><?= \Pterodactyl\Core\View::e(ltrim($cleanFile, '/')) ?: 'new-file.txt' ?></span>
        </p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="/server/<?= $server['uuidShort'] ?>/files" class="btn btn-secondary btn-sm">Back to Files</a>
        <button id="save-file-btn" class="btn btn-primary btn-sm">Save Content</button>
    </div>
</div>

<div class="card" style="margin-bottom: 0;">
    <div class="card-header" style="background-color: #12141a; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px; padding: 12px 18px;">
        <div style="display: flex; align-items: center; gap: 10px; width: 100%; max-width: 520px;">
            <span style="color: var(--text-muted); font-size: 13px; font-family: var(--font-mono); white-space: nowrap;">Path: /home/container/</span>
            <input type="text" id="file-path-input" class="form-control" style="font-family: var(--font-mono); font-size: 13px; padding: 6px 12px;" value="<?= \Pterodactyl\Core\View::e(ltrim($cleanFile, '/')) ?>" placeholder="path/to/file.txt">
        </div>
        <div style="display: flex; align-items: center; gap: 14px;">
            <span id="editor-mode-badge" class="badge badge-secondary" style="font-family: var(--font-mono); font-size: 11px; text-transform: uppercase;">TEXT</span>
            <div style="font-size: 12px; color: var(--text-muted);">
                Press <kbd style="background: rgba(255,255,255,0.08); padding: 2px 6px; border-radius: 4px; color: #e2e8f0;">Ctrl + S</kbd> to save
            </div>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div id="ace-code-editor" style="width: 100%; height: 650px; font-size: 13.5px; line-height: 1.6;"></div>
        <textarea id="file-editor-content" style="display: none;"><?= htmlspecialchars((string)($content ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
    </div>
</div>

<!-- Ace Editor Core & Extensions -->
<script src="/assets/js/ace/ace.js"></script>
<script src="/assets/js/ace/ext-modelist.js"></script>

<style>
#ace-code-editor {
    font-family: 'JetBrains Mono', 'Fira Code', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
}
.ace_editor {
    background-color: #0d0e12 !important;
}
.ace_gutter {
    background-color: #0b0c10 !important;
    color: #64748b !important;
    border-right: 1px solid rgba(255, 255, 255, 0.07);
}
.ace_gutter-active-line {
    background-color: rgba(255, 255, 255, 0.06) !important;
    color: #f1f5f9 !important;
}
.ace_active-line {
    background: rgba(255, 255, 255, 0.03) !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if (window.ace) {
        ace.config.set("basePath", "/assets/js/ace/");
        ace.config.set("modePath", "/assets/js/ace/");
        ace.config.set("themePath", "/assets/js/ace/");
        ace.config.set("workerPath", "/assets/js/ace/");
    }

    const saveBtn = document.getElementById('save-file-btn');
    const pathInput = document.getElementById('file-path-input');
    const rawContent = document.getElementById('file-editor-content').value;
    const modeBadge = document.getElementById('editor-mode-badge');

    // Configure Ace Editor with VS Code dark styling & line numbers
    const editor = ace.edit("ace-code-editor");
    editor.setTheme("ace/theme/tomorrow_night");
    editor.session.setValue(rawContent);
    editor.setOptions({
        showLineNumbers: true,
        showGutter: true,
        firstLineNumber: 1,
        tabSize: 4,
        useSoftTabs: true,
        fontSize: "13.5px",
        fontFamily: "ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace",
        wrap: true,
        highlightActiveLine: true,
        showPrintMargin: false,
        fadeFoldWidgets: true,
        scrollPastEnd: 0.1
    });

    // Detect Syntax Mode from File Extension
    const updateSyntaxMode = (filePath) => {
        try {
            if (window.ace && ace.require) {
                const modelist = ace.require("ace/ext/modelist");
                if (modelist) {
                    const modeObj = modelist.getModeForPath(filePath || 'file.txt');
                    editor.session.setMode(modeObj.mode);
                    if (modeBadge) {
                        modeBadge.textContent = modeObj.name.toUpperCase();
                    }
                    return;
                }
            }
        } catch (e) {}
        if (modeBadge) modeBadge.textContent = 'TEXT';
    };

    updateSyntaxMode(pathInput.value);
    pathInput.addEventListener('input', () => updateSyntaxMode(pathInput.value));

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
                    content: editor.getValue()
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

    // Ctrl+S / Cmd+S in Ace Editor
    editor.commands.addCommand({
        name: 'save',
        bindKey: { win: 'Ctrl-S', mac: 'Command-S' },
        exec: function() {
            saveFile();
        }
    });

    // Window Ctrl+S fallback
    window.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            saveFile();
        }
    });
});
</script>
