<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\JwtService;
use App\Http\Request;
use App\Http\Response;
use App\Models\User;
use App\Services\OtpService;
use App\Services\Sms\SmsException;

class AuthController
{
    public function __construct(
        private readonly OtpService $otp = new OtpService(),
        private readonly JwtService $jwt = new JwtService(),
    ) {
    }

    public function requestOtp(Request $request): Response
    {
        $phone = $this->normalizePhone($request->input('phone'));

        if ($phone === null) {
            return Response::json([
                'message' => 'اعتبارسنجی ناموفق بود',
                'errors' => ['phone' => ['شماره موبایل باید دقیقاً ۱۰ رقم باشد (مثال: ۹۱۲۳۴۵۶۷۸۹).']],
            ], 422);
        }

        try {
            $result = $this->otp->send($phone);
        } catch (SmsException $e) {
            return Response::json([
                'message' => 'ارسال پیامک کد تأیید ناموفق بود',
                'error' => $e->getMessage(),
                'provider_status' => $e->providerStatus,
            ], 502);
        }

        $payload = [
            'message' => 'کد تأیید با موفقیت ارسال شد',
            'phone' => (string) $phone,
            'expires_in' => (int) config('auth.otp.ttl', 5) * 60,
            'sms_driver' => $result['sms']['driver'] ?? null,
            'has_name' => false,
        ];

        $existing = User::findByPhone((int) $phone);
        if ($existing !== null && is_string($existing->name) && trim($existing->name) !== '') {
            $payload['has_name'] = true;
        }

        if (config('auth.otp.return_in_response')) {
            $payload['code'] = $result['code'];
        }

        return Response::json($payload);
    }

    public function verifyOtp(Request $request): Response
    {
        $phone = $this->normalizePhone($request->input('phone'));
        $code = trim((string) $request->input('code', ''));
        $name = $request->input('name');

        $errors = [];

        if ($phone === null) {
            $errors['phone'][] = 'شماره موبایل باید دقیقاً ۱۰ رقم باشد (مثال: ۹۱۲۳۴۵۶۷۸۹).';
        }

        $otpLength = (int) config('auth.otp.length', 6);
        if ($code === '' || ! preg_match('/^\d{' . $otpLength . '}$/', $code)) {
            $errors['code'][] = "کد باید دقیقاً {$otpLength} رقم باشد.";
        }

        if (is_string($name) && mb_strlen(trim($name)) > 255) {
            $errors['name'][] = 'نام نباید بیشتر از ۲۵۵ کاراکتر باشد.';
        }

        if ($errors !== []) {
            return Response::json(['message' => 'اعتبارسنجی ناموفق بود', 'errors' => $errors], 422);
        }

        if (! $this->otp->verify((int) $phone, $code)) {
            return Response::json(['message' => 'کد تأیید نامعتبر یا منقضی شده است'], 401);
        }

        $user = User::findByPhone((int) $phone);
        $isNew = false;

        if ($user === null) {
            $isNew = true;
            $user = User::create([
                'name' => is_string($name) && trim($name) !== '' ? trim($name) : null,
                'phone' => (int) $phone,
                'phone_verified_at' => utc_now(),
            ]);
        } else {
            $data = ['phone_verified_at' => utc_now()];

            if (is_string($name) && trim($name) !== '' && ($user->name === null || $user->name === '')) {
                $data['name'] = trim($name);
            }

            $user = $user->update($data);
        }

        $token = $this->jwt->issue([
            'sub' => (string) $user->id,
            'phone' => (string) $user->phone,
        ]);

        return Response::json([
            'message' => $isNew ? 'حساب کاربری ایجاد شد' : 'ورود موفقیت‌آمیز بود',
            'is_new_user' => $isNew,
            'token_type' => 'Bearer',
            'expires_in' => $this->jwt->ttlSeconds(),
            'access_token' => $token,
            'user' => $user->toArray(),
        ]);
    }

    public function me(Request $request): Response
    {
        $user = $request->user();

        if ($user === null) {
            return Response::json(['message' => 'احراز هویت نشده‌اید'], 401);
        }

        return Response::json(['data' => $user->toArray()]);
    }

    private function normalizePhone(mixed $phone): ?int
    {
        $phoneStr = is_scalar($phone) ? (string) $phone : '';

        if (! preg_match('/^\d{10}$/', $phoneStr)) {
            return null;
        }

        return (int) $phoneStr;
    }
}
