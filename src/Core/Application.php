<?php

declare(strict_types=1);

namespace Pterodactyl\Core;

use Pterodactyl\Routes;

/**
 * Main Application kernel.
 */
class Application
{
    private string $basePath;
    private Router $router;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
        $this->bootstrap();
    }

    private function bootstrap(): void
    {
        // Load configuration from .env
        Config::load($this->basePath);

        // Configure error reporting
        $debug = (bool)Config::get('APP_DEBUG', false);
        if ($debug) {
            ini_set('display_errors', '1');
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', '0');
            error_reporting(0);
        }

        // Set timezone
        $timezone = (string)Config::get('APP_TIMEZONE', 'UTC');
        date_default_timezone_set($timezone);

        // Configure Views
        View::setBaseDir($this->basePath . DIRECTORY_SEPARATOR . 'views');
        View::share('appUrl', Config::get('APP_URL', 'http://localhost'));

        // Initialize Router
        $this->router = new Router();
        Routes::register($this->router);
    }

    public function handle(Request $request): Response
    {
        $path = $request->getPath();
        $isAsset = str_starts_with($path, '/assets/') || str_starts_with($path, '/favicons/') || $path === '/favicon.ico' || $path === '/robots.txt';

        if ($isAsset) {
            $assetResponse = $this->serveStaticAsset($path);
            if ($assetResponse !== null) {
                return $assetResponse;
            }
        }

        $isSetupRoute = str_starts_with($path, '/setup') || $path === '/setup.php';

        if (!$isAsset) {
            $isInstalled = \Pterodactyl\Handlers\Setup\SetupHandler::isInstalled($this->basePath);
            if (!$isInstalled && !$isSetupRoute) {
                return Response::redirect('/setup');
            }
            if ($isInstalled && $isSetupRoute) {
                return Response::redirect('/auth/login');
            }
        }

        try {
            return $this->router->dispatch($request);
        } catch (\Throwable $e) {
            $debug = (bool)Config::get('APP_DEBUG', false);
            
            if ($request->isJson() || str_starts_with($request->getPath(), '/api/')) {
                $payload = [
                    'errors' => [
                        [
                            'code' => (new \ReflectionClass($e))->getShortName(),
                            'status' => '500',
                            'detail' => $debug ? $e->getMessage() : 'An internal server error occurred.',
                        ],
                    ],
                ];
                if ($debug) {
                    $payload['trace'] = explode("\n", $e->getTraceAsString());
                }
                return Response::json($payload, 500);
            }

            // Only show DB setup guide if the MySQL server connection itself failed or database is not reachable
            $isDbConnectionError = str_contains($e->getMessage(), 'Database connection error')
                || (str_contains($e->getMessage(), 'Connection refused') || str_contains($e->getMessage(), 'Access denied for user') || str_contains($e->getMessage(), 'Unknown database'));

            if ($isDbConnectionError) {
                return Response::html($this->renderDbErrorGuide($e->getMessage()), 500);
            }

            $msg = $debug ? $e->getMessage() : 'An unexpected internal server error occurred. Please try again later.';
            return Response::html(ErrorRenderer::render(500, $msg, 'Internal Server Error', $e), 500);
        }
    }

    private function renderDbErrorGuide(string $errorMessage): string
    {
        $safeError = htmlspecialchars($errorMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Database Configuration Required - Pterodactyl Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/pterodactyl.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
        .setup-card { max-width: 720px; width: 100%; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 32px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        .step-num { display: inline-flex; width: 24px; height: 24px; background: #4b5563; color: #fff; border-radius: 50%; font-size: 12px; font-weight: bold; align-items: center; justify-content: center; margin-right: 8px; }
        .code-box { background: var(--bg-base); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 12px; font-family: var(--font-mono); font-size: 12px; color: #10b981; overflow-x: auto; margin: 8px 0; }
    </style>
</head>
<body>
    <div class="setup-card">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
            <span style="font-size: 28px; color: #e5e7eb;">▲</span>
            <h1 style="font-size: 22px; font-weight: 700; color: #fff;">Pterodactyl Panel &bull; Database Configuration Required</h1>
        </div>

        <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444; padding: 12px 16px; margin-bottom: 24px; border-radius: 0 4px 4px 0;">
            <strong style="color: #ef4444;">MySQL/MariaDB Connection Error:</strong>
            <div style="color: var(--text-secondary); font-family: var(--font-mono); font-size: 13px; margin-top: 4px;">{$safeError}</div>
        </div>

        <p style="color: var(--text-primary); margin-bottom: 16px;">
            To get the panel running on this host, please complete these simple steps:
        </p>

        <div style="margin-bottom: 16px;">
            <strong style="color: #fff;"><span class="step-num">1</span> Create your <code>.env</code> configuration file</strong>
            <p style="color: var(--text-secondary); font-size: 13px; margin-left: 32px; margin-top: 4px;">
                Copy <code>.env.example</code> to <code>.env</code> and fill in your database credentials:
            </p>
            <div class="code-box" style="margin-left: 32px;">
DB_HOST=127.0.0.1<br>
DB_PORT=3306<br>
DB_DATABASE=panel_database<br>
DB_USERNAME=mysql_user<br>
DB_PASSWORD=secret_password<br>
APP_URL=http://your-domain.com<br>
APP_KEY=base64:... (generate with: php cli.php key:generate)
            </div>
        </div>

        <div style="margin-bottom: 16px;">
            <strong style="color: #fff;"><span class="step-num">2</span> Import database schema (if this is a new installation)</strong>
            <p style="color: var(--text-secondary); font-size: 13px; margin-left: 32px; margin-top: 4px;">
                Run the database migration command or the interactive installer in your terminal:
            </p>
            <div class="code-box" style="margin-left: 32px;">php cli.php db:migrate<br># Or run the full setup wizard:<br>php cli.php install</div>
        </div>

        <div style="margin-bottom: 24px;">
            <strong style="color: #fff;"><span class="step-num">3</span> Create an Administrator account</strong>
            <p style="color: var(--text-secondary); font-size: 13px; margin-left: 32px; margin-top: 4px;">
                Run the user creation command from your terminal:
            </p>
            <div class="code-box" style="margin-left: 32px;">php cli.php user:make</div>
        </div>

        <div style="text-align: right;">
            <button onclick="window.location.reload();" class="btn btn-primary" style="padding: 10px 20px;">Retry Connection</button>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function serveStaticAsset(string $path): ?Response
    {
        if (str_contains($path, '..')) {
            return null;
        }

        $candidates = [
            $this->basePath . DIRECTORY_SEPARATOR . 'public' . str_replace('/', DIRECTORY_SEPARATOR, $path),
            $this->basePath . str_replace('/', DIRECTORY_SEPARATOR, $path),
        ];

        $filePath = null;
        foreach ($candidates as $candidate) {
            if (file_exists($candidate) && !is_dir($candidate)) {
                $filePath = $candidate;
                break;
            }
        }

        if ($filePath === null) {
            return null;
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'css'   => 'text/css; charset=utf-8',
            'js'    => 'application/javascript; charset=utf-8',
            'svg'   => 'image/svg+xml',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'ico'   => 'image/x-icon',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'map'   => 'application/json',
            'json'  => 'application/json',
        ];

        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        return new Response($content, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string)strlen($content),
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
