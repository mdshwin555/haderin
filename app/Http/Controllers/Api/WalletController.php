<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    /**
     * GET /api/wallet/overview
     * يرجّع user_name, balance, currency
     */
    public function overview(Request $request)
    {
        $user = $request->user();

        // تأكيد وجود المحفظة للمستخدمين القدامى أيضاً (لو ما انخلقت سابقاً)
        $wallet = $user->wallet()->firstOrCreate([
            'currency' => 'SYP',
        ], [
            'balance'  => 0,
            'currency' => 'SYP',
        ]);

        return response()->json([
            'user_name' => $user->full_name,
            'balance'   => (int) $wallet->balance,
            'currency'  => $wallet->currency,
        ]);
    }


    public function balance(\Illuminate\Http\Request $request)
{
    $wallet = $request->user()->wallet;
    return response()->json([
        'balance'  => (int) ($wallet->balance ?? 0),
        'currency' => $wallet->currency ?? 'SYP',
    ]);
}


    /**
     * GET /api/wallet/transactions
     * باجينيشن 10 – بحث – ترتيب (الأحدث/الأقدم)
     * Params:
     *   q     = string (اختياري)
     *   page  = int (افتراضي 1) — من لارفيل
     *   limit = int (اختياري، افتراضياً 10)
     *   order = desc|asc  (desc = الأحدث أولاً)
     */
    public function transactions(Request $request)
    {
        $user  = $request->user();
        $q     = trim((string) $request->query('q', ''));
        $order = strtolower((string) $request->query('order', 'desc'));
        $order = in_array($order, ['asc', 'desc'], true) ? $order : 'desc';

        $limit = (int) $request->query('limit', 10);
        if ($limit <= 0 || $limit > 100) {
            $limit = 10;
        }

        $query = WalletTransaction::query()
            ->where('user_id', $user->id)
            ->search($q)
            ->orderByCreated($order);

        // نستخدم simplePaginate للأداء
        $p = $query->simplePaginate($limit);

        // نرسم العناصر بالشكل المطلوب
        $items = collect($p->items())->map(function (WalletTransaction $t) {
            return [
                'id'          => $t->id,
                'title'       => $t->title,
                'description' => $t->description,
                'type'        => $t->type,          // مثال: topup|purchase|repair|delivery...
                'direction'   => $t->direction,     // credit | debit
                'amount'      => (int) $t->amount,  // رقم صحيح
                'currency'    => $t->currency,      // SYP
                'created_at'  => $t->created_at?->toIso8601String(),
                // مفيد للواجهة لو بدك تلوّن +/-
                'signed_amount' => $t->signed_amount,
            ];
        })->values()->all();

        return response()->json([
            'items' => $items,
            'meta'  => [
                'page'      => $p->currentPage(),
                'limit'     => $p->perPage(),
                'has_more'  => $p->hasMorePages(),
                // روابط اختيارية لو حابب تستخدمها
                'next_page_url' => $p->nextPageUrl(),
                'prev_page_url' => $p->previousPageUrl(),
                'order'     => $order,
                'q'         => $q !== '' ? $q : null,
            ],
        ]);
    }
}
