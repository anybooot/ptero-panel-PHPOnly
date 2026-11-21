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
    
    <script>window.PterodactylAppName = <?= json_encode($appName ?? 'Pterodactyl') ?>;</script>
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
                <span style="color: #e5e7eb;">▲</span> <?= \Pterodactyl\Core\View::e($appName ?? 'Pterodactyl') ?>
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
            <div style="display: flex; align-items: center; gap: 12px;">
                <?php if (!empty($user['root_admin'])): ?>
                    <span class="btn-admin-badge">ROOT ADMIN</span>
                <?php endif; ?>
                <div class="user-dropdown-container" style="position: relative;">
                    <button type="button" id="user-dropdown-btn" class="user-dropdown-toggle" style="background: transparent; border: none; display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 4px 8px; border-radius: 8px; transition: background 0.15s ease;">
                        <span style="color: #f3f5f9; font-weight: 600; font-size: 13.5px; user-select: none;"><?= \Pterodactyl\Core\View::e($user['username']) ?></span>
                        <div class="user-avatar-badge" style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #374151 0%, #1f2937 100%); border: 1px solid rgba(255,255,255,0.18); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; color: #ffffff; text-transform: uppercase; user-select: none; box-shadow: 0 2px 6px rgba(0,0,0,0.3);"><?= mb_strtoupper(mb_substr((string)($user['username'] ?? 'U'), 0, 1)) ?></div>
                    </button>
                    <div class="user-dropdown-menu" id="user-dropdown-menu" style="display: none; position: absolute; right: 0; top: calc(100% + 8px); background: #161822; border: 1px solid var(--border-color); border-radius: 8px; min-width: 160px; z-index: 1000; box-shadow: 0 12px 28px rgba(0,0,0,0.65); overflow: hidden; padding: 5px;">
                        <a href="/account" class="dropdown-item" style="display: flex; align-items: center; gap: 8px; padding: 8px 12px; color: #eceff4; font-size: 13px; font-weight: 500; text-decoration: none; border-radius: 6px; transition: background 0.15s ease;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.7;"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                            <span>Settings</span>
                        </a>
                        <form action="/auth/logout" method="POST" style="margin: 0;">
                            <?= \Pterodactyl\Core\View::csrf() ?>
                            <button type="submit" class="dropdown-item" style="width: 100%; display: flex; align-items: center; gap: 8px; padding: 8px 12px; color: #ef4444; font-size: 13px; font-weight: 500; background: none; border: none; text-align: left; cursor: pointer; border-radius: 6px; transition: background 0.15s ease;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                <span>Logout</span>
                            </button>
                        </form>
                    </div>
                </div>
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

    <!-- Global Custom Confirmation Modal -->
    <div class="modal-backdrop" id="modal-app-confirm" style="align-items: center; justify-content: center; z-index: 99999;">
        <div class="modal-dialog" style="max-width: 440px; border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 20px 40px rgba(0,0,0,0.8); border-radius: 12px; background: #161820; padding: 0; overflow: hidden;">
            <div class="modal-header" style="background: #12141a; padding: 14px 18px; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: space-between;">
                <h3 class="modal-title" id="app-confirm-title" style="font-size: 16px; font-weight: 700; color: #eceff4; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <span id="app-confirm-icon" style="color: #f59e0b; font-size: 17px;">⚠️</span>
                    <span id="app-confirm-title-text">Confirm Action</span>
                </h3>
                <button type="button" class="modal-close" id="app-confirm-close-btn" style="background: none; border: none; color: #94a3b8; font-size: 20px; cursor: pointer; line-height: 1;">&times;</button>
            </div>
            <div class="modal-body" style="padding: 20px 18px;">
                <p id="app-confirm-message" style="color: #cbd5e1; font-size: 13.5px; line-height: 1.5; margin: 0;">Are you sure you want to proceed?</p>
            </div>
            <div class="modal-footer" style="background: #12141a; padding: 12px 18px; border-top: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary btn-sm" id="app-confirm-btn-cancel">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="app-confirm-btn-ok">Confirm</button>
            </div>
        </div>
    </div>

    <div id="toast-container"></div>
</body>
</html>
