<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= \Pterodactyl\Core\Session::getCsrfToken() ?>">
    <title>Forgot Password - <?= \Pterodactyl\Core\View::e($appName ?? 'Pterodactyl') ?></title>
    <link rel="stylesheet" href="/assets/css/pterodactyl.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: radial-gradient(circle at top, #162032 0%, #0c1017 100%);
        }
        .login-box {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }
        .login-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
        }
    </style>
</head>
<body>
    <div class="login-box">
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="font-size: 32px; font-weight: 800; color: #fff;">
                <span style="color: #e5e7eb;">▲</span> <?= \Pterodactyl\Core\View::e($appName ?? 'Pterodactyl') ?>
            </div>
            <p style="color: var(--text-secondary); font-size: 13px; margin-top: 4px;">Reset your account password</p>
        </div>

        <div class="login-card">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="margin-bottom: 20px;">
                    <?= \Pterodactyl\Core\View::e($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success" style="margin-bottom: 20px;">
                    <?= \Pterodactyl\Core\View::e($success) ?>
                </div>
            <?php endif; ?>

            <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 20px; line-height: 1.5;">
                Enter your account email address and we will send you a link to reset your password.
            </p>

            <form action="/auth/password" method="POST">
                <?= \Pterodactyl\Core\View::csrf() ?>

                <div class="form-group" style="margin-bottom: 24px;">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" required autofocus autocomplete="email" placeholder="user@example.com">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px; font-size: 14px; margin-bottom: 16px;">
                    Send Password Reset Link
                </button>

                <div style="text-align: center;">
                    <a href="/auth/login" style="color: var(--text-secondary); font-size: 13px; text-decoration: none;">
                        &larr; Return to Login
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
