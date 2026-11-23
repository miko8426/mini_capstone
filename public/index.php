<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Helpers\View;

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = is_string($uri) ? $uri : '/';
$uri = '/' . trim($uri, '/');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$routes = require __DIR__ . '/../routes/web.php';

foreach ($routes[$method] ?? [] as $pattern => $handler) {
    $regex = '#^' . preg_replace('#\{[a-zA-Z_]+\}#', '(\d+)', $pattern) . '$#';

    if (preg_match($regex, $uri, $matches) !== 1) {
        continue;
    }

    array_shift($matches);

    [$controllerName, $action] = explode('@', $handler, 2);
    $controllerClass = 'App\\Controllers\\' . $controllerName;

    if (!class_exists($controllerClass) || !method_exists($controllerClass, $action)) {
        break;
    }

    $controller = new $controllerClass();
    $controller->{$action}(...array_map('intval', $matches));
    return;
}

http_response_code(404);
View::render('errors/404', [
    'pageTitle' => '404 Not Found',
    'active'    => '',
    'message'   => 'The page you are looking for does not exist.',
]);

