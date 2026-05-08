<?php
/**
 * Front controller — every request hits this file.
 * In production, nginx rewrites everything to here.
 * In dev, PHP's built-in server (`php -S localhost:8000 -t public`) handles routing
 * to actual asset files (css/js) automatically and falls back to this for everything else.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/App.php';

\Portal\App::boot();

$router = new \Portal\Router();
\Portal\App::routes($router);
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
