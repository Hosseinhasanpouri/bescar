<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Otp;
use App\Services\Sms\SmsSender;

class OtpService
{
    public function __construct(
        private readonly SmsSender $sms = new SmsSender(),
    ) {
    }

    public function generateCode(): string
    {
        $length = max(4, (int) config('auth.otp.length', 6));
        $max = (10 ** $length) - 1;
        $code = (string) random_int(0, $max);

        return str_pad($code, $length, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{otp: Otp, code: string, sms: array<string, mixed>}
     */
    public function send(int $phone): array
    {
        $code = $this->generateCode();
        $ttl = max(1, (int) config('auth.otp.ttl', 5));
        $otp = Otp::createForPhone($phone, $code, $ttl);

        $smsResult = $this->sms->sendOtp($phone, $code);

        return [
            'otp' => $otp,
            'code' => $code,
            'sms' => $smsResult,
        ];
    }

    public function verify(int $phone, string $code): bool
    {
        $otp = Otp::findValid($phone);

        if ($otp === null || ! $otp->matches($code)) {
            return false;
        }

        $otp->markUsed();

        return true;
    }
}
