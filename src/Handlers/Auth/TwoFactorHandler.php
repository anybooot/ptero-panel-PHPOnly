<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Auth;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\Session;
use Pterodactyl\Core\View;
use Pterodactyl\Security\Encrypter;
use Pterodactyl\Security\TotpService;

/**
 * Handles 2FA TOTP verification checkpoints during login.
 */
class TwoFactorHandler
{
    public function showCheckpoint(Request $request): Response
    {
        Session::start();
        $userId = Session::get('2fa_user_id');
        if (!$userId || Session::get('2fa_pending') !== true) {
            return Response::redirect('/auth/login');
        }

        $error = Session::getFlash('error');
        $html = View::render('auth.checkpoint', ['error' => $error]);
        return Response::html($html);
    }

    public function verifyCheckpoint(Request $request): Response
    {
        Session::start();
        $userId = Session::get('2fa_user_id');
        if (!$userId || Session::get('2fa_pending') !== true) {
            return Response::redirect('/auth/login');
        }

        $code = trim((string)$request->input('code', ''));
        $user = Database::queryOne("SELECT * FROM `users` WHERE `id` = :id LIMIT 1", ['id' => $userId]);

        if (!$user) {
            Session::destroy();
            return Response::redirect('/auth/login');
        }

        $secret = $user['totp_secret'];
        // Attempt decryption if encrypted
        try {
            $decryptedSecret = Encrypter::decryptString($secret);
            if (!empty($decryptedSecret)) {
                $secret = $decryptedSecret;
            }
        } catch (\Throwable) {
            // Keep raw if not encrypted
        }

        if (!TotpService::verify($secret, $code)) {
            if ($request->isAjax()) {
                return Response::json(['error' => 'The two-factor authentication code provided was invalid.'], 400);
            }
            Session::flash('error', 'The two-factor authentication code provided was invalid.');
            return Response::redirect('/auth/checkpoint');
        }

        // 2FA Verified
        Session::set('user_id', $user['id']);
        Session::remove('2fa_pending');
        Session::remove('2fa_user_id');

        Database::insert('activity_logs', [
            'ip' => $request->getClientIp(),
            'event' => 'auth:checkpoint',
            'properties' => json_encode([]),
            'timestamp' => date('Y-m-d H:i:s'),
            'actor_id' => $user['id'],
            'actor_type' => 'user',
        ]);

        if ($request->isAjax()) {
            return Response::json(['complete' => true, 'redirect' => '/']);
        }
        return Response::redirect('/');
    }
}
