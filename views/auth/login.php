<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= \Pterodactyl\Core\Session::getCsrfToken() ?>">
    <title>Login - <?= \Pterodactyl\Core\View::e($appName ?? 'Pterodactyl') ?></title>
    <link rel="stylesheet" href="<?= \Pterodactyl\Core\View::asset('assets/css/pterodactyl.css') ?>">
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
            <p style="color: var(--text-secondary); font-size: 13px; margin-top: 4px;">Log in to control your servers</p>
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

            <form action="/auth/login" method="POST">
                <?= \Pterodactyl\Core\View::csrf() ?>
                <?= \Pterodactyl\Core\View::botHoneypot() ?>

                <div class="form-group">
                    <label class="form-label" for="user">Username or Email</label>
                    <input type="text" id="user" name="user" class="form-control" required autofocus autocomplete="username">
                </div>

                <div class="form-group" style="margin-bottom: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label class="form-label" for="password" style="margin-bottom: 0;">Password</label>
                        <a href="/auth/password" style="color: var(--text-secondary); font-size: 12px; text-decoration: none;">
                            Forgot your password?
                        </a>
                    </div>
                    <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px; font-size: 14px;">
                    Log In
                </button>
            </form>

            <?php if (!empty($registrationEnabled)): ?>
                <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-color); text-align: center;">
                    <span style="color: var(--text-secondary); font-size: 13px;">Don't have an account?</span>
                    <a href="/auth/register" class="btn btn-secondary" style="display: block; width: 100%; margin-top: 10px; padding: 9px; font-size: 13.5px; text-align: center; text-decoration: none;">
                        Create an Account
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
