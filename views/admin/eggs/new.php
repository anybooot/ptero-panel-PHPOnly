<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Create New Egg</h1>
        <p class="page-subtitle">Define server startup configurations and Docker container templates</p>
    </div>
    <div>
        <a href="/admin/nests" class="btn btn-secondary btn-sm">Back to Nests</a>
    </div>
</div>

<div class="card" style="max-width: 800px;">
    <div class="card-header">Egg Configuration</div>
    <form action="/admin/nests/egg/new" method="POST">
        <?= \Pterodactyl\Core\View::csrf() ?>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label" for="egg-nest">Associated Nest</label>
                <select id="egg-nest" name="nest_id" class="form-control" required>
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
                <label class="form-label" for="egg-name">Egg Name</label>
                <input type="text" id="egg-name" name="name" class="form-control" required placeholder="e.g. Paper Minecraft, BungeeCord, Node.js">
            </div>

            <div class="form-group">
                <label class="form-label" for="egg-desc">Description</label>
                <textarea id="egg-desc" name="description" class="form-control" rows="2" placeholder="Description of this egg..."></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="egg-image">Docker Image</label>
                <input type="text" id="egg-image" name="docker_image" class="form-control" required placeholder="ghcr.io/pterodactyl/yolks:java_17">
            </div>

            <div class="form-group">
                <label class="form-label" for="egg-startup">Startup Command</label>
                <input type="text" id="egg-startup" name="startup" class="form-control" placeholder="java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}">
            </div>
        </div>
        <div class="card-footer" style="text-align: right;">
            <button type="submit" class="btn btn-primary">Create Egg</button>
        </div>
    </form>
</div>
