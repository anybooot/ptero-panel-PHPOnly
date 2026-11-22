<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= \Pterodactyl\Core\Session::getCsrfToken() ?>">
    <title>Panel Setup Wizard - Pterodactyl</title>
    <link rel="stylesheet" href="<?= \Pterodactyl\Core\View::asset('assets/css/pterodactyl.css') ?>">
    <style>
        body {
            min-height: 100vh;
            background: radial-gradient(circle at top, #162032 0%, #0c1017 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: var(--font-sans);
        }
        .setup-container {
            width: 100%;
            max-width: 680px;
        }
        .setup-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 36px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
        }
        .step-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
        }
        .step-pill.active {
            color: #ffffff;
        }
        .step-pill.completed {
            color: #10b981;
        }
        .step-number {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        .step-pill.active .step-number {
            background: #2563eb;
            color: #ffffff;
        }
        .step-pill.completed .step-number {
            background: #10b981;
            color: #ffffff;
        }
        .req-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }
        .req-table th, .req-table td {
            padding: 10px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
        }
        .req-table th {
            color: var(--text-muted);
            font-weight: 600;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <!-- Logo / Title -->
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="font-size: 32px; font-weight: 800; color: #fff; letter-spacing: -0.02em;">
                <span style="color: #e5e7eb;">▲</span> Pterodactyl Setup
            </div>
            <p style="color: var(--text-secondary); font-size: 13.5px; margin-top: 6px;">Automated Panel Installation & Initialization</p>
        </div>

        <div class="setup-card">
            <!-- Progress Bar / Steps Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 18px; border-bottom: 1px solid var(--border-color);">
                <div class="step-pill <?= $step === 1 ? 'active' : ($step > 1 ? 'completed' : '') ?>">
                    <div class="step-number"><?= $step > 1 ? '✓' : '1' ?></div>
                    <span>Requirements</span>
                </div>
                <div style="color: var(--border-color); font-size: 18px;">➔</div>
                <div class="step-pill <?= $step === 2 ? 'active' : ($step > 2 ? 'completed' : '') ?>">
                    <div class="step-number"><?= $step > 2 ? '✓' : '2' ?></div>
                    <span>Database</span>
                </div>
                <div style="color: var(--border-color); font-size: 18px;">➔</div>
                <div class="step-pill <?= $step === 3 ? 'active' : '' ?>">
                    <div class="step-number">3</div>
                    <span>Admin User</span>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="margin-bottom: 20px;">
                    <span>⚠️</span> <?= \Pterodactyl\Core\View::e($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success" style="margin-bottom: 20px;">
                    <span>✓</span> <?= \Pterodactyl\Core\View::e($success) ?>
                </div>
            <?php endif; ?>

            <!-- STEP 1: Host Requirements -->
            <?php if ($step === 1): ?>
                <div>
                    <h2 style="font-size: 18px; font-weight: 700; color: #eceff4; margin-bottom: 6px;">Host System Requirements</h2>
                    <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 16px;">
                        Verifying PHP modules and environment dependencies before configuration.
                    </p>

                    <div style="background: rgba(0,0,0,0.25); border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden; margin-bottom: 24px;">
                        <table class="req-table">
                            <thead>
                                <tr>
                                    <th>Requirement</th>
                                    <th>Required</th>
                                    <th>Detected</th>
                                    <th style="text-align: right;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($checks['items'] as $item): ?>
                                    <tr>
                                        <td>
                                            <strong style="color: #eceff4;"><?= \Pterodactyl\Core\View::e($item['name']) ?></strong>
                                            <?php if (!empty($item['desc'])): ?>
                                                <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;"><?= \Pterodactyl\Core\View::e($item['desc']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color: var(--text-secondary); font-family: var(--font-mono); font-size: 12px;"><?= \Pterodactyl\Core\View::e($item['required']) ?></td>
                                        <td style="color: #eceff4; font-family: var(--font-mono); font-size: 12px;"><?= \Pterodactyl\Core\View::e($item['current']) ?></td>
                                        <td style="text-align: right;">
                                            <?php if ($item['passed']): ?>
                                                <span class="badge badge-success">PASS</span>
                                            <?php elseif (!$item['critical']): ?>
                                                <span class="badge badge-secondary">OPTIONAL</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">FAIL</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <a href="/setup?step=1" class="btn btn-secondary btn-sm">Re-check</a>
                        <?php if ($allChecksPassed): ?>
                            <a href="/setup?step=2" class="btn btn-primary" style="padding: 10px 22px;">Continue to Database Setup ➔</a>
                        <?php else: ?>
                            <button class="btn btn-primary" disabled style="opacity: 0.5; cursor: not-allowed;">Fix required items above to proceed</button>
                        <?php endif; ?>
                    </div>
                </div>

            <!-- STEP 2: Database & URL Configuration -->
            <?php elseif ($step === 2): ?>
                <form action="/setup/database" method="POST">
                    <?= \Pterodactyl\Core\View::csrf() ?>

                    <h2 style="font-size: 18px; font-weight: 700; color: #eceff4; margin-bottom: 6px;">Database & Panel Configuration</h2>
                    <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 20px;">
                        Enter your MySQL database details. Existing tables in this database will be refreshed with the clean schema.
                    </p>

                    <div class="form-group">
                        <label class="form-label" for="app_url">Panel URL (APP_URL)</label>
                        <input type="url" id="app_url" name="app_url" class="form-control" value="<?= \Pterodactyl\Core\View::e($detectedUrl) ?>" required placeholder="http://your-domain.com">
                        <small style="font-size: 11px; color: var(--text-muted); margin-top: 4px; display: block;">The publicly accessible web address of this panel without trailing slash.</small>
                    </div>

                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label class="form-label" for="db_host">Database Host</label>
                            <input type="text" id="db_host" name="db_host" class="form-control" value="127.0.0.1" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="db_port">Database Port</label>
                            <input type="number" id="db_port" name="db_port" class="form-control" value="3306" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="db_database">Database Name</label>
                        <input type="text" id="db_database" name="db_database" class="form-control" value="panel" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label class="form-label" for="db_username">Database User</label>
                            <input type="text" id="db_username" name="db_username" class="form-control" value="pterodactyl" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="db_password">Database Password</label>
                            <input type="password" id="db_password" name="db_password" class="form-control" placeholder="Database password">
                        </div>
                    </div>

                    <div class="alert alert-warning" style="margin-top: 14px; margin-bottom: 24px; font-size: 12.5px;">
                        <span>⚠️</span> <strong>Warning:</strong> Any existing tables in this database will be dropped and rebuilt with a clean schema.
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <a href="/setup?step=1" class="btn btn-secondary btn-sm">⬅ Back to Requirements</a>
                        <button type="submit" class="btn btn-primary" style="padding: 10px 22px;">Import Database & Continue ➔</button>
                    </div>
                </form>

            <!-- STEP 3: Create First Administrator User -->
            <?php elseif ($step === 3): ?>
                <form action="/setup/user" method="POST">
                    <?= \Pterodactyl\Core\View::csrf() ?>

                    <h2 style="font-size: 18px; font-weight: 700; color: #eceff4; margin-bottom: 6px;">Create Initial Administrator</h2>
                    <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 20px;">
                        Create your root administrator account to access the panel management area.
                    </p>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label class="form-label" for="name_last">Nume (Last Name)</label>
                            <input type="text" id="name_last" name="name_last" class="form-control" required placeholder="Popescu">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="name_first">Prenume (First Name)</label>
                            <input type="text" id="name_first" name="name_first" class="form-control" required placeholder="Marian">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label class="form-label" for="username">Username</label>
                            <input type="text" id="username" name="username" class="form-control" required placeholder="admin">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" required placeholder="admin@example.com">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label class="form-label" for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" required minlength="8" placeholder="At least 8 characters">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="password_confirmation">Confirm Password</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required minlength="8" placeholder="Repeat password">
                        </div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 14px;">
                        <a href="/setup?step=2" class="btn btn-secondary btn-sm">⬅ Back to Database</a>
                        <button type="submit" class="btn btn-primary" style="padding: 10px 22px;">Complete Setup & Finish ➔</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
