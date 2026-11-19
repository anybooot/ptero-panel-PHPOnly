<?php

declare(strict_types=1);

namespace Pterodactyl\Core;

/**
 * Pure PHP Template Engine with layout inheritance, sections, and escaping.
 */
class View
{
    private static string $baseDir = '';
    private static array $shared = [];

    private ?string $layout = null;
    private array $sections = [];
    private string $currentSection = '';

    public static function setBaseDir(string $dir): void
    {
        self::$baseDir = rtrim($dir, '/\\');
    }

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function getAppName(): string
    {
        try {
            $setting = Database::queryOne("SELECT `value` FROM `settings` WHERE `key` = 'settings::app:name' LIMIT 1");
            if (!empty($setting['value'])) {
                return (string)$setting['value'];
            }
        } catch (\Throwable) {
        }
        return (string)Config::get('APP_NAME', 'Pterodactyl');
    }

    public static function render(string $template, array $data = []): string
    {
        if (!isset($data['appName'])) {
            $data['appName'] = self::getAppName();
        }

        $view = new self();
        return $view->renderTemplate($template, array_merge(self::$shared, $data));
    }

    private function renderTemplate(string $__template, array $__data): string
    {
        $__viewFile = self::$baseDir . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $__template) . '.php';
        if (!file_exists($__viewFile)) {
            throw new \RuntimeException("View template not found: {$__viewFile}");
        }

        extract($__data, EXTR_OVERWRITE);

        ob_start();
        include $__viewFile;
        $__content = ob_get_clean() ?: '';

        while ($this->layout !== null) {
            $__currentLayout = $this->layout;
            $this->layout = null;

            $__layoutFile = self::$baseDir . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $__currentLayout) . '.php';
            if (!file_exists($__layoutFile)) {
                throw new \RuntimeException("Layout file not found: {$__layoutFile}");
            }

            $this->sections['content'] = $__content;
            $content = $__content;
            ob_start();
            include $__layoutFile;
            $__content = ob_get_clean() ?: '';
        }

        return $__content;
    }

    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    public function start(string $section): void
    {
        $this->currentSection = $section;
        ob_start();
    }

    public function end(): void
    {
        $content = ob_get_clean() ?: '';
        $this->sections[$this->currentSection] = $content;
        $this->currentSection = '';
    }

    public function yield(string $section, string $default = ''): string
    {
        return $this->sections[$section] ?? $default;
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function csrf(): string
    {
        $token = Session::getCsrfToken();
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}
