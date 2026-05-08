<?php
namespace Portal;

/** Tiny view helper. Renders PHP templates with optional layout. */
class View
{
    /** Render a view file with the given data. */
    public static function render(string $template, array $data = [], ?string $layout = 'layout'): void
    {
        $content = self::renderRaw($template, $data);
        if ($layout) {
            echo self::renderRaw($layout, array_merge($data, ['content' => $content]));
        } else {
            echo $content;
        }
    }

    /** Render a view to a string without applying any layout. */
    public static function renderRaw(string $template, array $data = []): string
    {
        $path = PORTAL_VIEWS . '/' . $template . '.php';
        if (!file_exists($path)) {
            throw new \RuntimeException("View not found: $template");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $path;
        return ob_get_clean();
    }

    public static function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}
