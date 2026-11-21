<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Auth;

use Pterodactyl\Core\Config;
use Pterodactyl\Core\Database;
use Pterodactyl\Core\Mailer;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\Session;
use Pterodactyl\Core\View;
use Pterodactyl\Handlers\Admin\SettingsHandler;

/**
 * Handles password reset requests and execution.
 */
class ForgotPasswordHandler
{
    public function showForgotForm(Request $request): Response
    {
        Session::start();
        if (Session::has('user_id') && Session::get('2fa_pending') !== true) {
            return Response::redirect('/');
        }

        $appName = SettingsHandler::getSetting('settings::app:name', Config::get('APP_NAME', 'Pterodactyl'));
        $html = View::render('auth.forgot_password', [
            'appName' => $appName,
            'error' => Session::getFlash('error'),
            'success' => Session::getFlash('success'),
        ]);

        return Response::html($html);
    }

    public function sendResetLink(Request $request): Response
    {
        Session::start();
        $email = trim((string)$request->input('email', ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Please enter a valid email address.');
            return Response::redirect('/auth/password');
        }

        $user = Database::queryOne("SELECT `id`, `username`, `email` FROM `users` WHERE `email` = :email LIMIT 1", ['email' => $email]);

        if ($user) {
            // Delete old tokens for this email
            Database::execute("DELETE FROM `password_resets` WHERE `email` = :email", ['email' => $email]);

            $token = bin2hex(random_bytes(32));
            Database::insert('password_resets', [
                'email' => $email,
                'token' => $token,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Determine base URL
            $appUrl = rtrim((string)Config::get('APP_URL', ''), '/');
            if (empty($appUrl) || str_contains($appUrl, 'example.com')) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $appUrl = "{$scheme}://{$host}";
            }

            $resetUrl = "{$appUrl}/auth/password/reset/{$token}";
            $appName = SettingsHandler::getSetting('settings::app:name', Config::get('APP_NAME', 'Pterodactyl'));

            $subject = "Password Reset Request - {$appName}";
            $html = "<h2>Password Reset Request</h2>"
                  . "<p>Hello <strong>" . htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>"
                  . "<p>You are receiving this email because we received a password reset request for your account.</p>"
                  . "<p style=\"margin: 24px 0;\">"
                  . "<a href=\"{$resetUrl}\" style=\"background-color: #2563eb; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: 600;\">Reset Password</a>"
                  . "</p>"
                  . "<p>If the button above does not work, copy and paste this URL into your browser:</p>"
                  . "<p style=\"word-break: break-all; color: #2563eb;\"><a href=\"{$resetUrl}\">{$resetUrl}</a></p>"
                  . "<p>This password reset link will expire in 60 minutes.</p>"
                  . "<p>If you did not request a password reset, no further action is required.</p>"
                  . "<hr style=\"border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;\">"
                  . "<p style=\"color: #64748b; font-size: 12px;\">{$appName}</p>";

            try {
                Mailer::send($email, $subject, $html);
            } catch (\Throwable $e) {
                // If mailer fails, we can flash an error in dev/setup environments
                error_log("Failed to send password reset email: " . $e->getMessage());
            }
        }

        Session::flash('success', 'If an account exists with that email, a password reset link has been sent.');
        return Response::redirect('/auth/password');
    }

    public function showResetForm(Request $request, string $token): Response
    {
        Session::start();
        $token = trim($token);

        $record = Database::queryOne(
            "SELECT * FROM `password_resets` WHERE `token` = :token LIMIT 1",
            ['token' => $token]
        );

        if (!$record) {
            Session::flash('error', 'This password reset token is invalid or has expired.');
            return Response::redirect('/auth/password');
        }

        // Check if token is older than 60 minutes
        $createdAt = strtotime((string)$record['created_at']);
        if (time() - $createdAt > 3600) {
            Database::execute("DELETE FROM `password_resets` WHERE `token` = :token", ['token' => $token]);
            Session::flash('error', 'This password reset token has expired. Please request a new one.');
            return Response::redirect('/auth/password');
        }

        $appName = SettingsHandler::getSetting('settings::app:name', Config::get('APP_NAME', 'Pterodactyl'));
        $html = View::render('auth.reset_password', [
            'appName' => $appName,
            'token' => $token,
            'email' => $record['email'],
            'error' => Session::getFlash('error'),
        ]);

        return Response::html($html);
    }

    public function resetPassword(Request $request): Response
    {
        Session::start();
        $token = trim((string)$request->input('token', ''));
        $email = trim((string)$request->input('email', ''));
        $password = (string)$request->input('password', '');
        $passwordConfirmation = (string)$request->input('password_confirmation', '');

        if ($token === '' || $email === '' || $password === '') {
            Session::flash('error', 'All fields are required.');
            return Response::redirect("/auth/password/reset/{$token}");
        }

        if ($password !== $passwordConfirmation) {
            Session::flash('error', 'The password confirmation does not match.');
            return Response::redirect("/auth/password/reset/{$token}");
        }

        $minLength = (int)SettingsHandler::getSetting('settings::auth:password_min_length', 8);
        if (strlen($password) < $minLength) {
            Session::flash('error', "Password must be at least {$minLength} characters long.");
            return Response::redirect("/auth/password/reset/{$token}");
        }

        $record = Database::queryOne(
            "SELECT * FROM `password_resets` WHERE `token` = :token AND `email` = :email LIMIT 1",
            ['token' => $token, 'email' => $email]
        );

        if (!$record) {
            Session::flash('error', 'Invalid token or email address.');
            return Response::redirect('/auth/password');
        }

        $createdAt = strtotime((string)$record['created_at']);
        if (time() - $createdAt > 3600) {
            Database::execute("DELETE FROM `password_resets` WHERE `token` = :token", ['token' => $token]);
            Session::flash('error', 'This password reset token has expired. Please request a new one.');
            return Response::redirect('/auth/password');
        }

        $hashed = password_hash($password, PASSWORD_BCRYPT);
        Database::execute(
            "UPDATE `users` SET `password` = :pwd, `updated_at` = :now WHERE `email` = :email",
            ['pwd' => $hashed, 'now' => date('Y-m-d H:i:s'), 'email' => $email]
        );

        Database::execute("DELETE FROM `password_resets` WHERE `email` = :email", ['email' => $email]);

        Session::flash('success', 'Your password has been reset successfully! You can now log in.');
        return Response::redirect('/auth/login');
    }
}
