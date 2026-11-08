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

        $error = Session::getFlash('error');
        $html = View::render('auth.login', ['error' => $error]);
        return Response::html($html);
    }

    public function login(Request $request): Response
    {
        Session::start();
        $userIdentifier = trim((string)$request->input('user', ''));
        $password = (string)$request->input('password', '');

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
            if ($request->isAjax()) {
                return Response::json(['error' => 'The credentials provided do not match our records.'], 400);
            }
            Session::flash('error', 'The credentials provided do not match our records.');
            return Response::redirect('/auth/login');
        }

        // Check 2FA
        $has2fa = !empty($user['totp_authenticated']) && !empty($user['totp_secret']);
        if ($has2fa) {
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
        Database::insert('activity_logs', [
            'ip' => $request->getClientIp(),
            'event' => 'auth:login',
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

    public function logout(Request $request): Response
    {
        Session::start();
        $userId = Session::get('user_id');
        if ($userId) {
            Database::insert('activity_logs', [
                'ip' => $request->getClientIp(),
                'event' => 'auth:logout',
                'properties' => json_encode([]),
                'timestamp' => date('Y-m-d H:i:s'),
                'actor_id' => $userId,
                'actor_type' => 'user',
            ]);
        }

        Session::destroy();
        return Response::redirect('/auth/login');
    }
}
