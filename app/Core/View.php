<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

class View
{
    public static function render(string $template, array $data = [], ?string $layout = 'app'): string
    {
        $baseDir = dirname(__DIR__, 2) . '/app/Views/';
        $templatePath = $baseDir . ltrim($template, '/') . '.php';

        if (!file_exists($templatePath)) {
            throw new RuntimeException("View template not found: {$templatePath}");
        }

        // Render template content
        $content = self::renderPhpFile($templatePath, $data);

        // Render with layout if specified
        if ($layout !== null && $layout !== '') {
            $layoutName = str_starts_with($layout, 'layouts/') ? $layout : "layouts/{$layout}";
            $layoutPath = $baseDir . $layoutName . '.php';

            if (file_exists($layoutPath)) {
                $layoutData = array_merge($data, ['content' => $content]);
                return self::renderPhpFile($layoutPath, $layoutData);
            }
        }

        return $content;
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Generate versioned asset URL with cache-busting query parameter (?v=hash).
     */
    public static function asset(string $path): string
    {
        $cleanPath = '/' . ltrim($path, '/');
        $realPath = dirname(__DIR__, 2) . '/public' . $cleanPath;

        if (file_exists($realPath)) {
            $mtime = (int)@filemtime($realPath);
            $size = (int)@filesize($realPath);
            $hash = substr(md5((string)$mtime . $size), 0, 8);
            return $cleanPath . '?v=' . $hash;
        }

        return $cleanPath;
    }

    private static function renderPhpFile(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);

        ob_start();
        try {
            require $file;
            return (string)ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
