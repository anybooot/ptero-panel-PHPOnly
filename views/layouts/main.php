<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= \Pterodactyl\Core\Session::getCsrfToken() ?>">
    <title><?= \Pterodactyl\Core\View::e($title ?? $appName ?? 'Pterodactyl') ?></title>
    
    <link rel="icon" type="image/png" href="/favicons/favicon-32x32.png" sizes="32x32">
    <link rel="stylesheet" href="/assets/css/pterodactyl.css">
    <link rel="stylesheet" href="/assets/css/terminal.css">
    
    <script src="/assets/js/app.js" defer></script>
    <script src="/assets/js/stats.js" defer></script>
    <script src="/assets/js/console.js" defer></script>
    <script src="/assets/js/files.js" defer></script>
</head>
<body>
    <!-- Top Navigation Bar -->
    <header class="navbar">
        <div style="display: flex; align-items: center; gap: 24px;">
            <a href="/" class="navbar-brand">
                <span style="color: #3b82f6;">▲</span> <?= \Pterodactyl\Core\View::e($appName ?? 'Pterodactyl') ?>
            </a>
            <?php if (!empty($user)): ?>
                <nav class="navbar-links">
                    <a href="/" class="nav-link <?= empty($activeTab) && empty($activeAdminTab) ? 'active' : '' ?>">Dashboard</a>
                    <?php if (!empty($user['root_admin'])): ?>
                        <a href="/admin" class="nav-link <?= !empty($activeAdminTab) ? 'active' : '' ?>">Admin Control</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($user)): ?>
            <div style="display: flex; align-items: center; gap: 14px;">
                <?php if (!empty($user['root_admin'])): ?>
                    <span class="btn-admin-badge">ROOT ADMIN</span>
                <?php endif; ?>
                <a href="/account" class="nav-link" style="color: #fff; font-weight: 600;">
                    <?= \Pterodactyl\Core\View::e($user['username']) ?>
                </a>
                <form action="/auth/logout" method="POST" style="margin: 0;">
                    <?= \Pterodactyl\Core\View::csrf() ?>
                    <button type="submit" class="btn btn-secondary btn-sm" style="padding: 4px 10px;">Logout</button>
                </form>
            </div>
        <?php endif; ?>
    </header>

    <!-- Sub-navigation slot -->
    <?= $this->yield('subnav') ?>

    <!-- Main Content Container -->
    <main class="main-container">
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <span>✓</span> <?= \Pterodactyl\Core\View::e($success) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <span>⚠</span> <?= \Pterodactyl\Core\View::e($error) ?>
            </div>
        <?php endif; ?>

        <?= $this->yield('content') ?>
    </main>

    <div id="toast-container"></div>
</body>
</html>
