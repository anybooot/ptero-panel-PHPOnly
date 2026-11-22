<?php

declare(strict_types=1);

namespace Pterodactyl\Middleware;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\Session;

/**
 * Ensures user is authenticated via session or API token.
 */
class AuthMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        // 1. Prioritize Bearer token authentication for API requests
        $bearer = $request->getBearerToken();
        if ($bearer !== null && $bearer !== '') {
            $apiUser = \Pterodactyl\Security\ApiKeyAuth::authenticate($request, \Pterodactyl\Security\ApiKeyAuth::TYPE_CLIENT);
            if ($apiUser) {
                $request->setAttribute('user', $apiUser);
                $request->setAttribute('api_key', $apiUser['api_key'] ?? null);
                return $next($request);
            }

            // If an explicit API key was provided and is invalid or revoked, return 401
            return Response::json([
                'errors' => [
                    [
                        'code' => 'UnauthorizedHttpException',
                        'status' => '401',
                        'detail' => 'The API key provided is invalid, revoked, or IP restricted.',
                    ],
                ],
            ], 401);
        }

        // 2. Session authentication for web / cookie users
        Session::start();
        $userId = Session::get('user_id');

        if (!$userId) {
            if ($request->isJson() || str_starts_with($request->getPath(), '/api/')) {
                return Response::json([
                    'errors' => [
                        [
                            'code' => 'UnauthorizedHttpException',
                            'status' => '401',
                            'detail' => 'You must be logged in or provide a valid API key to access this resource.',
                        ],
                    ],
                ], 401);
            }
            return Response::redirect('/auth/login');
        }

        $user = Database::queryOne("SELECT * FROM `users` WHERE `id` = :id LIMIT 1", ['id' => $userId]);
        if (!$user) {
            Session::destroy();
            if ($request->isJson() || str_starts_with($request->getPath(), '/api/')) {
                return Response::json(['errors' => [['code' => 'UnauthorizedHttpException', 'status' => '401', 'detail' => 'Session user not found.']]], 401);
            }
            return Response::redirect('/auth/login');
        }

        // Check if 2FA checkpoint is pending
        if (Session::get('2fa_pending') === true && !str_starts_with($request->getPath(), '/auth/')) {
            if ($request->isJson() || str_starts_with($request->getPath(), '/api/')) {
                return Response::json(['errors' => [['code' => 'TwoFactorRequiredException', 'status' => '403', 'detail' => 'Two-factor checkpoint pending.']]], 403);
            }
            return Response::redirect('/auth/checkpoint');
        }

        $request->setAttribute('user', $user);
        return $next($request);
    }
}
