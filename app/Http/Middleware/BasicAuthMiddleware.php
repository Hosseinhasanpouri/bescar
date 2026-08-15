<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\MiddlewareInterface;
use App\Http\Request;
use App\Http\Response;

class BasicAuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $expectedUser = (string) config('auth.migrate.username', '');
        $expectedPass = (string) config('auth.migrate.password', '');

        if ($expectedUser === '' || $expectedPass === '') {
            return Response::json([
                'message' => 'احراز هویت مهاجرت پیکربندی نشده است',
            ], 503);
        }

        [$username, $password] = $this->credentials($request);

        if (
            $username === null
            || $password === null
            || ! hash_equals($expectedUser, $username)
            || ! hash_equals($expectedPass, $password)
        ) {
            return Response::json(['message' => 'نام کاربری یا رمز عبور نادرست است'], 401)
                ->withHeaders([
                    'WWW-Authenticate' => 'Basic realm="Migrate"',
                ]);
        }

        return $next($request);
    }

    /** @return array{0: ?string, 1: ?string} */
    private function credentials(Request $request): array
    {
        $header = $request->header('Authorization');
        if (! is_string($header)) {
            $header = $request->server['HTTP_AUTHORIZATION']
                ?? $request->server['REDIRECT_HTTP_AUTHORIZATION']
                ?? null;
        }

        if (is_string($header) && preg_match('/^Basic\s+(\S+)/i', trim($header), $matches) === 1) {
            $decoded = base64_decode($matches[1], true);
            if (is_string($decoded) && str_contains($decoded, ':')) {
                [$user, $pass] = explode(':', $decoded, 2);

                return [$user, $pass];
            }
        }

        $user = $request->input('username');
        $pass = $request->input('password');

        return [
            is_scalar($user) ? (string) $user : null,
            is_scalar($pass) ? (string) $pass : null,
        ];
    }
}
