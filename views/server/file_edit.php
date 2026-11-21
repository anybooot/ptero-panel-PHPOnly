<?php $this->extend('layouts.server'); ?>

<?php
$cleanFile = !empty($file) ? str_replace('\\', '/', (string)$file) : '';
$fileName = !empty($cleanFile) ? basename($cleanFile) : '';

$explicitDir = !empty($directory) ? str_replace('\\', '/', (string)$directory) : '';
if (!empty($explicitDir)) {
    $parentDir = '/' . trim($explicitDir, '/');
} elseif (!empty($cleanFile)) {
    $dirName = dirname($cleanFile);
    $parentDir = ($dirName === '.' || $dirName === '/' || $dirName === '\\') ? '/' : '/' . trim(str_replace('\\', '/', $dirName), '/');
} else {
    $parentDir = '/';
}
if ($parentDir === '/.') $parentDir = '/';
$backUrl = '/server/' . $server['uuidShort'] . '/files' . ($parentDir !== '/' ? '?directory=' . urlencode($parentDir) : '');

$defaultInputPath = ltrim($cleanFile, '/');
if (empty($defaultInputPath) && $parentDir !== '/') {
    $defaultInputPath = trim($parentDir, '/') . '/';
}
?>

<div class="page-header" style="margin-bottom: 16px;">
    <div>
        <h1 class="page-title"><?= empty($cleanFile) ? 'Create New File' : 'Editing: ' . \Pterodactyl\Core\View::e($fileName) ?></h1>
        <p class="page-subtitle" style="font-family: var(--font-mono); font-size: 13px;">
            <?php if (!empty($cleanFile)): ?>
                <span style="color: var(--text-muted);">/home/container/</span><span style="color: #eceff4; font-weight: 600;"><?= \Pterodactyl\Core\View::e(ltrim($cleanFile, '/')) ?></span>
            <?php else: ?>
                <span style="color: var(--text-muted);">/home/container/<?= $parentDir !== '/' ? \Pterodactyl\Core\View::e(trim($parentDir, '/')) . '/' : '' ?></span><span style="color: #94a3b8; font-style: italic;">[new file]</span>
            <?php endif; ?>
        </p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="<?= $backUrl ?>" class="btn btn-secondary btn-sm" id="btn-back-to-files">Back to Files</a>
        <button id="save-file-btn" class="btn btn-primary btn-sm"><?= empty($cleanFile) ? 'Create File' : 'Save Content' ?></button>
    </div>
</div>

<div class="card" style="margin-bottom: 0;">
    <div class="card-header" style="background-color: #12141a; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px; padding: 12px 18px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="color: var(--text-muted); font-size: 13px; font-family: var(--font-mono); white-space: nowrap;">Path:</span>
            <?php if (!empty($cleanFile)): ?>
                <span style="font-family: var(--font-mono); font-size: 13px; font-weight: 600; color: #eceff4;">
                    /home/container/<?= \Pterodactyl\Core\View::e(ltrim($cleanFile, '/')) ?>
                </span>
                <input type="hidden" id="file-path-input" value="<?= \Pterodactyl\Core\View::e($cleanFile) ?>">
            <?php else: ?>
                <span style="font-family: var(--font-mono); font-size: 13px; font-weight: 600; color: #eceff4;">
                    /home/container/<?= $parentDir !== '/' ? \Pterodactyl\Core\View::e(trim($parentDir, '/')) . '/' : '' ?><span style="color: #94a3b8; font-style: italic;">[new file]</span>
                </span>
                <input type="hidden" id="file-path-input" value="">
            <?php endif; ?>
        </div>
        <div style="display: flex; align-items: center; gap: 14px;">
            <span id="editor-mode-badge" class="badge badge-secondary" style="font-family: var(--font-mono); font-size: 11px; text-transform: uppercase;">TEXT</span>
            <div style="font-size: 12px; color: var(--text-muted);">
                Press <kbd style="background: rgba(255,255,255,0.08); padding: 2px 6px; border-radius: 4px; color: #e2e8f0;">Ctrl + S</kbd> to save
            </div>
        </div>
    </div>
    <div class="card-body" style="padding: 0; position: relative;">
        <!-- Find & Replace Floating Widget (Point 7) -->
        <div id="ace-find-replace-bar" class="ace-find-replace-container" style="display: none;">
            <!-- Find Row -->
            <div class="find-row">
                <button type="button" id="btn-toggle-replace" class="find-icon-btn" title="Toggle Replace (Ctrl+H)">
                    <span id="replace-toggle-arrow" style="display: inline-block; transition: transform 0.15s ease;">▶</span>
                </button>
                <div class="find-input-wrapper">
                    <input type="text" id="find-input" placeholder="Find (Ctrl+F)" autocomplete="off" spellcheck="false">
                    <span id="find-match-counter" class="find-counter">0 of 0</span>
                </div>
                <button type="button" id="btn-find-prev" class="find-icon-btn" title="Previous Match (Shift+Enter)">▲</button>
                <button type="button" id="btn-find-next" class="find-icon-btn" title="Next Match (Enter)">▼</button>
                
                <div class="find-options" style="display: flex; gap: 3px;">
                    <button type="button" id="btn-opt-case" class="find-opt-btn" title="Match Case (Alt+C)">Aa</button>
                    <button type="button" id="btn-opt-word" class="find-opt-btn" title="Match Whole Word (Alt+W)">\b</button>
                    <button type="button" id="btn-opt-regex" class="find-opt-btn" title="Use Regular Expression (Alt+R)">.*</button>
                </div>
                <button type="button" id="btn-close-find" class="find-icon-btn close-btn" title="Close (Escape)">✕</button>
            </div>

            <!-- Replace Row (dedesubt cu ce vrei sa fie schimbat) -->
            <div class="replace-row" id="replace-row" style="display: none;">
                <div style="width: 22px;"></div>
                <div class="find-input-wrapper">
                    <input type="text" id="replace-input" placeholder="Replace with..." autocomplete="off" spellcheck="false">
                </div>
                <button type="button" id="btn-replace-one" class="find-btn" title="Replace Next">Replace</button>
                <button type="button" id="btn-replace-all" class="find-btn" title="Replace All Matches">Replace All</button>
            </div>
        </div>

        <div id="ace-code-editor" style="width: 100%; height: 650px; font-size: 13.5px; line-height: 1.6;"></div>
        <textarea id="file-editor-content" style="display: none;"><?= htmlspecialchars((string)($content ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
    </div>
</div>

<!-- Modal: Name New File -->
<div class="modal-backdrop" id="modal-name-file">
    <div class="modal-dialog" style="max-width: 460px;">
        <div class="modal-header">
            <h3 class="modal-title">Name Your File</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form id="form-name-file">
            <div class="modal-body">
                <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 14px;">
                    Saving to directory: <strong style="color: #eceff4; font-family: var(--font-mono);">/home/container/<?= $parentDir !== '/' ? \Pterodactyl\Core\View::e(trim($parentDir, '/')) . '/' : '' ?></strong>
                </p>
                <div class="form-group">
                    <label class="form-label" for="new-file-name-input">File Name</label>
                    <input type="text" id="new-file-name-input" class="form-control" placeholder="e.g. server.properties, config.yml" required autofocus>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary" id="btn-submit-name-file">Create File</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Unsaved Changes Confirmation -->
<div class="modal-backdrop" id="modal-unsaved-changes" style="align-items: center; justify-content: center; z-index: 99999;">
    <div class="modal-dialog" style="max-width: 480px; border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 20px 40px rgba(0,0,0,0.8); border-radius: 12px; background: #161820; padding: 0; overflow: hidden;">
        <div class="modal-header" style="background: #12141a; padding: 14px 18px; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: space-between;">
            <h3 class="modal-title" style="font-size: 16px; font-weight: 700; color: #eceff4; margin: 0; display: flex; align-items: center; gap: 8px;">
                <span style="color: #f59e0b; font-size: 18px;">⚠️</span>
                <span>Unsaved Changes</span>
            </h3>
            <button type="button" class="modal-close" id="btn-unsaved-close" style="background: none; border: none; color: #94a3b8; font-size: 20px; cursor: pointer; line-height: 1;">&times;</button>
        </div>
        <div class="modal-body" style="padding: 20px 18px;">
            <p style="color: #cbd5e1; font-size: 13.5px; line-height: 1.5; margin: 0;">
                You have unsaved changes in this file. Do you want to save before leaving, exit without saving, or cancel?
            </p>
        </div>
        <div class="modal-footer" style="background: #12141a; padding: 12px 18px; border-top: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap;">
            <button type="button" class="btn btn-secondary btn-sm" id="btn-unsaved-cancel">Cancel</button>
            <button type="button" class="btn btn-danger btn-sm" id="btn-unsaved-discard">Exit without Saving</button>
            <button type="button" class="btn btn-primary btn-sm" id="btn-unsaved-save">Save & Exit</button>
        </div>
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

/* Find & Replace Bar Styling */
.ace-find-replace-container {
    position: absolute;
    top: 12px;
    right: 24px;
    z-index: 100;
    background: #181b24;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 8px;
    box-shadow: 0 16px 36px rgba(0, 0, 0, 0.7);
    padding: 8px 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 420px;
}
.find-row, .replace-row {
    display: flex;
    align-items: center;
    gap: 6px;
}
.find-input-wrapper {
    position: relative;
    flex: 1;
    display: flex;
    align-items: center;
}
.find-input-wrapper input {
    width: 100%;
    background: #0d0e14;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 5px;
    color: #f1f5f9;
    font-family: var(--font-mono);
    font-size: 13px;
    padding: 6px 65px 6px 10px;
    outline: none;
    box-sizing: border-box;
}
.find-input-wrapper input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
}
.find-counter {
    position: absolute;
    right: 8px;
    font-size: 11px;
    color: #94a3b8;
    pointer-events: none;
    font-family: var(--font-mono);
}
.find-icon-btn, .find-opt-btn, .find-btn {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #cbd5e1;
    border-radius: 4px;
    padding: 5px 8px;
    font-size: 12px;
    cursor: pointer;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
}
.find-icon-btn:hover, .find-opt-btn:hover, .find-btn:hover {
    background: rgba(255, 255, 255, 0.15);
    color: #ffffff;
}
.find-opt-btn.active {
    background: #2563eb;
    border-color: #3b82f6;
    color: #ffffff;
    font-weight: 700;
}
.find-icon-btn.close-btn {
    margin-left: 2px;
    color: #94a3b8;
}
.find-icon-btn.close-btn:hover {
    color: #ef4444;
}
.find-btn {
    font-size: 11.5px;
    padding: 6px 12px;
    font-weight: 600;
    white-space: nowrap;
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

    let originalContent = editor.getValue();
    let isNavigatingAway = false;
    let pendingTargetUrl = null;

    const isDirty = () => editor.getValue() !== originalContent;

    const saveFile = async () => {
        let filePath = pathInput.value.trim().replace(/\\/g, '/');
        if (!filePath) {
            App.openModal('modal-name-file');
            return false;
        }
        if (!filePath.startsWith('/')) {
            filePath = '/' + filePath;
        }

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        try {
            const res = await App.request('/api/client/servers/<?= $server['uuid'] ?>/files/write', {
                method: 'POST',
                body: {
                    file: filePath,
                    content: editor.getValue()
                }
            });
            if (res && res.error) {
                const msg = (res.error.includes('Unable to reach the node') || res.error.includes('Wings')) ? 'Unable to reach the node.' : res.error;
                App.toast(msg, 'danger');
                return false;
            }
            originalContent = editor.getValue();
            App.toast('File saved successfully.', 'success');
            return true;
        } catch (e) {
            const msg = (e.message && (e.message.includes('Unable to reach the node') || e.message.includes('Wings'))) ? 'Unable to reach the node.' : ('Failed to save file: ' + e.message);
            App.toast(msg, 'danger');
            return false;
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = <?= empty($cleanFile) ? "'Create File'" : "'Save Content'" ?>;
        }
    };

    saveBtn.addEventListener('click', saveFile);

    // Unsaved Changes Navigation Guard
    document.addEventListener('click', (e) => {
        if (isNavigatingAway) return;
        const link = e.target.closest('a[href]');
        if (!link) return;
        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;

        if (isDirty()) {
            e.preventDefault();
            e.stopPropagation();
            pendingTargetUrl = link.href;
            App.openModal('modal-unsaved-changes');
        }
    }, true);

    const btnUnsavedCancel = document.getElementById('btn-unsaved-cancel');
    const btnUnsavedClose = document.getElementById('btn-unsaved-close');
    const closeUnsavedDialog = () => {
        pendingTargetUrl = null;
        App.closeModal('modal-unsaved-changes');
    };
    if (btnUnsavedCancel) btnUnsavedCancel.addEventListener('click', closeUnsavedDialog);
    if (btnUnsavedClose) btnUnsavedClose.addEventListener('click', closeUnsavedDialog);

    const btnUnsavedDiscard = document.getElementById('btn-unsaved-discard');
    if (btnUnsavedDiscard) {
        btnUnsavedDiscard.addEventListener('click', () => {
            isNavigatingAway = true;
            App.closeModal('modal-unsaved-changes');
            if (pendingTargetUrl) {
                window.location.href = pendingTargetUrl;
            }
        });
    }

    const btnUnsavedSave = document.getElementById('btn-unsaved-save');
    if (btnUnsavedSave) {
        btnUnsavedSave.addEventListener('click', async () => {
            btnUnsavedSave.disabled = true;
            btnUnsavedSave.textContent = 'Saving...';
            const ok = await saveFile();
            btnUnsavedSave.disabled = false;
            btnUnsavedSave.textContent = 'Save & Exit';
            if (ok) {
                isNavigatingAway = true;
                App.closeModal('modal-unsaved-changes');
                if (pendingTargetUrl) {
                    window.location.href = pendingTargetUrl;
                }
            }
        });
    }

    window.addEventListener('beforeunload', (e) => {
        if (isDirty() && !isNavigatingAway) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    const formNameFile = document.getElementById('form-name-file');
    if (formNameFile) {
        formNameFile.addEventListener('submit', async (e) => {
            e.preventDefault();
            const nameInput = document.getElementById('new-file-name-input');
            let fileName = nameInput.value.trim().replace(/\\/g, '/').replace(/^\/+/, '');
            if (!fileName) return;

            const parentDir = <?= json_encode($parentDir) ?>;
            let fullPath = (parentDir === '/' ? '' : parentDir) + '/' + fileName;
            if (!fullPath.startsWith('/')) fullPath = '/' + fullPath;

            App.closeModal('modal-name-file');
            saveBtn.disabled = true;
            saveBtn.textContent = 'Creating...';

            try {
                const res = await App.request('/api/client/servers/<?= $server['uuid'] ?>/files/write', {
                    method: 'POST',
                    body: {
                        file: fullPath,
                        content: editor.getValue()
                    }
                });
                if (res && res.error) {
                    const msg = (res.error.includes('Unable to reach the node') || res.error.includes('Wings')) ? 'Unable to reach the node.' : res.error;
                    App.toast(msg, 'danger');
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Create File';
                    return;
                }
                originalContent = editor.getValue();
                isNavigatingAway = true;
                App.toast('File created successfully.', 'success');
                setTimeout(() => {
                    window.location.href = '/server/<?= $server['uuidShort'] ?>/files/edit?file=' + encodeURIComponent(fullPath) + '&directory=' + encodeURIComponent(parentDir);
                }, 400);
            } catch (err) {
                App.toast('Failed to create file: ' + err.message, 'danger');
                saveBtn.disabled = false;
                saveBtn.textContent = 'Create File';
            }
        });
    }

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

    // =========================================================================
    // Point 7: Interactive Find & Replace (Ctrl+F and Ctrl+H)
    // =========================================================================
    const findBar = document.getElementById('ace-find-replace-bar');
    const findInput = document.getElementById('find-input');
    const replaceRow = document.getElementById('replace-row');
    const replaceInput = document.getElementById('replace-input');
    const btnToggleReplace = document.getElementById('btn-toggle-replace');
    const replaceToggleArrow = document.getElementById('replace-toggle-arrow');
    const matchCounter = document.getElementById('find-match-counter');
    const btnFindPrev = document.getElementById('btn-find-prev');
    const btnFindNext = document.getElementById('btn-find-next');
    const btnReplaceOne = document.getElementById('btn-replace-one');
    const btnReplaceAll = document.getElementById('btn-replace-all');
    const btnCloseFind = document.getElementById('btn-close-find');
    const btnOptCase = document.getElementById('btn-opt-case');
    const btnOptWord = document.getElementById('btn-opt-word');
    const btnOptRegex = document.getElementById('btn-opt-regex');

    let isCaseSensitive = false;
    let isWholeWord = false;
    let isRegex = false;
    let isReplaceVisible = false;

    const getFindOptions = () => ({
        caseSensitive: isCaseSensitive,
        wholeWord: isWholeWord,
        regExp: isRegex,
        wrap: true,
        backwards: false,
        skipCurrent: false
    });

    const updateMatchCounter = () => {
        const query = findInput.value;
        if (!query) {
            matchCounter.textContent = '0 of 0';
            return;
        }

        try {
            let flags = 'g' + (isCaseSensitive ? '' : 'i');
            let pattern;
            if (isRegex) {
                pattern = new RegExp(query, flags);
            } else {
                let escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                if (isWholeWord) escaped = '\\b' + escaped + '\\b';
                pattern = new RegExp(escaped, flags);
            }
            const matches = editor.getValue().match(pattern);
            const count = matches ? matches.length : 0;

            if (count === 0) {
                matchCounter.textContent = 'No results';
            } else {
                matchCounter.textContent = `${count} result${count === 1 ? '' : 's'}`;
            }
        } catch (e) {
            matchCounter.textContent = 'Invalid regex';
        }
    };

    const performFind = (backwards = false) => {
        const needle = findInput.value;
        if (!needle) {
            updateMatchCounter();
            return;
        }
        const opts = getFindOptions();
        opts.backwards = backwards;
        editor.find(needle, opts);
        updateMatchCounter();
    };

    const openFind = (showReplace = false) => {
        findBar.style.display = 'flex';
        const selectedText = editor.getSelectedText();
        if (selectedText && !selectedText.includes('\n')) {
            findInput.value = selectedText;
        }

        if (showReplace) {
            isReplaceVisible = true;
            replaceRow.style.display = 'flex';
            replaceToggleArrow.style.transform = 'rotate(90deg)';
            if (selectedText) {
                replaceInput.focus();
                replaceInput.select();
            } else {
                findInput.focus();
                findInput.select();
            }
        } else {
            findInput.focus();
            findInput.select();
        }

        if (findInput.value) {
            performFind(false);
        } else {
            updateMatchCounter();
        }
    };

    const closeFind = () => {
        findBar.style.display = 'none';
        editor.focus();
    };

    // Toggle Replace Row
    btnToggleReplace.addEventListener('click', () => {
        isReplaceVisible = !isReplaceVisible;
        replaceRow.style.display = isReplaceVisible ? 'flex' : 'none';
        replaceToggleArrow.style.transform = isReplaceVisible ? 'rotate(90deg)' : 'rotate(0deg)';
        if (isReplaceVisible) {
            replaceInput.focus();
        } else {
            findInput.focus();
        }
    });

    // Options toggles
    btnOptCase.addEventListener('click', () => {
        isCaseSensitive = !isCaseSensitive;
        btnOptCase.classList.toggle('active', isCaseSensitive);
        performFind(false);
    });

    btnOptWord.addEventListener('click', () => {
        isWholeWord = !isWholeWord;
        btnOptWord.classList.toggle('active', isWholeWord);
        performFind(false);
    });

    btnOptRegex.addEventListener('click', () => {
        isRegex = !isRegex;
        btnOptRegex.classList.toggle('active', isRegex);
        performFind(false);
    });

    // Find Prev / Next
    btnFindNext.addEventListener('click', () => performFind(false));
    btnFindPrev.addEventListener('click', () => performFind(true));

    findInput.addEventListener('input', () => performFind(false));
    findInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            performFind(e.shiftKey);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            closeFind();
        }
    });

    // Replace One (urmatorul)
    btnReplaceOne.addEventListener('click', () => {
        const replacement = replaceInput.value;
        editor.replace(replacement);
        performFind(false);
    });

    // Replace All (toate)
    btnReplaceAll.addEventListener('click', () => {
        const needle = findInput.value;
        if (!needle) return;
        const replacement = replaceInput.value;
        const opts = getFindOptions();
        editor.replaceAll(replacement, opts);
        updateMatchCounter();
        App.toast('All occurrences replaced.', 'info');
    });

    replaceInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (e.ctrlKey || e.altKey) {
                btnReplaceAll.click();
            } else {
                btnReplaceOne.click();
            }
        } else if (e.key === 'Escape') {
            e.preventDefault();
            closeFind();
        }
    });

    btnCloseFind.addEventListener('click', closeFind);

    // Register Ace Commands for Ctrl+F and Ctrl+H
    editor.commands.addCommand({
        name: 'openCustomFind',
        bindKey: { win: 'Ctrl-F', mac: 'Command-F' },
        exec: function() {
            openFind(false);
        }
    });

    editor.commands.addCommand({
        name: 'openCustomReplace',
        bindKey: { win: 'Ctrl-H', mac: 'Command-H' },
        exec: function() {
            openFind(true);
        }
    });

    // Global Window shortcuts fallback
    window.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f') {
            e.preventDefault();
            openFind(false);
        } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'h') {
            e.preventDefault();
            openFind(true);
        } else if (e.key === 'Escape' && findBar.style.display !== 'none') {
            e.preventDefault();
            closeFind();
        }
    });
});
</script>
