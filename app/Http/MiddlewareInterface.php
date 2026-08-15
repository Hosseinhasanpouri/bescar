<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Request;
use App\Http\Response;

interface MiddlewareInterface
{
    /**
     * @param  callable(Request): Response  $next
     */
    public function handle(Request $request, callable $next): Response;
}
