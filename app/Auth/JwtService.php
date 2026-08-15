<?php

declare(strict_types=1);

namespace App\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;
use Throwable;

class JwtService
{
    public function issue(array $claims): string
    {
        $now = time();
        $ttlMinutes = (int) config('auth.jwt.ttl', 10080);

        $payload = array_merge($claims, [
            'iss' => (string) config('auth.jwt.issuer', 'services'),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + ($ttlMinutes * 60),
        ]);

        return JWT::encode(
            $payload,
            $this->secret(),
            (string) config('auth.jwt.algo', 'HS256')
        );
    }

    public function decode(string $token): array
    {
        try {
            $decoded = JWT::decode(
                $token,
                new Key($this->secret(), (string) config('auth.jwt.algo', 'HS256'))
            );

            return (array) $decoded;
        } catch (Throwable $e) {
            throw new RuntimeException('Invalid or expired token.', 0, $e);
        }
    }

    public function ttlSeconds(): int
    {
        return (int) config('auth.jwt.ttl', 10080) * 60;
    }

    private function secret(): string
    {
        $secret = (string) config('auth.jwt.secret', '');

        if (strlen($secret) < 32) {
            throw new RuntimeException('JWT_SECRET must be at least 32 characters.');
        }

        return $secret;
    }
}
