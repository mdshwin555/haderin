<?php

return [

    // مزوّد الإرسال عبر SMS
    'sms' => [
        'driver' => 'traccar', // فقط وسم لمعلومية النوع
        'api_url' => env('SMS_API_URL', 'https://www.traccar.org/sms/'),
        'api_key' => env('SMS_API_KEY'),
    ],

    // طول الكود وزمن الصلاحية
    'length'      => (int) env('OTP_LENGTH', 5),
    'ttl_minutes' => (int) env('OTP_TTL_MINUTES', 10),

    // قيود معدّل الإرسال (Rate Limit)
    'rate_limit' => [
        'per_phone_per_minute' => (int) env('OTP_PER_PHONE_PER_MINUTE', 3),
        'per_phone_per_hour'   => (int) env('OTP_PER_PHONE_PER_HOUR', 10),
    ],

    // زمن الانتظار قبل إعادة الإرسال
    'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 30),

    // وضع الاختبار: لا يرسل SMS بل يكتب الكود في اللوج فقط
    'test_mode' => filter_var(env('OTP_TEST_MODE', false), FILTER_VALIDATE_BOOLEAN),
];
