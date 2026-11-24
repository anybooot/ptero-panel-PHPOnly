<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= \Pterodactyl\Core\Session::getCsrfToken() ?>">
    <title>Register - <?= \Pterodactyl\Core\View::e($appName ?? 'Pterodactyl') ?></title>
    <link rel="stylesheet" href="<?= \Pterodactyl\Core\View::asset('assets/css/pterodactyl.css') ?>">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: radial-gradient(circle at top, #162032 0%, #0c1017 100%);
            padding: 24px 0;
        }
        .login-box {
            width: 100%;
            max-width: 480px;
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
            <p style="color: var(--text-secondary); font-size: 13px; margin-top: 4px;">Create a new account</p>
        </div>

        <div class="login-card">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="margin-bottom: 20px;">
                    <?= \Pterodactyl\Core\View::e($error) ?>
                </div>
            <?php endif; ?>

            <form action="/auth/register" method="POST">
                <?= \Pterodactyl\Core\View::csrf() ?>
                <?= \Pterodactyl\Core\View::botHoneypot() ?>

                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" required autofocus autocomplete="username" value="<?= \Pterodactyl\Core\View::e($old['username'] ?? '') ?>" placeholder="johndoe">
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" required autocomplete="email" value="<?= \Pterodactyl\Core\View::e($old['email'] ?? '') ?>" placeholder="user@example.com">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label" for="name_first">First Name</label>
                        <input type="text" id="name_first" name="name_first" class="form-control" value="<?= \Pterodactyl\Core\View::e($old['name_first'] ?? '') ?>" placeholder="John">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="name_last">Last Name</label>
                        <input type="text" id="name_last" name="name_last" class="form-control" value="<?= \Pterodactyl\Core\View::e($old['name_last'] ?? '') ?>" placeholder="Doe">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required autocomplete="new-password">
                </div>

                <div class="form-group" style="margin-bottom: 24px;">
                    <label class="form-label" for="password_confirmation">Confirm Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px; font-size: 14px; margin-bottom: 16px;">
                    Register Account
                </button>

                <div style="text-align: center;">
                    <span style="color: var(--text-secondary); font-size: 13px;">Already have an account?</span>
                    <a href="/auth/login" style="color: #60a5fa; font-size: 13px; text-decoration: none; margin-left: 4px;">
                        Log In
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
