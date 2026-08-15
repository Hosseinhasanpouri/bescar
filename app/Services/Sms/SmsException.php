<?php

declare(strict_types=1);

namespace App\Services\Sms;

use RuntimeException;

class SmsException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $providerStatus = null,
        public readonly mixed $providerResponse = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
