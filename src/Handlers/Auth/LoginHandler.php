<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Auth;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\Session;
use Pterodactyl\Core\View;

/**
 * Handles user login and session initialization.
 */
class LoginHandler
{
    public function showLogin(Request $request): Response
    {
        Session::start();
        if (Session::has('user_id') && Session::get('2fa_pending') !== true) {
            return Response::redirect('/');
        }

        $appName = \Pterodactyl\Handlers\Admin\SettingsHandler::getSetting('settings::app:name', \Pterodactyl\Core\Config::get('APP_NAME', 'Pterodactyl'));
        $registrationEnabled = \Pterodactyl\Handlers\Admin\SettingsHandler::getSetting('settings::auth:registration_enabled', 'false') === 'true';

        $error = Session::getFlash('error');
        $success = Session::getFlash('success');
        $html = View::render('auth.login', [
            'error' => $error,
            'success' => $success,
            'appName' => $appName,
            'registrationEnabled' => $registrationEnabled,
        ]);
        return Response::html($html);
    }

    public function login(Request $request): Response
    {
        Session::start();

        // 1. Bot Protection Check
        if ($botError = \Pterodactyl\Security\BotProtection::validateForm($request)) {
            if ($request->isAjax()) {
                return Response::json(['error' => $botError], 400);
            }
            Session::flash('error', $botError);
            return Response::redirect('/auth/login');
        }

        $userIdentifier = trim((string)$request->input('user', ''));
        $password = (string)$request->input('password', '');
        $ip = $request->getClientIp();

        $ipKey = 'login_ip:' . $ip;
        $userKey = 'login_user:' . $ip . ':' . strtolower($userIdentifier);

        // 2. Rate Limiting on Login (5 attempts per user/IP, 15 attempts per IP in 5 minutes)
        if (\Pterodactyl\Security\RateLimiter::tooManyAttempts($ipKey, 15)) {
            $secs = \Pterodactyl\Security\RateLimiter::availableIn($ipKey);
            $msg = "Too many failed login attempts from your IP address. Please wait {$secs} seconds before trying again.";
            if ($request->isAjax()) {
                return Response::json(['error' => $msg], 429);
            }
            Session::flash('error', $msg);
            return Response::redirect('/auth/login');
        }

        if ($userIdentifier !== '' && \Pterodactyl\Security\RateLimiter::tooManyAttempts($userKey, 5)) {
            $secs = \Pterodactyl\Security\RateLimiter::availableIn($userKey);
            $msg = "Too many failed login attempts for this account. Please wait {$secs} seconds before trying again.";
            if ($request->isAjax()) {
                return Response::json(['error' => $msg], 429);
            }
            Session::flash('error', $msg);
            return Response::redirect('/auth/login');
        }

        if ($userIdentifier === '' || $password === '') {
            if ($request->isAjax()) {
                return Response::json(['error' => 'Please enter both your username/email and password.'], 400);
            }
            Session::flash('error', 'Please enter both your username/email and password.');
            return Response::redirect('/auth/login');
        }

        $user = Database::queryOne(
            "SELECT * FROM `users` WHERE `email` = :param1 OR `username` = :param2 LIMIT 1",
            ['param1' => $userIdentifier, 'param2' => $userIdentifier]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            \Pterodactyl\Security\RateLimiter::hit($ipKey, 300);
            \Pterodactyl\Security\RateLimiter::hit($userKey, 300);

            if ($request->isAjax()) {
                return Response::json(['error' => 'The credentials provided do not match our records.'], 400);
            }
            Session::flash('error', 'The credentials provided do not match our records.');
            return Response::redirect('/auth/login');
        }

        // Credentials are valid - clear login throttles
        \Pterodactyl\Security\RateLimiter::clear($ipKey);
        \Pterodactyl\Security\RateLimiter::clear($userKey);

        // Regenerate session to eliminate session fixation
        Session::regenerate(true);

        // Check 2FA
        $has2fa = (!empty($user['use_totp']) || !empty($user['totp_authenticated'])) && !empty($user['totp_secret']);
        if ($has2fa) {
            // Check for valid 30-day remembered device
            if ($this->is2faRemembered($user)) {
                Session::set('user_id', $user['id']);
                Session::remove('2fa_pending');
                Session::remove('2fa_user_id');

                \Pterodactyl\Core\ActivityLogger::log('auth:login', $user['id'], [], $request);

                if ($request->isAjax()) {
                    return Response::json(['complete' => true, 'redirect' => '/']);
                }
                return Response::redirect('/');
            }

            Session::set('2fa_user_id', $user['id']);
            Session::set('2fa_pending', true);

            if ($request->isAjax()) {
                return Response::json(['complete' => false, 'redirect' => '/auth/checkpoint']);
            }
            return Response::redirect('/auth/checkpoint');
        }

        // Login success
        Session::set('user_id', $user['id']);
        Session::remove('2fa_pending');
        Session::remove('2fa_user_id');

        // Log audit event
        \Pterodactyl\Core\ActivityLogger::log('auth:login', $user['id'], [], $request);

        if ($request->isAjax()) {
            return Response::json(['complete' => true, 'redirect' => '/']);
        }
        return Response::redirect('/');
    }

    public function logout(Request $request): Response
    {
        Session::start();
        $userId = Session::get('user_id');
        if ($userId) {
            \Pterodactyl\Core\ActivityLogger::log('auth:logout', $userId, [], $request);
        }

        Session::destroy();
        return Response::redirect('/auth/login');
    }

    private function is2faRemembered(array $user): bool
    {
        $rawCookie = $_COOKIE['pterodactyl_2fa_remember'] ?? null;
        if (empty($rawCookie)) {
            return false;
        }

        try {
            $data = json_decode((string)base64_decode((string)$rawCookie), true);
            if (!is_array($data)) {
                return false;
            }

            if (((int)($data['user_id'] ?? 0)) !== ((int)$user['id'])) {
                return false;
            }

            $expiry = (int)($data['expiry'] ?? 0);
            if ($expiry < time()) {
                return false;
            }

            $appKey = (string)\Pterodactyl\Core\Config::get('APP_KEY', 'default_ptero_key');
            $expectedSignature = hash_hmac('sha256', (string)$user['id'] . '|' . $expiry . '|' . (string)$user['totp_secret'], $appKey);

            return hash_equals($expectedSignature, (string)($data['signature'] ?? ''));
        } catch (\Throwable) {
            return false;
        }
    }
}
