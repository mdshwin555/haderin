<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AuthOtpController extends Controller
{
    /**
     * POST /api/auth/request-otp
     * يرجّع دائمًا JSON (حتى عند 429).
     */
    public function requestOtp(Request $request, OtpService $otpService)
    {
        $request->validate([
            'phone'         => ['required', 'string', 'max:32'],
            // ✅ توقيع التطبيق (اختياري) — 11 محرف
            'app_signature' => ['nullable', 'string', 'size:11', 'regex:/^[A-Za-z0-9+\/]{11}$/'],
        ]);

        $normalized = $otpService->normalizeSyPhone($request->input('phone'));

        if (!preg_match('/^09\d{8}$/', $normalized)) {
            return response()->json([
                'message' => 'رقم هاتف غير صالح. استخدم صيغة 09XXXXXXXX أو +9639XXXXXXXX',
            ], 422);
        }

        // التوقيع إن وُجد
        $appSignature = $request->input('app_signature');
        if (!is_string($appSignature) || strlen($appSignature) !== 11) {
            $appSignature = null;
        }

        try {
            // ✅ مرّر الـ hash إلى الخدمة لتلحقة بنص الـ SMS
            $result = $otpService->request($normalized, $appSignature);
        } catch (\Throwable $e) {
            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                $msg    = $e->getMessage() ?: 'حدث خطأ.';
                return response()->json(['message' => $msg], $status);
            }
            return response()->json(['message' => 'تعذّر إرسال رمز التحقق حالياً.'], 500);
        }

        if (!($result['ok'] ?? false)) {
            if (($result['reason'] ?? null) === 'provider' && isset($result['status'])) {
                $body = (array) ($result['body'] ?? []);
                if (!isset($body['message'])) {
                    $body['message'] = 'تعذّر إرسال رمز التحقق حالياً.';
                }
                return response()->json($body, (int) $result['status']);
            }

            return response()->json(['message' => 'تعذّر إرسال رمز التحقق حالياً.'], 400);
        }

        return response()->json([
            'message' => 'تم إرسال رمز التحقق.',
            'phone' => $result['phone'] ?? null,
            'expires_in_minutes' => $result['expires_in_minutes'] ?? null,
            'cooldown_seconds' => $result['cooldown_seconds'] ?? null,
        ]);
    }

    /**
     * POST /api/auth/verify-otp
     * يتحقق من الكود، ينشئ/يحدّث المستخدم، ويصدر توكن Sanctum
     * ويرجع دائمًا JSON.
     */
    public function verifyOtp(Request $request, OtpService $otpService)
    {
        $data = $request->validate([
            'phone'            => ['required','string','max:32'],
            'otp'              => ['required','string','max:10'],
            'invited_by_code'  => ['nullable','string','max:16'],
        ]);

        $phone = $otpService->normalizeSyPhone($data['phone']);

        if (!preg_match('/^09\d{8}$/', $phone)) {
            return response()->json(['message' => 'رقم هاتف غير صالح.'], 422);
        }

        try {
            $ver = $otpService->verify($phone, $data['otp']);
        } catch (\Throwable $e) {
            if ($e instanceof HttpExceptionInterface) {
                return response()->json(['message' => $e->getMessage() ?: 'خطأ في التحقق.'], $e->getStatusCode());
            }
            return response()->json(['message' => 'تعذّر التحقق حالياً.'], 500);
        }

        if (!($ver['ok'] ?? false)) {
            return response()->json(['message' => 'رمز التحقق غير صحيح أو منتهي.'], 422);
        }

        /** @var User $user */
        $user = User::firstOrNew(['phone' => $phone]);

        if ($user->exists && $user->status === 'blocked') {
            return response()->json(['message' => 'الحساب محظور.'], 403);
        }

        $invite = null;
        if (!empty($data['invited_by_code'])) {
            $invite = strtoupper(trim($data['invited_by_code']));

            if ($user->exists && $invite === $user->referral_code) {
                return response()->json(['message' => 'لا يمكنك استخدام كود الإحالة الخاص بك.'], 422);
            }

            $inviter = User::where('referral_code', $invite)
                ->when($user->exists, fn($q) => $q->where('id', '!=', $user->id))
                ->first();

            if (!$inviter) {
                return response()->json(['message' => 'كود إحالة غير صالح.'], 422);
            }
        }

        if (!$user->exists) {
            $user->role   = 'customer';
            $user->status = 'active';
            $user->referral_code = $this->generateReferralCode();
            if (!empty($invite)) {
                $user->invited_by_code = $invite;
            }
            $user->save();
        } else {
            if (empty($user->invited_by_code) && !empty($invite)) {
                if ($invite === $user->referral_code) {
                    return response()->json(['message' => 'لا يمكنك استخدام كود الإحالة الخاص بك.'], 422);
                }
                $user->invited_by_code = $invite;
                $user->save();
            }
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'message' => 'تم التحقق بنجاح.',
            'token'   => $token,
            'user'    => [
                'id'                => $user->id,
                'phone'             => $user->phone,
                'full_name'         => $user->full_name,
                'gender'            => $user->gender,
                'city'              => $user->city,
                'role'              => $user->role,
                'status'            => $user->status,
                'referral_code'     => $user->referral_code,
                'invited_by_code'   => $user->invited_by_code,
                'profile_completed' => (bool) $user->profile_completed_at,
                'joined_at'         => $user->created_at?->toIso8601String(),
            ],
        ]);
    }

    /** مولد كود إحالة فريد (A-Z0-9) */
    private function generateReferralCode(int $len = 8): string
    {
        do {
            $code = strtoupper(Str::random($len));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }
}
