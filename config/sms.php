<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | SMS Driver
    |--------------------------------------------------------------------------
    |
    | log   = write OTP to PHP error_log only (local/dev, no real SMS)
    | smsir = send via sms.ir Verify API
    |
    */
    'driver' => env('SMS_DRIVER', env('APP_ENV', 'local') === 'local' ? 'log' : 'smsir'),

    'smsir' => [
        'api_key' => env('SMS_IR_API_KEY', ''),
        'base_url' => rtrim((string) env('SMS_IR_BASE_URL', 'https://api.sms.ir/v1'), '/'),
        // Template from sms.ir panel (Sandbox default: 123456 with #CODE#)
        'template_id' => (int) env('SMS_IR_TEMPLATE_ID', 123456),
        // Parameter name in template without # (e.g. CODE for #CODE#)
        'parameter_name' => env('SMS_IR_PARAMETER_NAME', 'CODE'),
        'timeout' => (int) env('SMS_IR_TIMEOUT', 15),
    ],
];
