<?php

namespace App\Core;

/**
 * Simple PHP-template view renderer with layouts.
 * Views live in app/Views; layouts in app/Views/layouts.
 */
class View
{
    public static function render(string $view, array $data = [], string $layout = 'app'): void
    {
        echo self::capture($view, $data, $layout);
    }

    public static function capture(string $view, array $data = [], string $layout = 'app'): string
    {
        $content = self::renderPartial($view, $data);

        if ($layout === '' ) {
            return $content;
        }

        $layoutFile = BASE_PATH . "/app/Views/layouts/{$layout}.php";
        if (!is_file($layoutFile)) {
            return $content;
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $layoutFile;   // layout echoes $content where needed
        return (string) ob_get_clean();
    }

    /** Render a view/partial without a layout and return the HTML. */
    public static function renderPartial(string $view, array $data = []): string
    {
        $file = BASE_PATH . "/app/Views/{$view}.php";
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: {$view}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}
