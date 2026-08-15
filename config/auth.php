<?php

declare(strict_types=1);

return [
    'jwt' => [
        'secret' => env('JWT_SECRET', 'change-me-in-production'),
        'algo' => env('JWT_ALGO', 'HS256'),
        'ttl' => (int) env('JWT_TTL', 60 * 24 * 7), // minutes (7 days)
        'issuer' => env('JWT_ISSUER', 'services'),
    ],

    'otp' => [
        'length' => (int) env('OTP_LENGTH', 6),
        'ttl' => (int) env('OTP_TTL', 5), // minutes
        'return_in_response' => env('OTP_RETURN_IN_RESPONSE') !== null
            ? filter_var(env('OTP_RETURN_IN_RESPONSE'), FILTER_VALIDATE_BOOLEAN)
            : env('APP_ENV', 'local') === 'local',
    ],

    'migrate' => [
        'username' => env('MIGRATE_USERNAME', ''),
        'password' => env('MIGRATE_PASSWORD', ''),
    ],
];
