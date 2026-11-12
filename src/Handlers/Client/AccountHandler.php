<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Client;

use Pterodactyl\Core\ActivityLogger;
use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\Session;
use Pterodactyl\Core\View;
use Pterodactyl\Security\Encrypter;
use Pterodactyl\Security\TotpService;

/**
 * Handles Client Account actions (Profile, Password, 2FA, API Keys, SSH Keys, Activity).
 */
class AccountHandler
{
    public function overview(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $html = View::render('account.overview', ['user' => $user]);
        return Response::html($html);
    }

    public function updateEmail(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $email = trim((string)$request->input('email', ''));
        $password = (string)$request->input('password', '');
        $isApi = $request->isJson() || str_starts_with($request->getPath(), '/api/');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if ($isApi) {
                return Response::json(['errors' => [['code' => 'ValidationException', 'status' => '422', 'detail' => 'Please provide a valid email address.']]], 422);
            }
            Session::flash('error', 'Please provide a valid email address.');
            return Response::redirect('/account');
        }

        if (!password_verify($password, $user['password'])) {
            if ($isApi) {
                return Response::json(['errors' => [['code' => 'InvalidPasswordException', 'status' => '400', 'detail' => 'The password provided was incorrect.']]], 400);
            }
            Session::flash('error', 'The password provided was incorrect.');
            return Response::redirect('/account');
        }

        $existing = Database::queryOne(
            "SELECT `id` FROM `users` WHERE `email` = :email AND `id` != :id LIMIT 1",
            ['email' => $email, 'id' => $user['id']]
        );
        if ($existing) {
            if ($isApi) {
                return Response::json(['errors' => [['code' => 'ConflictHttpException', 'status' => '409', 'detail' => 'That email address is already in use by another account.']]], 409);
            }
            Session::flash('error', 'That email address is already in use by another account.');
            return Response::redirect('/account');
        }

        Database::execute("UPDATE `users` SET `email` = :email WHERE `id` = :id", ['email' => $email, 'id' => $user['id']]);
        ActivityLogger::log('user:account.email-changed', $user, ['new_email' => $email], $request);

        if ($isApi) {
            return Response::noContent();
        }

        Session::flash('success', 'Your email address has been successfully updated.');
        return Response::redirect('/account');
    }

    public function updatePassword(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $currentPassword = (string)$request->input('current_password', '');
        $newPassword = (string)$request->input('password', '');
        $confirmPassword = (string)$request->input('password_confirmation', $newPassword);
        $isApi = $request->isJson() || str_starts_with($request->getPath(), '/api/');

        if (!password_verify($currentPassword, $user['password'])) {
            if ($isApi) {
                return Response::json(['errors' => [['code' => 'InvalidPasswordException', 'status' => '400', 'detail' => 'The current password provided was incorrect.']]], 400);
            }
            Session::flash('error', 'The current password provided was incorrect.');
            return Response::redirect('/account');
        }

        if (strlen($newPassword) < 8) {
            if ($isApi) {
                return Response::json(['errors' => [['code' => 'ValidationException', 'status' => '422', 'detail' => 'The new password must be at least 8 characters long.']]], 422);
            }
            Session::flash('error', 'The new password must be at least 8 characters long.');
            return Response::redirect('/account');
        }

        if ($newPassword !== $confirmPassword) {
            if ($isApi) {
                return Response::json(['errors' => [['code' => 'ValidationException', 'status' => '422', 'detail' => 'The new passwords do not match.']]], 422);
            }
            Session::flash('error', 'The new passwords do not match.');
            return Response::redirect('/account');
        }

        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
        Database::execute("UPDATE `users` SET `password` = :pwd WHERE `id` = :id", ['pwd' => $hashed, 'id' => $user['id']]);
        ActivityLogger::log('user:account.password-changed', $user, [], $request);

        if ($isApi) {
            return Response::noContent();
        }

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
        ActivityLogger::log('user:account.two-factor.enable', $user, [], $request);

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

        ActivityLogger::log('user:account.two-factor.disable', $user, [], $request);
        return Response::json(['success' => true]);
    }

    // API Keys
    public function apiKeys(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $keys = Database::query(
            "SELECT `id`, `identifier`, `memo`, `allowed_ips`, `last_used_at`, `created_at`
             FROM `api_keys` WHERE `user_id` = :user_id AND `key_type` IN (0, 2) ORDER BY `created_at` DESC",
            ['user_id' => $user['id']]
        );

        if ($request->isJson() || str_starts_with($request->getPath(), '/api/')) {
            $data = array_map(function ($k) {
                return [
                    'object' => 'api_key',
                    'attributes' => [
                        'identifier' => $k['identifier'],
                        'description' => $k['memo'],
                        'allowed_ips' => !empty($k['allowed_ips']) ? json_decode((string)$k['allowed_ips'], true) : [],
                        'last_used_at' => $k['last_used_at'] ? date('c', strtotime($k['last_used_at'])) : null,
                        'created_at' => date('c', strtotime($k['created_at'])),
                    ],
                ];
            }, $keys);
            return Response::json(['object' => 'list', 'data' => $data]);
        }

        $html = View::render('account.api', ['user' => $user, 'keys' => $keys]);
        return Response::html($html);
    }

    public function createApiKey(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $memo = trim((string)($request->input('description') ?? $request->input('memo', '')));
        $allowedIps = trim((string)$request->input('allowed_ips', ''));

        if ($memo === '') {
            return Response::json(['error' => 'A description is required.'], 400);
        }

        $identifier = bin2hex(random_bytes(8)); // 16 chars
        $secretToken = bin2hex(random_bytes(16)); // 32 chars
        $fullKey = "ptlc_{$identifier}{$secretToken}"; // standard 53-char format

        Database::insert('api_keys', [
            'user_id' => $user['id'],
            'key_type' => 0, // 0 = standard Account / Client API Key
            'identifier' => $identifier,
            'token' => Encrypter::encryptString($secretToken),
            'memo' => $memo,
            'allowed_ips' => !empty($allowedIps) ? json_encode(array_filter(array_map('trim', explode("\n", str_replace("\r", "", $allowedIps))))) : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        ActivityLogger::log('user:account.api-key.create', $user, ['memo' => $memo, 'identifier' => $identifier], $request);

        return Response::json([
            'object' => 'api_key',
            'attributes' => [
                'identifier' => $identifier,
                'description' => $memo,
                'allowed_ips' => !empty($allowedIps) ? array_filter(array_map('trim', explode("\n", str_replace("\r", "", $allowedIps)))) : [],
                'last_used_at' => null,
                'created_at' => date('c'),
            ],
            'meta' => [
                'secret_token' => $fullKey,
            ],
            'secret_token' => $fullKey,
            'identifier' => $identifier,
            'description' => $memo,
        ]);
    }

    public function deleteApiKey(Request $request, string $identifier): Response
    {
        $user = $request->getAttribute('user');
        Database::execute(
            "DELETE FROM `api_keys` WHERE `identifier` = :identifier AND `user_id` = :user_id AND `key_type` IN (0, 2)",
            ['identifier' => $identifier, 'user_id' => $user['id']]
        );

        ActivityLogger::log('user:account.api-key.delete', $user, ['identifier' => $identifier], $request);
        return Response::noContent();
    }

    // SSH Keys
    public function sshKeys(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $keys = Database::query(
            "SELECT `id`, `name`, `fingerprint`, `public_key`, `created_at` FROM `user_ssh_keys`
             WHERE `user_id` = :user_id ORDER BY `created_at` DESC",
            ['user_id' => $user['id']]
        );

        if ($request->isJson() || str_starts_with($request->getPath(), '/api/')) {
            $data = array_map(function ($k) {
                return [
                    'object' => 'ssh_key',
                    'attributes' => [
                        'name' => $k['name'],
                        'fingerprint' => $k['fingerprint'],
                        'public_key' => $k['public_key'],
                        'created_at' => date('c', strtotime($k['created_at'])),
                    ],
                ];
            }, $keys);
            return Response::json(['object' => 'list', 'data' => $data]);
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

        ActivityLogger::log('user:account.ssh-key.create', $user, ['name' => $name, 'fingerprint' => $fingerprint], $request);

        return Response::json([
            'object' => 'ssh_key',
            'attributes' => [
                'name' => $name,
                'fingerprint' => $fingerprint,
                'public_key' => $publicKey,
                'created_at' => date('c'),
            ],
            'name' => $name,
            'fingerprint' => $fingerprint,
        ]);
    }

    public function deleteSshKey(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $fingerprint = (string)($request->input('fingerprint') ?? $request->getAttribute('fingerprint', ''));

        Database::execute(
            "DELETE FROM `user_ssh_keys` WHERE `fingerprint` = :fingerprint AND `user_id` = :user_id",
            ['fingerprint' => $fingerprint, 'user_id' => $user['id']]
        );

        ActivityLogger::log('user:account.ssh-key.delete', $user, ['fingerprint' => $fingerprint], $request);
        return Response::noContent();
    }

    // Activity Log
    public function activity(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $logs = Database::query(
            "SELECT al.`id`, al.`batch`, al.`event`, al.`ip`, al.`description`, al.`properties`, al.`timestamp`,
                    MAX(s.`name`) as `server_name`, MAX(s.`uuidShort`) as `server_uuid_short`
             FROM `activity_logs` al
             LEFT JOIN `activity_log_subjects` als ON als.`activity_log_id` = al.`id` AND (als.`subject_type` = 'server' OR als.`subject_type` LIKE '%Server')
             LEFT JOIN `servers` s ON s.`id` = als.`subject_id`
             WHERE al.`actor_id` = :user_id AND (al.`actor_type` = 'user' OR al.`actor_type` LIKE '%User' OR al.`actor_type` IS NULL)
             GROUP BY al.`id`
             ORDER BY al.`timestamp` DESC, al.`id` DESC LIMIT 100",
            ['user_id' => $user['id']]
        );

        if ($request->isJson() || str_starts_with($request->getPath(), '/api/')) {
            $formatted = array_map(function ($l) {
                return [
                    'object' => 'activity_log',
                    'attributes' => [
                        'id' => (string)$l['id'],
                        'batch' => $l['batch'],
                        'event' => $l['event'],
                        'is_api' => false,
                        'ip' => $l['ip'],
                        'description' => $l['description'],
                        'properties' => json_decode((string)$l['properties'], true) ?: new \stdClass(),
                        'has_additional_metadata' => !empty($l['server_name']),
                        'timestamp' => date('c', strtotime($l['timestamp'])),
                    ],
                    'relationships' => [
                        'server' => !empty($l['server_name']) ? [
                            'name' => $l['server_name'],
                            'identifier' => $l['server_uuid_short'],
                        ] : null,
                    ],
                ];
            }, $logs);

            return Response::json([
                'object' => 'list',
                'data' => $formatted,
                'meta' => [
                    'pagination' => [
                        'total' => count($logs),
                        'count' => count($logs),
                        'per_page' => 100,
                        'current_page' => 1,
                        'total_pages' => 1,
                        'links' => [],
                    ],
                ],
            ]);
        }

        $html = View::render('account.activity', ['user' => $user, 'logs' => $logs]);
        return Response::html($html);
    }
}
