<?php

declare(strict_types=1);

use App\Http\Request;
use App\Routing\Route;

// Handle CORS preflight before routing (OPTIONS may not match a POST-only route)
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit;
}

$router = require __DIR__ . '/bootstrap/app.php';

$request = Request::capture();

try {
    $response = Route::dispatch($request);
} catch (Throwable $e) {
    $response = \App\Http\Response::json([
        'message' => 'Server Error',
        'error' => $e->getMessage(),
    ], 500);
}

$response->withHeaders([
    'Access-Control-Allow-Origin' => '*',
    'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
    'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
])->send();
