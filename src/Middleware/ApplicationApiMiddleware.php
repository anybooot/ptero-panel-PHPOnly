<?php

declare(strict_types=1);

namespace Pterodactyl\Middleware;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\Session;
use Pterodactyl\Security\ApiKeyAuth;

/**
 * Ensures requests to /api/application/* are authenticated via Application API Token or Root Admin Session.
 */
class ApplicationApiMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        // 1. Authenticate via Application API Key
        $apiUser = ApiKeyAuth::authenticate($request, ApiKeyAuth::TYPE_APPLICATION);
        if ($apiUser) {
            $request->setAttribute('user', $apiUser);
            $request->setAttribute('api_key', $apiUser['api_key'] ?? null);
            return $next($request);
        }

        // 2. Authenticate via Root Admin Session
        Session::start();
        $userId = Session::get('user_id');
        if ($userId && Session::get('2fa_pending') !== true) {
            $user = Database::queryOne("SELECT * FROM `users` WHERE `id` = :id LIMIT 1", ['id' => $userId]);
            if ($user && !empty($user['root_admin']) && ((int)$user['root_admin'] === 1)) {
                $request->setAttribute('user', $user);
                return $next($request);
            }
        }

        $bearer = $request->getBearerToken();
        if (!$bearer) {
            return Response::json([
                'errors' => [
                    [
                        'code' => 'UnauthorizedHttpException',
                        'status' => '401',
                        'detail' => 'Application API requires Authorization Bearer token or root administrator session.',
                    ],
                ],
            ], 401);
        }

        return Response::json([
            'errors' => [
                [
                    'code' => 'AccessDeniedHttpException',
                    'status' => '403',
                    'detail' => 'Invalid or expired Application API key provided.',
                ],
            ],
        ], 403);
    }
}
