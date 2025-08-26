<?php

namespace App\Services;

use App\Models\Otp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OtpService
{
    protected int $length;
    protected int $ttlMinutes;
    protected int $cooldownSec;
    protected int $limitPerMinute;
    protected int $limitPerHour;

    public function __construct()
    {
        $this->length         = (int) config('otp.length', 5);
        $this->ttlMinutes     = (int) config('otp.ttl_minutes', 10);
        $this->cooldownSec    = (int) config('otp.resend_cooldown_seconds', 30);
        $this->limitPerMinute = (int) data_get(config('otp.rate_limit'), 'per_phone_per_minute', 3);
        $this->limitPerHour   = (int) data_get(config('otp.rate_limit'), 'per_phone_per_hour', 10);
    }

    /**
     * طلب إرسال OTP
     * $appSignature: اختياري (11 محرف) لإرفاقه بآخر الرسالة لسحب الكود تلقائياً على أندرويد
     */
    public function request(string $rawPhone, ?string $appSignature = null): array
    {
        $phone = $this->normalizeSyPhone($rawPhone);

        // إلغاء الأكواد السابقة الفعالة
        Otp::active()->where('phone', $phone)->update(['used' => true]);

        // ولّد الكود وخزّنه
        $code = $this->generateCode($this->length);
        $otp = Otp::create([
            'phone'      => $phone,
            'otp'        => $code,
            'used'       => false,
            'expires_at' => now()->addMinutes($this->ttlMinutes),
        ]);

        // ✅ ابنِ نص الرسالة، وألحق الـ hash (إن وُجد) كسطر أخير بدون أي نص بعده
        $message = $this->buildMessage($code, $appSignature);

        // أرسل الرسالة عبر مزود الـ SMS
        $provider = $this->sendSms($phone, $message);

        if (!($provider['ok'] ?? false)) {
            try { $otp->delete(); } catch (\Throwable $e) { /* لا شيء */ }

            return [
                'ok'     => false,
                'reason' => 'provider',
                'status' => $provider['status'] ?? 500,
                'body'   => $provider['body'] ?? ['message' => 'تعذّر إرسال الرسالة.'],
            ];
        }

        return [
            'ok'                 => true,
            'phone'              => $this->maskPhone($phone),
            'expires_in_minutes' => $this->ttlMinutes,
            'cooldown_seconds'   => $this->cooldownSec,
            'otp_id'             => $otp->id,
            'provider'           => $provider['body'] ?? null,
        ];
    }

    /**
     * تحقق من الـ OTP
     */
    public function verify(string $rawPhone, string $code): array
    {
        $phone = $this->normalizeSyPhone($rawPhone);

        $otp = Otp::query()
            ->active()
            ->where('phone', $phone)
            ->where('otp', $code)
            ->latest('id')
            ->first();

        if (!$otp) {
            return ['ok' => false, 'reason' => 'invalid_or_expired'];
        }

        $otp->update(['used' => true]);
        Otp::active()
            ->where('phone', $phone)
            ->where('id', '!=', $otp->id)
            ->update(['used' => true]);

        return ['ok' => true, 'phone' => $phone];
    }

    /**
     * ابنِ رسالة OTP. لو وُجد appSignature (11 محرف)، نضيفه كسطر أخير بلا أي أحرف بعده.
     * مثال موصى به:
     * "Your ExampleApp code is: 12345\nkg+TZ3A5qzS"
     * يجب أن يكون الـ hash في **نهاية** الرسالة وعلى **سطر منفصل**. :contentReference[oaicite:5]{index=5}
     */
    protected function buildMessage(string $code, ?string $appSignature = null): string
    {
        // يمكنك تخصيص اللغة كما تريد
        $base = "كود التحقق: {$code} صالح لمدة {$this->ttlMinutes} دقائق. لا تشاركه مع أي شخص.";
        if ($appSignature && strlen($appSignature) === 11) {
            // مهم: لا تُضِف أي شيء بعد الهاش، ولا حتى مسافة
            return $base . "\n" . $appSignature;
        }
        return $base;
    }

    /**
     * تُرجع مصفوفة: ['ok'=>bool, 'status'=>int, 'body'=>array]
     */
    protected function sendSms(string $localPhone09, string $message): array
    {
        $testMode = (bool) config('otp.test_mode', false);
        $apiUrl   = (string) config('otp.sms.api_url');
        $apiKey   = (string) config('otp.sms.api_key');

        $to = $this->toE164Sy($localPhone09);

        if ($testMode || empty($apiUrl) || empty($apiKey)) {
            Log::info("[OTP][TEST] to={$to} message={$message}");
            return ['ok' => true, 'status' => 200, 'body' => ['message' => 'test_mode']];
        }

        $res = Http::withHeaders([
            'Authorization' => $apiKey,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ])->post($apiUrl, [
            'to'      => $to,
            'message' => $message,
        ]);

        $json = $this->safeJson($res);

        Log::info('[OTP] SMS sent', [
            'to'     => $to,
            'status' => $res->status(),
            'body'   => $json,
        ]);

        if ($res->successful()) {
            return ['ok' => true, 'status' => $res->status(), 'body' => $json];
        }

        return ['ok' => false, 'status' => $res->status(), 'body' => $json];
    }

    protected function safeJson($res): array
    {
        try { return (array) $res->json(); } catch (\Throwable $e) { return ['raw' => $res->body()]; }
    }

    protected function cooldownRemaining(string $phone): int
    {
        $last = Otp::where('phone', $phone)->latest('id')->first();
        if (!$last) return 0;

        $diff = now()->diffInSeconds($last->created_at);
        return $diff >= $this->cooldownSec ? 0 : ($this->cooldownSec - $diff);
    }

    protected function checkRateLimit(string $phone): void
    {
        // معطلة محلياً كما كانت
    }

    protected function generateCode(int $len = 5): string
    {
        $max = 10 ** $len - 1;
        return str_pad((string) random_int(0, $max), $len, '0', STR_PAD_LEFT);
    }

    /** تطبيع رقم سوري إلى 09XXXXXXXX */
    public function normalizeSyPhone(string $input): string
    {
        $digits = preg_replace('/\D+/', '', $input ?? '');

        if (Str::startsWith($digits, '09') && strlen($digits) === 10) {
            return $digits;
        }
        if (Str::startsWith($digits, '009639') && strlen($digits) >= 14) {
            return '0' . substr($digits, 5, 9);
        }
        if (Str::startsWith($digits, '9639') && strlen($digits) >= 12) {
            return '0' . substr($digits, 3, 9);
        }

        return $digits;
    }

    /** تحويل 09XXXXXXXX إلى +9639XXXXXXX */
    public function toE164Sy(string $local09): string
    {
        $d = preg_replace('/\D+/', '', $local09 ?? '');
        if (Str::startsWith($d, '09') && strlen($d) === 10) {
            return '+963' . substr($d, 1);
        }
        if (Str::startsWith($d, '963')) {
            return '+' . $d;
        }
        return '+' . $d;
    }

    protected function maskPhone(string $phone): string
    {
        $d = preg_replace('/\D+/', '', $phone);
        return substr($d, 0, 2) . str_repeat('*', max(0, strlen($d) - 6)) . substr($d, -4);
    }
}
