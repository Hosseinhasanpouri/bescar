<?php

declare(strict_types=1);

namespace App\Routing;

class RouteDefinition
{
    public function __construct(
        public readonly array $methods,
        public readonly string $uri,
        public mixed $action,
        public array $middleware = [],
        public ?string $name = null,
        public string $prefix = '',
    ) {
    }

    public function middleware(string|array $middleware): self
    {
        $this->middleware = array_merge(
            $this->middleware,
            is_array($middleware) ? $middleware : [$middleware]
        );

        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function fullUri(): string
    {
        $uri = '/' . trim($this->prefix . '/' . trim($this->uri, '/'), '/');

        return $uri === '' ? '/' : $uri;
    }
}
