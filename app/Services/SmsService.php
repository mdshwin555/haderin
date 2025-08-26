<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * أرسل رسالة SMS لرقم سوري بصيغة 09XXXXXXXX
     */
    public function send(string $localPhone09, string $message): void
    {
        $apiUrl   = (string) config('otp.sms.api_url');
        $apiKey   = (string) config('otp.sms.api_key');
        $testMode = (bool)   config('otp.test_mode', false);

        $to = $this->toE164Sy($localPhone09);

        if ($testMode || empty($apiUrl) || empty($apiKey)) {
            Log::info("[SMS][TEST] to={$to} message={$message}");
            return;
        }

        $res = Http::withHeaders([
            'Authorization' => $apiKey,          // انتبه: بدون اقتباس في .env
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ])->post($apiUrl, [
            'to'      => $to,
            'message' => $message,
        ]);

        Log::info('[SMS] sent', [
            'to'     => $to,
            'status' => $res->status(),
            'body'   => $res->body(),
        ]);
    }

    /** تحويل 09XXXXXXXX إلى +9639XXXXXXX */
    public function toE164Sy(string $local09): string
    {
        $d = preg_replace('/\D+/', '', $local09 ?? '');
        if (str_starts_with($d, '09') && strlen($d) === 10) {
            return '+963' . substr($d, 1);
        }
        if (str_starts_with($d, '963')) {
            return '+' . $d;
        }
        return '+' . $d;
    }

    /** إخفاء الرقم (يبقي أول خانتين وآخر 4) */
    public function maskPhone(string $phone): string
    {
        $d = preg_replace('/\D+/', '', $phone);
        return substr($d, 0, 2) . str_repeat('*', max(0, strlen($d) - 6)) . substr($d, -4);
    }
}
