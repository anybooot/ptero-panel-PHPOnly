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

        $ip = $request->getClientIp();
        $totpKey = "2fa_checkpoint:{$userId}:{$ip}";

        if (\Pterodactyl\Security\RateLimiter::tooManyAttempts($totpKey, 5)) {
            $secs = \Pterodactyl\Security\RateLimiter::availableIn($totpKey);
            $msg = "Too many failed 2FA verification attempts. Please wait {$secs} seconds before trying again.";
            if ($request->isAjax()) {
                return Response::json(['error' => $msg], 429);
            }
            Session::flash('error', $msg);
            return Response::redirect('/auth/checkpoint');
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
            \Pterodactyl\Security\RateLimiter::hit($totpKey, 300);

            if ($request->isAjax()) {
                return Response::json(['error' => 'The two-factor authentication code provided was invalid.'], 400);
            }
            Session::flash('error', 'The two-factor authentication code provided was invalid.');
            return Response::redirect('/auth/checkpoint');
        }

        // 2FA Verified - clear rate limits and regenerate session ID
        \Pterodactyl\Security\RateLimiter::clear($totpKey);
        Session::regenerate(true);

        Session::set('user_id', $user['id']);
        Session::remove('2fa_pending');
        Session::remove('2fa_user_id');

        // Check if 30-day device remember was requested
        if (!empty($request->input('remember'))) {
            $expiry = time() + (30 * 86400); // 30 days
            $appKey = (string)\Pterodactyl\Core\Config::get('APP_KEY', 'default_ptero_key');
            $signature = hash_hmac('sha256', (string)$user['id'] . '|' . $expiry . '|' . (string)$user['totp_secret'], $appKey);
            $cookieVal = base64_encode((string)json_encode([
                'user_id' => $user['id'],
                'expiry' => $expiry,
                'signature' => $signature,
            ]));
            $secure = str_starts_with((string)\Pterodactyl\Core\Config::get('APP_URL', ''), 'https://');
            setcookie('pterodactyl_2fa_remember', $cookieVal, [
                'expires' => $expiry,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            $_COOKIE['pterodactyl_2fa_remember'] = $cookieVal;
        }

        \Pterodactyl\Core\ActivityLogger::log('auth:checkpoint', $user['id'], [], $request);

        if ($request->isAjax()) {
            return Response::json(['complete' => true, 'redirect' => '/']);
        }
        return Response::redirect('/');
    }
}
