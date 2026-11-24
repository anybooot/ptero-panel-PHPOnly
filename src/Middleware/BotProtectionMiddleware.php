<?php

declare(strict_types=1);

namespace Pterodactyl\Middleware;

use Pterodactyl\Core\ErrorRenderer;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Security\BotProtection;

/**
 * Middleware that blocks automated vulnerability scanners and malicious exploitation tools.
 */
class BotProtectionMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (BotProtection::isMaliciousScanner($request)) {
            if ($request->isJson() || str_starts_with($request->getPath(), '/api/')) {
                return Response::json(['error' => 'Automated scanner access forbidden.'], 403);
            }
            return Response::html(ErrorRenderer::render(403, 'Automated security scanners and exploit tools are forbidden from accessing this service.', 'Access Denied'), 403);
        }

        return $next($request);
    }
}
