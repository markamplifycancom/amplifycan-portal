<?php
namespace Portal;

/**
 * Tiny router. Routes are registered with HTTP method + path; path can include
 * placeholders like /orders/{id}. Handlers are 'Controller@method' strings.
 */
class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, handler:string, params:array<int,string>}> */
    private array $routes = [];

    public function get(string $path, string $handler): void    { $this->add('GET', $path, $handler); }
    public function post(string $path, string $handler): void   { $this->add('POST', $path, $handler); }

    private function add(string $method, string $path, string $handler): void
    {
        // Convert /orders/{id} -> #^/orders/([^/]+)$#
        $params = [];
        $regex = preg_replace_callback('#\{([^/]+)\}#', function ($m) use (&$params) {
            $params[] = $m[1];
            return '([^/]+)';
        }, $path);
        $regex = '#^' . $regex . '$#';

        $this->routes[] = compact('method', 'regex', 'handler', 'params') + ['pattern' => $path];
    }

    public function dispatch(string $method, string $path): void
    {
        // Strip query string
        $path = parse_url($path, PHP_URL_PATH) ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;
            if (!preg_match($route['regex'], $path, $matches)) continue;

            array_shift($matches); // drop full match
            $args = array_combine($route['params'], $matches) ?: [];
            $this->invoke($route['handler'], $args);
            return;
        }

        http_response_code(404);
        echo "<h1>404 Not Found</h1>";
    }

    private function invoke(string $handler, array $args): void
    {
        [$controllerName, $method] = explode('@', $handler);
        $class = "\\Portal\\Controllers\\$controllerName";
        $controller = new $class();
        call_user_func_array([$controller, $method], [$args]);
    }
}
