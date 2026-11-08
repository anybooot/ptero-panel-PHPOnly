<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= \Pterodactyl\Core\Session::getCsrfToken() ?>">
    <title>Two-Factor Authentication - <?= \Pterodactyl\Core\View::e($appName ?? 'Pterodactyl') ?></title>
    <link rel="stylesheet" href="/assets/css/pterodactyl.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: radial-gradient(circle at top, #162032 0%, #0c1017 100%);
        }
        .checkpoint-box {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }
        .checkpoint-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
        }
    </style>
</head>
<body>
    <div class="checkpoint-box">
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="font-size: 32px; font-weight: 800; color: #fff;">
                <span style="color: #3b82f6;">▲</span> Two-Factor Checkpoint
            </div>
            <p style="color: var(--text-secondary); font-size: 13px; margin-top: 4px;">Enter the 6-digit code from your authenticator app</p>
        </div>

        <div class="checkpoint-card">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="margin-bottom: 20px;">
                    <?= \Pterodactyl\Core\View::e($error) ?>
                </div>
            <?php endif; ?>

            <form action="/auth/checkpoint" method="POST">
                <?= \Pterodactyl\Core\View::csrf() ?>

                <div class="form-group" style="margin-bottom: 24px;">
                    <label class="form-label" for="code">Authentication Code</label>
                    <input type="text" id="code" name="code" class="form-control" style="font-size: 20px; text-align: center; letter-spacing: 0.3em; font-family: var(--font-mono);" required autofocus maxlength="6" autocomplete="one-time-code">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px; font-size: 14px; margin-bottom: 12px;">
                    Continue
                </button>

                <div style="text-align: center;">
                    <a href="/auth/login" style="font-size: 13px; color: var(--text-secondary);">Return to Login</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
