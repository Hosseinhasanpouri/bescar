<?php

declare(strict_types=1);

use App\Http\Request;
use App\Http\Response;
use App\Support\Env;

if (! function_exists('response')) {
    function response(mixed $data = null, int $status = 200, array $headers = []): Response
    {
        if (is_array($data) || is_object($data)) {
            return Response::json($data, $status, $headers);
        }

        return Response::make($data ?? '', $status, $headers);
    }
}

if (! function_exists('request')) {
    function request(): Request
    {
        return Request::capture();
    }
}

if (! function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        $base = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app';

        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}

if (! function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $base = dirname(__DIR__);

        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}

if (! function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        $base = base_path('config');

        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}

if (! function_exists('database_path')) {
    function database_path(string $path = ''): string
    {
        $base = base_path('database');

        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}

if (! function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (! function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        static $configs = [];

        $segments = explode('.', $key);
        $file = array_shift($segments);

        if (! isset($configs[$file])) {
            $path = config_path($file . '.php');
            $configs[$file] = is_file($path) ? require $path : [];
        }

        $value = $configs[$file];

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

if (! function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        $base = (string) (config('filesystems.disks.local.root') ?: base_path('uploads'));

        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}

if (! function_exists('db')) {
    function db(): \PDO
    {
        return \App\Database\Connection::get();
    }
}

if (! function_exists('utc_now')) {
    /** Current UTC datetime for database storage: Y-m-d H:i:s */
    function utc_now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}

if (! function_exists('utc_from_timestamp')) {
    /** Format a Unix timestamp as UTC datetime for database storage. */
    function utc_from_timestamp(int $timestamp): string
    {
        return (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }
}

if (! function_exists('iran_datetime')) {
    /**
     * Convert a UTC datetime string to Iran time (Asia/Tehran, GMT+3:30) for API responses.
     * Does not change how values are stored in the database.
     */
    function iran_datetime(?string $utcValue): ?string
    {
        if ($utcValue === null || $utcValue === '') {
            return $utcValue;
        }

        try {
            $dt = new DateTimeImmutable($utcValue, new DateTimeZone('UTC'));
        } catch (Exception) {
            return $utcValue;
        }

        return $dt->setTimezone(new DateTimeZone('Asia/Tehran'))->format('Y-m-d H:i:s');
    }
}
