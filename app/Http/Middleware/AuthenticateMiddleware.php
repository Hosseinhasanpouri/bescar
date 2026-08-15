<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\JwtService;
use App\Http\MiddlewareInterface;
use App\Http\Request;
use App\Http\Response;
use App\Models\User;
use RuntimeException;

class AuthenticateMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly JwtService $jwt = new JwtService(),
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null) {
            return Response::json(['message' => 'احراز هویت نشده‌اید'], 401);
        }

        try {
            $payload = $this->jwt->decode($token);
        } catch (RuntimeException) {
            return Response::json(['message' => 'توکن نامعتبر یا منقضی شده است'], 401);
        }

        $userId = isset($payload['sub']) ? (int) $payload['sub'] : 0;
        $user = $userId > 0 ? User::find($userId) : null;

        if ($user === null) {
            return Response::json(['message' => 'احراز هویت نشده‌اید'], 401);
        }

        $request->setUser($user);

        return $next($request);
    }
}
