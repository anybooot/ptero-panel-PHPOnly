<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Auth;

use Pterodactyl\Core\ActivityLogger;
use Pterodactyl\Core\Config;
use Pterodactyl\Core\Database;
use Pterodactyl\Core\Mailer;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\Session;
use Pterodactyl\Core\View;
use Pterodactyl\Handlers\Admin\SettingsHandler;

/**
 * Handles public user registration when enabled in Admin Settings.
 */
class RegisterHandler
{
    public function showRegister(Request $request): Response
    {
        Session::start();
        if (Session::has('user_id') && Session::get('2fa_pending') !== true) {
            return Response::redirect('/');
        }

        $enabled = SettingsHandler::getSetting('settings::auth:registration_enabled', 'false') === 'true';
        if (!$enabled) {
            Session::flash('error', 'User registration is currently disabled by the administrator.');
            return Response::redirect('/auth/login');
        }

        $appName = SettingsHandler::getSetting('settings::app:name', Config::get('APP_NAME', 'Pterodactyl'));
        $html = View::render('auth.register', [
            'appName' => $appName,
            'error' => Session::getFlash('error'),
            'old' => Session::getFlash('old', []),
        ]);

        return Response::html($html);
    }

    public function register(Request $request): Response
    {
        Session::start();
        $enabled = SettingsHandler::getSetting('settings::auth:registration_enabled', 'false') === 'true';
        if (!$enabled) {
            Session::flash('error', 'User registration is currently disabled.');
            return Response::redirect('/auth/login');
        }

        $username = trim((string)$request->input('username', ''));
        $email = trim((string)$request->input('email', ''));
        $nameFirst = trim((string)$request->input('name_first', ''));
        $nameLast = trim((string)$request->input('name_last', ''));
        $password = (string)$request->input('password', '');
        $passwordConfirmation = (string)$request->input('password_confirmation', '');

        $oldData = [
            'username' => $username,
            'email' => $email,
            'name_first' => $nameFirst,
            'name_last' => $nameLast,
        ];

        if ($username === '' || $email === '' || $password === '') {
            Session::flash('old', $oldData);
            Session::flash('error', 'Username, email address, and password are required.');
            return Response::redirect('/auth/register');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('old', $oldData);
            Session::flash('error', 'Please provide a valid email address.');
            return Response::redirect('/auth/register');
        }

        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $username) || strlen($username) < 3 || strlen($username) > 32) {
            Session::flash('old', $oldData);
            Session::flash('error', 'Username must be between 3 and 32 characters and contain only letters, numbers, dashes, underscores, and dots.');
            return Response::redirect('/auth/register');
        }

        if ($password !== $passwordConfirmation) {
            Session::flash('old', $oldData);
            Session::flash('error', 'The password confirmation does not match.');
            return Response::redirect('/auth/register');
        }

        // Validate password policy
        $minLen = (int)SettingsHandler::getSetting('settings::auth:password_min_length', 8);
        if (strlen($password) < $minLen) {
            Session::flash('old', $oldData);
            Session::flash('error', "Password must be at least {$minLen} characters long.");
            return Response::redirect('/auth/register');
        }

        if (SettingsHandler::getSetting('settings::auth:password_require_uppercase', 'false') === 'true') {
            if (!preg_match('/[A-Z]/', $password)) {
                Session::flash('old', $oldData);
                Session::flash('error', 'Password must contain at least one uppercase letter.');
                return Response::redirect('/auth/register');
            }
        }

        if (SettingsHandler::getSetting('settings::auth:password_require_numbers', 'false') === 'true') {
            if (!preg_match('/[0-9]/', $password)) {
                Session::flash('old', $oldData);
                Session::flash('error', 'Password must contain at least one number.');
                return Response::redirect('/auth/register');
            }
        }

        if (SettingsHandler::getSetting('settings::auth:password_require_symbols', 'false') === 'true') {
            if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
                Session::flash('old', $oldData);
                Session::flash('error', 'Password must contain at least one special character.');
                return Response::redirect('/auth/register');
            }
        }

        // Check uniqueness
        $existing = Database::queryOne(
            "SELECT `id` FROM `users` WHERE `email` = :email OR `username` = :username LIMIT 1",
            ['email' => $email, 'username' => $username]
        );
        if ($existing) {
            Session::flash('old', $oldData);
            Session::flash('error', 'A user with that username or email address already exists.');
            return Response::redirect('/auth/register');
        }

        $uuid = bin2hex(random_bytes(16));
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $now = date('Y-m-d H:i:s');

        $userId = Database::insert('users', [
            'uuid' => $uuid,
            'email' => $email,
            'username' => $username,
            'name_first' => $nameFirst !== '' ? $nameFirst : $username,
            'name_last' => $nameLast !== '' ? $nameLast : '',
            'password' => $hashedPassword,
            'language' => 'en',
            'root_admin' => 0,
            'use_totp' => 0,
            'gravatar' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        ActivityLogger::log('auth:register', (int)$userId, ['username' => $username, 'email' => $email], $request);

        // Optionally send welcome email via SMTP
        $appName = SettingsHandler::getSetting('settings::app:name', Config::get('APP_NAME', 'Pterodactyl'));
        try {
            $welcomeSubj = "Welcome to {$appName}!";
            $welcomeBody = "<h2>Welcome to {$appName}!</h2>"
                         . "<p>Hello <strong>" . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . "</strong>,</p>"
                         . "<p>Thank you for registering an account on {$appName}. You can now log in and manage your servers.</p>"
                         . "<hr style=\"border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;\">"
                         . "<p style=\"color: #64748b; font-size: 12px;\">{$appName}</p>";
            Mailer::send($email, $welcomeSubj, $welcomeBody);
        } catch (\Throwable $e) {
            error_log("Failed to send welcome email: " . $e->getMessage());
        }

        // Auto log in new user
        Session::set('user_id', (int)$userId);
        Session::remove('2fa_pending');
        Session::remove('2fa_user_id');

        Session::flash('success', 'Your account has been successfully created! Welcome to your dashboard.');
        return Response::redirect('/');
    }
}
