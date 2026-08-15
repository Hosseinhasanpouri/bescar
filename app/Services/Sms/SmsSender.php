<?php

declare(strict_types=1);

namespace App\Services\Sms;

class SmsSender
{
    /**
     * Send OTP code to a 10-digit Iranian mobile (e.g. 9123456789).
     *
     * @return array{driver: string, message_id?: int|null, cost?: float|null}
     */
    public function sendOtp(int $phone, string $code): array
    {
        $driver = strtolower((string) config('sms.driver', 'log'));
        $mobile = $this->formatMobile($phone);

        return match ($driver) {
            'smsir' => $this->sendViaSmsIr($mobile, $code),
            'log' => $this->sendViaLog($mobile, $code),
            default => throw new SmsException("Unknown SMS driver [{$driver}]."),
        };
    }

    /**
     * @return array{driver: string, message_id: int|null, cost: float|null}
     */
    private function sendViaSmsIr(string $mobile, string $code): array
    {
        $client = SmsIrClient::fromConfig();
        $templateId = (int) config('sms.smsir.template_id', 123456);
        $parameterName = (string) config('sms.smsir.parameter_name', 'CODE');

        if ($templateId < 1) {
            throw new SmsException('SMS_IR_TEMPLATE_ID is not configured.');
        }

        $result = $client->sendVerify($mobile, $templateId, [
            [
                'name' => $parameterName,
                'value' => $code,
            ],
        ]);

        return [
            'driver' => 'smsir',
            'message_id' => $result['message_id'],
            'cost' => $result['cost'],
        ];
    }

    /**
     * @return array{driver: string}
     */
    private function sendViaLog(string $mobile, string $code): array
    {
        error_log("[SMS:log] OTP for {$mobile}: {$code}");

        return ['driver' => 'log'];
    }

    /**
     * sms.ir expects mobile without leading 0 (10 digits), e.g. 9123456789.
     */
    private function formatMobile(int $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if (str_starts_with($digits, '98') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = substr($digits, 1);
        }

        if (! preg_match('/^9\d{9}$/', $digits)) {
            throw new SmsException('Invalid mobile number for SMS.ir (expected 9xxxxxxxxx).');
        }

        return $digits;
    }
}
