<?php

declare(strict_types=1);

namespace App\Http;

class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly array $query,
        public readonly array $body,
        public readonly array $headers,
        public readonly array $server,
        public readonly array $files = [],
        public array $routeParams = [],
        private mixed $user = null,
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Support method spoofing via _method (POST forms) or X-HTTP-Method-Override
        if ($method === 'POST') {
            $override = $_POST['_method']
                ?? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']
                ?? null;

            if (is_string($override) && $override !== '') {
                $method = strtoupper($override);
            }
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/');

        // Strip subdirectory base path (e.g. /three/cursor/backend when not using a vhost)
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = str_replace('\\', '/', dirname($scriptName));
        $basePath = rtrim($basePath, '/');

        if ($basePath !== '' && $basePath !== '/' && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath)) ?: '/';
        }

        $uri = '/' . trim($uri, '/');
        if ($uri !== '/') {
            $uri = rtrim($uri, '/') ?: '/';
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = $value;
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }

        // Apache/CGI often omits Authorization from HTTP_* — recover it.
        $authorization = self::resolveAuthorizationHeader($_SERVER, $headers);
        if ($authorization !== null) {
            $headers['Authorization'] = $authorization;
        }

        $contentType = $headers['Content-Type'] ?? '';
        $body = $_POST;

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw ?: '', true);
            $body = is_array($decoded) ? $decoded : [];
        }

        return new self(
            method: $method,
            uri: $uri,
            query: $_GET,
            body: $body,
            headers: $headers,
            server: $_SERVER,
            files: $_FILES,
        );
    }

    /**
     * Resolve Authorization from CGI/Apache quirks (missing HTTP_AUTHORIZATION).
     *
     * @param array<string, mixed> $server
     * @param array<string, mixed> $headers
     */
    private static function resolveAuthorizationHeader(array $server, array $headers): ?string
    {
        $candidates = [
            $headers['Authorization'] ?? null,
            $server['HTTP_AUTHORIZATION'] ?? null,
            $server['REDIRECT_HTTP_AUTHORIZATION'] ?? null,
            $server['Authorization'] ?? null,
        ];

        if (function_exists('apache_request_headers')) {
            $apacheHeaders = apache_request_headers();
            if (is_array($apacheHeaders)) {
                foreach ($apacheHeaders as $name => $value) {
                    if (strcasecmp((string) $name, 'Authorization') === 0) {
                        $candidates[] = $value;
                        break;
                    }
                }
            }
        }

        if (function_exists('getallheaders')) {
            $allHeaders = getallheaders();
            if (is_array($allHeaders)) {
                foreach ($allHeaders as $name => $value) {
                    if (strcasecmp((string) $name, 'Authorization') === 0) {
                        $candidates[] = $value;
                        break;
                    }
                }
            }
        }

        foreach ($candidates as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    /**
     * @return array{name: string, type: string, tmp_name: string, error: int, size: int}|null
     */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        if (! is_array($file) || ! isset($file['tmp_name'])) {
            return null;
        }

        return $file;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        foreach ($this->headers as $name => $value) {
            if (strcasecmp($name, $key) === 0) {
                return $value;
            }
        }

        return $default;
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization');

        if (! is_string($header)) {
            // Fallback for environments where headers map missed Authorization.
            $header = $this->server['HTTP_AUTHORIZATION']
                ?? $this->server['REDIRECT_HTTP_AUTHORIZATION']
                ?? null;
        }

        if (! is_string($header)) {
            return null;
        }

        $header = trim($header);

        if ($header === '' || ! preg_match('/^Bearer\s+(\S+)/i', $header, $matches)) {
            return null;
        }

        return $matches[1] !== '' ? $matches[1] : null;
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function setUser(mixed $user): void
    {
        $this->user = $user;
    }

    public function user(): mixed
    {
        return $this->user;
    }
}
