<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Import Egg</h1>
        <p class="page-subtitle">Import an official or custom Egg definition from a JSON file</p>
    </div>
    <div>
        <a href="/admin/nests" class="btn btn-secondary btn-sm">Back to Nests</a>
    </div>
</div>

<div class="card" style="max-width: 800px;">
    <div class="card-header">Import Egg Configuration</div>
    <form action="/admin/nests/egg/import" method="POST" enctype="multipart/form-data">
        <?= \Pterodactyl\Core\View::csrf() ?>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label" for="dest-nest">Associated Destination Nest</label>
                <select id="dest-nest" name="nest_id" class="form-control" required>
                    <option value="">Select Target Nest</option>
                    <?php 
                    $selectedNest = (int)($_GET['nest_id'] ?? 0);
                    foreach ($nests as $n): 
                    ?>
                        <option value="<?= $n['id'] ?>" <?= $selectedNest === (int)$n['id'] ? 'selected' : '' ?>>
                            <?= \Pterodactyl\Core\View::e($n['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="egg-file">Egg File (.json)</label>
                <input type="file" id="egg-file" name="egg_file" class="form-control" accept=".json,application/json">
                <small style="color: var(--text-muted); font-size: 12px; margin-top: 4px; display: block;">
                    Select an official Pterodactyl egg JSON file to upload.
                </small>
            </div>

            <div style="text-align: center; color: var(--text-muted); margin: 16px 0; font-weight: 600;">- OR -</div>

            <div class="form-group">
                <label class="form-label" for="import-json">Paste Egg JSON</label>
                <textarea id="import-json" name="import_json" class="form-control" rows="8" placeholder="Paste raw JSON here if not uploading a file..." style="font-family: var(--font-mono); font-size: 12px;"></textarea>
            </div>
        </div>
        <div class="card-footer" style="text-align: right;">
            <button type="submit" class="btn btn-primary">Import Egg</button>
        </div>
    </form>
</div>
