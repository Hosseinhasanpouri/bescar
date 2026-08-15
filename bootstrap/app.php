<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateMiddleware;
use App\Http\Middleware\BasicAuthMiddleware;
use App\Http\Middleware\CorsMiddleware;
use App\Http\Middleware\JsonMiddleware;
use App\Routing\Route;
use App\Routing\Router;
use App\Support\Env;

require __DIR__ . '/../vendor/autoload.php';

Env::load(dirname(__DIR__));

$router = new Router();

$router->middlewareAliases([
    'cors' => CorsMiddleware::class,
    'json' => JsonMiddleware::class,
    'auth' => AuthenticateMiddleware::class,
    'basic' => BasicAuthMiddleware::class,
]);

Route::setRouter($router);

require __DIR__ . '/../routes/api.php';

return $router;