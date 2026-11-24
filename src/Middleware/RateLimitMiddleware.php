<?php

declare(strict_types=1);

namespace Pterodactyl\Middleware;

use Pterodactyl\Core\ErrorRenderer;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Security\RateLimiter;

/**
 * Global and route-level rate limiting middleware.
 * Returns HTTP 429 Too Many Requests when limits are exceeded.
 */
class RateLimitMiddleware
{
    private int $maxAttempts;
    private int $decaySeconds;

    public function __construct(int $maxAttempts = 120, int $decaySeconds = 60)
    {
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
    }

    public function handle(Request $request, callable $next): Response
    {
        // Don't throttle static assets
        $path = $request->getPath();
        if (str_starts_with($path, '/assets/') || str_starts_with($path, '/favicons/') || $path === '/favicon.ico' || $path === '/robots.txt') {
            return $next($request);
        }

        // Daemon API has higher throughput for Wings communication
        $isDaemon = str_starts_with($path, '/api/remote');
        $max = $isDaemon ? 600 : $this->maxAttempts;

        $ip = $request->getClientIp();
        $key = 'req_rl:' . $ip . ':' . ($isDaemon ? 'daemon' : (str_starts_with($path, '/api/') ? 'api' : 'web'));

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $retryAfter = RateLimiter::availableIn($key);

            $headers = [
                'X-RateLimit-Limit' => (string)$max,
                'X-RateLimit-Remaining' => '0',
                'Retry-After' => (string)$retryAfter,
            ];

            if ($request->isJson() || str_starts_with($path, '/api/')) {
                return Response::json([
                    'errors' => [
                        [
                            'code' => 'TooManyRequestsHttpException',
                            'status' => '429',
                            'detail' => "Too many requests. Please slow down and try again in {$retryAfter} seconds.",
                        ],
                    ],
                ], 429, $headers);
            }

            return Response::html(
                ErrorRenderer::render(429, "Too many requests from your IP address. Please wait {$retryAfter} seconds before trying again.", 'Too Many Requests'),
                429,
                $headers
            );
        }

        $currentAttempts = RateLimiter::hit($key, $this->decaySeconds);
        $remaining = max(0, $this->maxAttempts - $currentAttempts);

        /** @var Response $response */
        $response = $next($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string)$this->maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string)$remaining);
    }
}
