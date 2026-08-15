<?php

declare(strict_types=1);

namespace App\Services\Sms;

/**
 * sms.ir REST client — Verify (OTP) API
 *
 * @see https://sms.ir/rest-api/
 */
class SmsIrClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.sms.ir/v1',
        private readonly int $timeout = 15,
    ) {
    }

    public static function fromConfig(): self
    {
        $apiKey = trim((string) config('sms.smsir.api_key', ''));

        if ($apiKey === '') {
            throw new SmsException('SMS.ir API key is not configured (SMS_IR_API_KEY).');
        }

        return new self(
            apiKey: $apiKey,
            baseUrl: (string) config('sms.smsir.base_url', 'https://api.sms.ir/v1'),
            timeout: max(3, (int) config('sms.smsir.timeout', 15)),
        );
    }

    /**
     * Send verification SMS using a panel template.
     *
     * @param  list<array{name: string, value: string}>  $parameters
     * @return array{message_id: int|null, cost: float|null, raw: array<string, mixed>}
     */
    public function sendVerify(string $mobile, int $templateId, array $parameters): array
    {
        $payload = [
            'mobile' => $mobile,
            'templateId' => $templateId,
            'parameters' => array_values($parameters),
        ];

        $response = $this->request('POST', '/send/verify', $payload);

        $status = (int) ($response['status'] ?? 0);
        if ($status !== 1) {
            throw new SmsException(
                message: (string) ($response['message'] ?? 'SMS.ir verify send failed'),
                providerStatus: $status,
                providerResponse: $response,
            );
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];

        return [
            'message_id' => isset($data['messageId']) ? (int) $data['messageId'] : null,
            'cost' => isset($data['cost']) ? (float) $data['cost'] : null,
            'raw' => $response,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $body = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $json = json_encode($body, JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new SmsException('Unable to encode SMS request body.');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: text/plain',
            'x-api-key: ' . $this->apiKey,
        ];

        if (function_exists('curl_init')) {
            return $this->requestWithCurl($method, $url, $json, $headers);
        }

        return $this->requestWithStream($method, $url, $json, $headers);
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, mixed>
     */
    private function requestWithCurl(string $method, string $url, string $json, array $headers): array
    {
        $ch = curl_init($url);

        if ($ch === false) {
            throw new SmsException('Unable to initialize cURL for SMS.ir request.');
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $errno !== 0) {
            throw new SmsException('SMS.ir connection failed: ' . ($error ?: 'unknown error'));
        }

        return $this->decodeResponse((string) $raw, $httpCode);
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, mixed>
     */
    private function requestWithStream(string $method, string $url, string $json, array $headers): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headers),
                'content' => $json,
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        $httpCode = 0;

        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $httpCode = (int) $m[1];
        }

        if ($raw === false) {
            throw new SmsException('SMS.ir connection failed.');
        }

        return $this->decodeResponse($raw, $httpCode);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(string $raw, int $httpCode): array
    {
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw new SmsException(
                message: 'Invalid JSON response from SMS.ir (HTTP ' . $httpCode . ').',
                providerResponse: $raw,
            );
        }

        return $decoded;
    }
}
