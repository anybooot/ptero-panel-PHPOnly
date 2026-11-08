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
        View::share('appName', Config::get('APP_NAME', 'Pterodactyl'));
        View::share('appUrl', Config::get('APP_URL', 'http://localhost'));

        // Initialize Router
        $this->router = new Router();
        Routes::register($this->router);
    }

    public function handle(Request $request): Response
    {
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

            // Check if this is a Database Connection Error or missing configuration
            $isDbError = str_contains($e->getMessage(), 'Database connection error') || $e instanceof \PDOException;
            if ($isDbError) {
                return Response::html($this->renderDbErrorGuide($e->getMessage()), 500);
            }

            $msg = $debug ? nl2br(htmlspecialchars($e->getMessage() . "\n\n" . $e->getTraceAsString())) : 'An internal error occurred. Please try again later.';
            return Response::html("<h1>500 Internal Server Error</h1><p>{$msg}</p>", 500);
        }
    }

    private function renderDbErrorGuide(string $errorMessage): string
    {
        $safeError = htmlspecialchars($errorMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return <<<HTML
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>Configurare Bază de Date - Pterodactyl Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/pterodactyl.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
        .setup-card { max-width: 720px; width: 100%; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 32px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        .step-num { display: inline-flex; width: 24px; height: 24px; background: var(--color-primary); color: #fff; border-radius: 50%; font-size: 12px; font-weight: bold; align-items: center; justify-content: center; margin-right: 8px; }
        .code-box { background: var(--bg-base); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 12px; font-family: var(--font-mono); font-size: 12px; color: #10b981; overflow-x: auto; margin: 8px 0; }
    </style>
</head>
<body>
    <div class="setup-card">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
            <span style="font-size: 28px; color: #3b82f6;">▲</span>
            <h1 style="font-size: 22px; font-weight: 700; color: #fff;">Pterodactyl Panel &bull; Necesită Configurare Bază de Date</h1>
        </div>

        <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444; padding: 12px 16px; margin-bottom: 24px; border-radius: 0 4px 4px 0;">
            <strong style="color: #ef4444;">Eroare conectare MySQL/MariaDB:</strong>
            <div style="color: var(--text-secondary); font-family: var(--font-mono); font-size: 13px; margin-top: 4px;">{$safeError}</div>
        </div>

        <p style="color: var(--text-primary); margin-bottom: 16px;">
            Pentru ca panelul să funcționeze pe acest host, urmează acești pași simpli:
        </p>

        <div style="margin-bottom: 16px;">
            <strong style="color: #fff;"><span class="step-num">1</span> Creează fișierul de configurare <code>.env</code></strong>
            <p style="color: var(--text-secondary); font-size: 13px; margin-left: 32px; margin-top: 4px;">
                Copiază fișierul <code>.env.example</code> în <code>.env</code> și completează datele bazei de date:
            </p>
            <div class="code-box" style="margin-left: 32px;">
DB_HOST=127.0.0.1<br>
DB_PORT=3306<br>
DB_DATABASE=nume_baza_de_date<br>
DB_USERNAME=utilizator_mysql<br>
DB_PASSWORD=parola_secreta<br>
APP_URL=http://domeniul-tau.ro<br>
APP_KEY=base64:... (generează cu: php cli.php key:generate)
            </div>
        </div>

        <div style="margin-bottom: 16px;">
            <strong style="color: #fff;"><span class="step-num">2</span> Importă structura bazei de date (dacă e instalare nouă)</strong>
            <p style="color: var(--text-secondary); font-size: 13px; margin-left: 32px; margin-top: 4px;">
                Dacă baza de date este nouă, importă fișierul <code>database/schema/mysql-schema.sql</code> în MySQL/MariaDB (prin phpMyAdmin sau terminal).
            </p>
        </div>

        <div style="margin-bottom: 24px;">
            <strong style="color: #fff;"><span class="step-num">3</span> Creează contul de Administrator</strong>
            <p style="color: var(--text-secondary); font-size: 13px; margin-left: 32px; margin-top: 4px;">
                Rulează din terminal comanda:
            </p>
            <div class="code-box" style="margin-left: 32px;">php cli.php user:make</div>
        </div>

        <div style="text-align: right;">
            <button onclick="window.location.reload();" class="btn btn-primary" style="padding: 10px 20px;">Reîncearcă Conexiunea</button>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
