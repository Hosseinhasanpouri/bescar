<?php

declare(strict_types=1);

namespace App\Routing;

use App\Http\Request;
use App\Http\Response;

/**
 * Laravel-style static facade over the shared Router instance.
 */
class Route
{
    private static ?Router $router = null;

    public static function setRouter(Router $router): void
    {
        self::$router = $router;
    }

    public static function router(): Router
    {
        if (self::$router === null) {
            throw new \RuntimeException('Router has not been bootstrapped.');
        }

        return self::$router;
    }

    public static function get(string $uri, callable|array|string $action): RouteDefinition
    {
        return self::router()->get($uri, $action);
    }

    public static function post(string $uri, callable|array|string $action): RouteDefinition
    {
        return self::router()->post($uri, $action);
    }

    public static function put(string $uri, callable|array|string $action): RouteDefinition
    {
        return self::router()->put($uri, $action);
    }

    public static function patch(string $uri, callable|array|string $action): RouteDefinition
    {
        return self::router()->patch($uri, $action);
    }

    public static function delete(string $uri, callable|array|string $action): RouteDefinition
    {
        return self::router()->delete($uri, $action);
    }

    public static function options(string $uri, callable|array|string $action): RouteDefinition
    {
        return self::router()->options($uri, $action);
    }

    public static function any(string $uri, callable|array|string $action): RouteDefinition
    {
        return self::router()->any($uri, $action);
    }

    public static function match(array $methods, string $uri, callable|array|string $action): RouteDefinition
    {
        return self::router()->match($methods, $uri, $action);
    }

    public static function group(array $attributes, callable $callback): void
    {
        self::router()->group($attributes, $callback);
    }

    public static function dispatch(Request $request): Response
    {
        return self::router()->dispatch($request);
    }
}
