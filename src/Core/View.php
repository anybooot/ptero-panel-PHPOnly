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

    public static function render(string $template, array $data = []): string
    {
        $view = new self();
        return $view->renderTemplate($template, array_merge(self::$shared, $data));
    }

    private function renderTemplate(string $template, array $data): string
    {
        $file = self::$baseDir . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $template) . '.php';
        if (!file_exists($file)) {
            throw new \RuntimeException("View template not found: {$file}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $file;
        $content = ob_get_clean() ?: '';

        while ($this->layout !== null) {
            $currentLayout = $this->layout;
            $this->layout = null;

            $layoutFile = self::$baseDir . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $currentLayout) . '.php';
            if (!file_exists($layoutFile)) {
                throw new \RuntimeException("Layout file not found: {$layoutFile}");
            }

            $this->sections['content'] = $content;
            ob_start();
            include $layoutFile;
            $content = ob_get_clean() ?: '';
        }

        return $content;
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
