<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReferralController extends Controller
{
    /**
     * GET /api/referrals/mine
     * يرجّع كود دعوتي + عدد/عيّنة من المدعوّين.
     */
    public function mine(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        // احتياط: لو مستخدم قديم بدون referral_code
        if (empty($user->referral_code)) {
            $user->referral_code = strtoupper(Str::random(8));
            $user->save();
        }

        $count = $user->invitees()->count();

        $preview = $user->invitees()
            ->latest('id')
            ->limit(5)
            ->get(['id', 'phone', 'full_name', 'created_at'])
            ->map(fn ($u) => [
                'id'        => $u->id,
                'phone'     => $u->phone,
                'full_name' => $u->full_name,
                'joined_at' => $u->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'referral_code'   => $user->referral_code,
            'invited_count'   => $count,
            'invited_preview' => $preview,
        ]);
    }

    /**
     * GET /api/referrals/invitees?per_page=20&page=1
     * قائمة كاملة (مع ترقيم صفحات) بالمدعوّين.
     */
    public function invitees(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $perPage = (int) $request->integer('per_page', 20);
        $perPage = max(1, min(100, $perPage));

        $paginator = $user->invitees()
            ->latest('id')
            ->paginate($perPage, ['id', 'phone', 'full_name', 'created_at']);

        // تخصيص عناصر الـ data قبل الإرجاع
        $paginator->getCollection()->transform(function (User $u) {
            return [
                'id'        => $u->id,
                'phone'     => $u->phone,
                'full_name' => $u->full_name,
                'joined_at' => $u->created_at?->toIso8601String(),
            ];
        });

        return response()->json($paginator);
    }
}
