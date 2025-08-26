<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * GET /api/profile
     * يتطلّب Authorization: Bearer <token>
     * يعيد بيانات البروفايل بصيغة موحّدة
     */
    public function show(Request $request)
    {
        /** @var User $user */
        $user = $request->user(); // Sanctum

        return response()->json([
            'user' => [
                'id'                => $user->id,
                'phone'             => $user->phone,
                'full_name'         => $user->full_name,
                'gender'            => $user->gender, // 'ذكر' أو 'أنثى' حسب تخزينك الحالي
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

    /**
     * PUT /api/profile
     * يتطلّب Authorization: Bearer <token>
     */
    public function update(Request $request, SmsService $sms)
    {
        /** @var User $user */
        $user = $request->user(); // Sanctum

        if ($user->status === 'blocked') {
            return response()->json(['message' => 'الحساب محظور.'], 403);
        }

        $data = $request->validate([
            'full_name'       => ['nullable', 'string', 'max:120'],
            'gender'          => ['nullable', 'string', Rule::in(['ذكر','أنثى','male','female'])],
            'city'            => ['nullable', 'string', 'max:120'],
            'invited_by_code' => ['nullable', 'string', 'max:16'],
        ]);

        // التحقق إن كان البروفايل مكتملاً قبل التحديث
        $wasCompleted  = (bool) $user->profile_completed_at;
        $justSetInvite = false;
        $referrer      = null;

        // توحيد تمثيل الجنس للتخزين (نخزّن بالعربي حسب منطقك الحالي)
        if (!empty($data['gender'])) {
            $g = strtolower(trim($data['gender']));
            $data['gender'] = in_array($g, ['male','ذكر']) ? 'ذكر' : 'أنثى';
        }

        // تحديث الحقول الأساسية
        $user->fill([
            'full_name' => $data['full_name'] ?? $user->full_name,
            'gender'    => $data['gender']    ?? $user->gender,
            'city'      => $data['city']      ?? $user->city,
        ]);

        // كود الإحالة (مرة واحدة فقط)
        if (!empty($data['invited_by_code'])) {
            $code = strtoupper(trim($data['invited_by_code']));

            if (!empty($user->invited_by_code)) {
                return response()->json(['message' => 'لا يمكن تعديل كود الدعوة بعد حفظه.'], 422);
            }
            if ($code === $user->referral_code) {
                return response()->json(['message' => 'لا يمكنك استخدام كود الإحالة الخاص بك.'], 422);
            }

            $referrer = User::where('referral_code', $code)
                ->where('id', '!=', $user->id)
                ->first();

            if (!$referrer) {
                return response()->json(['message' => 'كود إحالة غير صالح.'], 422);
            }

            $user->invited_by_code = $code;
            $justSetInvite = true;
        }

        // تعليم اكتمال الملف عند توفر العناصر الأساسية لأول مرة
        if (
            !$user->profile_completed_at &&
            $user->full_name && $user->gender && $user->city
        ) {
            $user->profile_completed_at = now();
        }

        $user->save();

        // ====== رسائل SMS ======

        // 1) للمستخدم نفسه عند أول اكتمال للبروفايل فقط
        if (!$wasCompleted && $user->profile_completed_at) {
            $display = $user->full_name ?: $sms->maskPhone($user->phone);
            $msgUser = "🎉 أهلاً {$display}! تم إكمال ملفك الشخصي بنجاح. صار حسابك جاهز للاستفادة من خدماتنا. شكرًا لإتمام بياناتك 💚";
            $sms->send($user->phone, $msgUser);
        }

        // 2) عند قبول كود إحالة صالح الآن
        if ($justSetInvite && $referrer) {
            // لصاحب الكود
            $maskedNew = $sms->maskPhone($user->phone);
            $msgRef = "🙌 خبر سار! المستخدم {$maskedNew} انضم عبر كودك {$referrer->referral_code}. شكرًا لمشاركتك التطبيق — مزايا الدعوة بتوصلك عند أول عملية ناجحة 🎁";
            $sms->send($referrer->phone, $msgRef);

            // للمستخدم المنضم
            $msgInvitee = "🎁 تم قبول كود الإحالة! رح تستفيد من خصم الترحيب عند أول طلب. أهلاً وسهلاً فيك 😊";
            $sms->send($user->phone, $msgInvitee);
        }

        return response()->json([
            'message' => 'تم تحديث الملف الشخصي.',
            'user' => [
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
}
