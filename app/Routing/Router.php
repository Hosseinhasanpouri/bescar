<?php

declare(strict_types=1);

namespace App\Routing;

use App\Http\Request;
use App\Http\Response;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use RuntimeException;

use function FastRoute\simpleDispatcher;

class Router
{
    /** @var list<RouteDefinition> */
    private array $routes = [];

    /** @var array<string, RouteDefinition> */
    private array $namedRoutes = [];

    private array $groupStack = [];

    private array $middlewareAliases = [];

    public function middlewareAliases(array $aliases): self
    {
        $this->middlewareAliases = array_merge($this->middlewareAliases, $aliases);

        return $this;
    }

    public function get(string $uri, callable|array|string $action): RouteDefinition
    {
        return $this->addRoute(['GET'], $uri, $action);
    }

    public function post(string $uri, callable|array|string $action): RouteDefinition
    {
        return $this->addRoute(['POST'], $uri, $action);
    }

    public function put(string $uri, callable|array|string $action): RouteDefinition
    {
        return $this->addRoute(['PUT'], $uri, $action);
    }

    public function patch(string $uri, callable|array|string $action): RouteDefinition
    {
        return $this->addRoute(['PATCH'], $uri, $action);
    }

    public function delete(string $uri, callable|array|string $action): RouteDefinition
    {
        return $this->addRoute(['DELETE'], $uri, $action);
    }

    public function options(string $uri, callable|array|string $action): RouteDefinition
    {
        return $this->addRoute(['OPTIONS'], $uri, $action);
    }

    public function any(string $uri, callable|array|string $action): RouteDefinition
    {
        return $this->addRoute(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $uri, $action);
    }

    public function match(array $methods, string $uri, callable|array|string $action): RouteDefinition
    {
        return $this->addRoute(array_map('strtoupper', $methods), $uri, $action);
    }

    /**
     * @param  array{prefix?: string, middleware?: string|array}  $attributes
     */
    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    public function addRoute(array $methods, string $uri, callable|array|string $action): RouteDefinition
    {
        $prefix = '';
        $middleware = [];

        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim((string) $group['prefix'], '/');
            }

            if (isset($group['middleware'])) {
                $groupMiddleware = is_array($group['middleware'])
                    ? $group['middleware']
                    : [$group['middleware']];
                $middleware = array_merge($middleware, $groupMiddleware);
            }
        }

        $route = new RouteDefinition(
            methods: $methods,
            uri: $uri,
            action: $action,
            middleware: $middleware,
            prefix: $prefix,
        );

        $this->routes[] = $route;

        return $route;
    }

    public function dispatch(Request $request): Response
    {
        $dispatcher = simpleDispatcher(function (RouteCollector $r): void {
            foreach ($this->routes as $route) {
                $uri = $route->fullUri();

                // Laravel {param} works as-is; {param?} becomes FastRoute optional [/...]
                $fastUri = preg_replace('#/\{(\w+)\?\}#', '[/{$1}]', $uri) ?? $uri;
                $fastUri = preg_replace('#^\{(\w+)\?\}#', '[{$1}]', $fastUri) ?? $fastUri;

                foreach ($route->methods as $method) {
                    $r->addRoute($method, $fastUri, $route);
                }

                if ($route->name !== null) {
                    $this->namedRoutes[$route->name] = $route;
                }
            }
        });

        $routeInfo = $dispatcher->dispatch($request->method, $request->uri);

        return match ($routeInfo[0]) {
            Dispatcher::NOT_FOUND => Response::json([
                'message' => 'یافت نشد',
            ], 404),
            Dispatcher::METHOD_NOT_ALLOWED => Response::json([
                'message' => 'متد مجاز نیست',
                'allowed' => $routeInfo[1],
            ], 405),
            Dispatcher::FOUND => $this->runRoute($request, $routeInfo[1], $routeInfo[2]),
            default => Response::json(['message' => 'خطای سرور'], 500),
        };
    }

    private function runRoute(Request $request, RouteDefinition $route, array $params): Response
    {
        $request->routeParams = $params;

        $handler = function (Request $request) use ($route, $params): Response {
            return $this->invokeAction($route->action, $request, $params);
        };

        $pipeline = array_reverse($this->resolveMiddleware($route->middleware));

        $next = $handler;
        foreach ($pipeline as $middleware) {
            $previous = $next;
            $next = static function (Request $request) use ($middleware, $previous): Response {
                return $middleware->handle($request, $previous);
            };
        }

        $result = $next($request);

        return $result instanceof Response ? $result : Response::json($result);
    }

    private function resolveMiddleware(array $middleware): array
    {
        $resolved = [];

        foreach ($middleware as $item) {
            if (is_object($item)) {
                $resolved[] = $item;
                continue;
            }

            $name = (string) $item;
            $class = $this->middlewareAliases[$name] ?? $name;

            if (! class_exists($class)) {
                throw new RuntimeException("Middleware [{$name}] not found.");
            }

            $resolved[] = new $class();
        }

        return $resolved;
    }

    private function invokeAction(callable|array|string $action, Request $request, array $params): Response
    {
        if (is_callable($action) && ! is_array($action)) {
            $result = $action($request, ...array_values($params));

            return $result instanceof Response ? $result : Response::json($result);
        }

        if (is_string($action)) {
            if (! str_contains($action, '@')) {
                throw new RuntimeException("Invalid action string [{$action}]. Use Controller@method.");
            }

            [$class, $method] = explode('@', $action, 2);
            $action = [$class, $method];
        }

        if (is_array($action)) {
            [$class, $method] = $action;

            if (! class_exists($class)) {
                throw new RuntimeException("Controller [{$class}] not found.");
            }

            $controller = new $class();

            if (! method_exists($controller, $method)) {
                throw new RuntimeException("Method [{$class}@{$method}] not found.");
            }

            $result = $controller->{$method}($request, ...array_values($params));

            return $result instanceof Response ? $result : Response::json($result);
        }

        throw new RuntimeException('Invalid route action.');
    }

    /** @return list<RouteDefinition> */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
