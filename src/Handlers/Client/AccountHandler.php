<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Client;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\Session;
use Pterodactyl\Core\View;
use Pterodactyl\Security\Encrypter;
use Pterodactyl\Security\TotpService;

/**
 * Handles Account Settings: profile, email, password, 2FA, API keys, SSH keys, activity logs.
 */
class AccountHandler
{
    public function overview(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $success = Session::getFlash('success');
        $error = Session::getFlash('error');

        $html = View::render('account.overview', [
            'user' => $user,
            'success' => $success,
            'error' => $error,
        ]);
        return Response::html($html);
    }

    public function updateEmail(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $email = trim((string)$request->input('email', ''));
        $password = (string)$request->input('password', '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Please provide a valid email address.');
            return Response::redirect('/account');
        }

        if (!password_verify($password, $user['password'])) {
            Session::flash('error', 'The current password provided was incorrect.');
            return Response::redirect('/account');
        }

        $existing = Database::queryOne(
            "SELECT `id` FROM `users` WHERE `email` = :email AND `id` != :id LIMIT 1",
            ['email' => $email, 'id' => $user['id']]
        );
        if ($existing) {
            Session::flash('error', 'That email address is already in use by another account.');
            return Response::redirect('/account');
        }

        Database::execute("UPDATE `users` SET `email` = :email WHERE `id` = :id", ['email' => $email, 'id' => $user['id']]);
        Session::flash('success', 'Your email address has been successfully updated.');
        return Response::redirect('/account');
    }

    public function updatePassword(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $currentPassword = (string)$request->input('current_password', '');
        $newPassword = (string)$request->input('password', '');
        $confirmPassword = (string)$request->input('password_confirmation', '');

        if (!password_verify($currentPassword, $user['password'])) {
            Session::flash('error', 'The current password provided was incorrect.');
            return Response::redirect('/account');
        }

        if (strlen($newPassword) < 8) {
            Session::flash('error', 'The new password must be at least 8 characters long.');
            return Response::redirect('/account');
        }

        if ($newPassword !== $confirmPassword) {
            Session::flash('error', 'The new passwords do not match.');
            return Response::redirect('/account');
        }

        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
        Database::execute("UPDATE `users` SET `password` = :pwd WHERE `id` = :id", ['pwd' => $hashed, 'id' => $user['id']]);
        Session::flash('success', 'Your password has been successfully updated.');
        return Response::redirect('/account');
    }

    // Two-factor Authentication
    public function getTwoFactor(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $secret = TotpService::generateSecret(16);
        Session::set('totp_setup_secret', $secret);

        $otpUrl = TotpService::getOtpAuthUrl('Pterodactyl', $user['email'], $secret);
        return Response::json([
            'data' => [
                'secret' => $secret,
                'qr_url' => $otpUrl,
            ],
        ]);
    }

    public function enableTwoFactor(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $code = trim((string)$request->input('code', ''));
        $secret = (string)Session::get('totp_setup_secret', '');

        if ($secret === '' || !TotpService::verify($secret, $code)) {
            return Response::json(['error' => 'The two-factor token provided was invalid.'], 400);
        }

        $encryptedSecret = Encrypter::encryptString($secret);
        Database::execute(
            "UPDATE `users` SET `totp_secret` = :secret, `totp_authenticated` = 1 WHERE `id` = :id",
            ['secret' => $encryptedSecret, 'id' => $user['id']]
        );

        Session::remove('totp_setup_secret');
        return Response::json(['success' => true]);
    }

    public function disableTwoFactor(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $password = (string)$request->input('password', '');

        if (!password_verify($password, $user['password'])) {
            return Response::json(['error' => 'The password provided was incorrect.'], 400);
        }

        Database::execute(
            "UPDATE `users` SET `totp_secret` = NULL, `totp_authenticated` = 0 WHERE `id` = :id",
            ['id' => $user['id']]
        );

        return Response::json(['success' => true]);
    }

    // API Keys
    public function apiKeys(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $keys = Database::query(
            "SELECT `id`, `identifier`, `memo`, `allowed_ips`, `last_used_at`, `created_at`
             FROM `api_keys` WHERE `user_id` = :user_id AND `key_type` = 2 ORDER BY `created_at` DESC",
            ['user_id' => $user['id']]
        );

        if ($request->isJson()) {
            return Response::json(['data' => $keys]);
        }

        $html = View::render('account.api', ['user' => $user, 'keys' => $keys]);
        return Response::html($html);
    }

    public function createApiKey(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $memo = trim((string)$request->input('description', ''));
        $allowedIps = trim((string)$request->input('allowed_ips', ''));

        if ($memo === '') {
            return Response::json(['error' => 'A description is required.'], 400);
        }

        $identifier = bin2hex(random_bytes(8));
        $secretToken = bin2hex(random_bytes(24));
        $fullKey = "ptlc_{$identifier}_{$secretToken}";

        Database::insert('api_keys', [
            'user_id' => $user['id'],
            'key_type' => 2, // 2 = Client API Key
            'identifier' => $identifier,
            'token' => hash('sha256', $fullKey),
            'memo' => $memo,
            'allowed_ips' => !empty($allowedIps) ? json_encode(explode("\n", str_replace("\r", "", $allowedIps))) : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return Response::json([
            'secret_token' => $fullKey,
            'identifier' => $identifier,
            'description' => $memo,
        ]);
    }

    public function deleteApiKey(Request $request, string $identifier): Response
    {
        $user = $request->getAttribute('user');
        Database::execute(
            "DELETE FROM `api_keys` WHERE `identifier` = :identifier AND `user_id` = :user_id AND `key_type` = 2",
            ['identifier' => $identifier, 'user_id' => $user['id']]
        );
        return Response::noContent();
    }

    // SSH Keys
    public function sshKeys(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $keys = Database::query(
            "SELECT `id`, `name`, `fingerprint`, `created_at` FROM `user_ssh_keys`
             WHERE `user_id` = :user_id ORDER BY `created_at` DESC",
            ['user_id' => $user['id']]
        );

        if ($request->isJson()) {
            return Response::json(['data' => $keys]);
        }

        $html = View::render('account.ssh', ['user' => $user, 'keys' => $keys]);
        return Response::html($html);
    }

    public function createSshKey(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $name = trim((string)$request->input('name', ''));
        $publicKey = trim((string)$request->input('public_key', ''));

        if ($name === '' || $publicKey === '') {
            return Response::json(['error' => 'Both a name and the public key are required.'], 400);
        }

        // Calculate SHA256 fingerprint from key
        $parts = explode(' ', $publicKey);
        $keyData = $parts[1] ?? $parts[0];
        $fingerprint = hash('sha256', base64_decode($keyData));

        Database::insert('user_ssh_keys', [
            'user_id' => $user['id'],
            'name' => $name,
            'fingerprint' => $fingerprint,
            'public_key' => $publicKey,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return Response::json([
            'name' => $name,
            'fingerprint' => $fingerprint,
        ]);
    }

    public function deleteSshKey(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $fingerprint = (string)$request->input('fingerprint', '');

        Database::execute(
            "DELETE FROM `user_ssh_keys` WHERE `fingerprint` = :fingerprint AND `user_id` = :user_id",
            ['fingerprint' => $fingerprint, 'user_id' => $user['id']]
        );
        return Response::noContent();
    }

    // Activity Log
    public function activity(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $logs = Database::query(
            "SELECT `event`, `ip`, `properties`, `timestamp` FROM `activity_logs`
             WHERE `actor_id` = :user_id AND `actor_type` = 'user'
             ORDER BY `timestamp` DESC LIMIT 50",
            ['user_id' => $user['id']]
        );

        if ($request->isJson()) {
            return Response::json(['data' => $logs]);
        }

        $html = View::render('account.activity', ['user' => $user, 'logs' => $logs]);
        return Response::html($html);
    }
}
