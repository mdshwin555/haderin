<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\ServiceOrder;
use App\Models\WalletTransaction;
use App\Models\UserNotification;   // 👈 جديد
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceOrderController extends Controller
{
    /**
     * GET /api/service-orders
     * جلب الطلبات "الحالية" للمستخدم (pending | accepted | in_progress).
     * اختياري: limit (افتراضي 10).
     */
    public function indexCurrent(Request $request)
    {
        $user = $request->user();
        $limit = (int) $request->query('limit', 10);
        $statuses = ['pending', 'accepted', 'in_progress'];

        $orders = ServiceOrder::with(['images', 'address'])
            ->where('customer_id', $user->id)
            ->whereIn('status', $statuses)
            ->latest()
            ->take($limit)
            ->get();

        $data = $orders->map(function (ServiceOrder $o) {
            // عنوان مقروء + عنوان مختصر (ليبل)
            $addressText  = '';
            $addressTitle = '';

            if ($o->relationLoaded('address') && $o->address) {
                $parts = array_filter([
                    $o->address->city ?? $o->address->area ?? null,
                    $o->address->street ?? $o->address->district ?? null,
                    $o->address->details ?? $o->address->building ?? $o->address->notes ?? null,
                ], fn ($v) => filled($v));
                $addressText  = implode(' - ', $parts);
                $addressTitle = $o->address->title ?? $o->address->label ?? 'عنوان';
            }

            // صور بروابط كاملة
            $images = [];
            if ($o->relationLoaded('images')) {
                foreach ($o->images as $img) {
                    $images[] = [
                        'id'   => $img->id,
                        'url'  => asset('storage/' . $img->path),
                        'name' => $img->original_name,
                        'size' => (int) $img->size,
                    ];
                }
            }

            // خطوة للكارد في الهوم:
            // pending/accepted -> 0, in_progress -> 1, (الباقي 2 كمرجعية عامة)
            $step = 0;
            if ($o->status === 'in_progress') {
                $step = 1;
            } elseif (!in_array($o->status, ['pending', 'accepted', 'in_progress'])) {
                $step = 2;
            }

            return [
                'id'             => $o->id,
                'code'           => $o->code ?? ('#' . $o->id),
                'category'       => $o->category ?? 'خدمة',
                'description'    => $o->description,
                'payment_method' => $o->payment_method,
                'cost'           => (int) $o->cost,
                'status'         => $o->status,
                'address_text'   => $addressText,
                'address_title'  => $addressTitle,
                'placed_at'      => optional($o->created_at)->toIso8601String(),
                'images'         => $images,
                'step_index'     => $step,
            ];
        });

        return response()->json(['data' => $data], 200);
    }

    /**
     * GET /api/service-orders/history
     * جلب الطلبات "المنتهية" للمستخدم (completed | canceled).
     * اختياري: limit (افتراضي 20).
     */
    public function indexHistory(Request $request)
    {
        $user  = $request->user();
        $limit = (int) $request->query('limit', 20);
        $statuses = ['completed', 'canceled'];

        $orders = ServiceOrder::with(['images', 'address'])
            ->where('customer_id', $user->id)
            ->whereIn('status', $statuses)
            ->latest()
            ->take($limit)
            ->get();

        $data = $orders->map(function (ServiceOrder $o) {
            $addressText  = '';
            $addressTitle = '';

            if ($o->relationLoaded('address') && $o->address) {
                $parts = array_filter([
                    $o->address->city ?? $o->address->area ?? null,
                    $o->address->street ?? $o->address->district ?? null,
                    $o->address->details ?? $o->address->building ?? $o->address->notes ?? null,
                ], fn ($v) => filled($v));
                $addressText  = implode(' - ', $parts);
                $addressTitle = $o->address->title ?? $o->address->label ?? 'عنوان';
            }

            $images = [];
            if ($o->relationLoaded('images')) {
                foreach ($o->images as $img) {
                    $images[] = [
                        'id'   => $img->id,
                        'url'  => asset('storage/' . $img->path),
                        'name' => $img->original_name,
                        'size' => (int) $img->size,
                    ];
                }
            }

            // للمنتهية: خطوة العرض دائماً 2
            return [
                'id'             => $o->id,
                'code'           => $o->code ?? ('#' . $o->id),
                'category'       => $o->category ?? 'خدمة',
                'description'    => $o->description,
                'payment_method' => $o->payment_method,
                'cost'           => (int) $o->cost,
                'status'         => $o->status, // completed | canceled
                'address_text'   => $addressText,
                'address_title'  => $addressTitle,
                'placed_at'      => optional($o->created_at)->toIso8601String(),
                'images'         => $images,
                'step_index'     => 2,
            ];
        });

        return response()->json(['data' => $data], 200);
    }

    /**
     * GET /api/service-orders/{order}
     * جلب تفاصيل طلب واحد (خاص بالمستخدم المالك).
     */
    public function show(Request $request, $order)
    {
        $user = $request->user();

        // لا تحمّل provider الآن
        $o = ServiceOrder::with(['images', 'address'])->find($order);

        if (!$o) {
            return response()->json(['message' => 'الطلب غير موجود.'], 404);
        }
        if ((int) $o->customer_id !== (int) $user->id) {
            return response()->json(['message' => 'غير مصرّح لك بمشاهدة هذا الطلب.'], 403);
        }

        $addressText  = '';
        $addressTitle = '';

        if ($o->relationLoaded('address') && $o->address) {
            $parts = array_filter([
                $o->address->city ?? $o->address->area ?? null,
                $o->address->street ?? $o->address->district ?? null,
                $o->address->details ?? $o->address->building ?? $o->address->notes ?? null,
            ], fn ($v) => filled($v));
            $addressText  = implode(' - ', $parts);
            $addressTitle = $o->address->title ?? $o->address->label ?? 'عنوان';
        }

        $images = [];
        if ($o->relationLoaded('images')) {
            foreach ($o->images as $img) {
                $images[] = [
                    'id'   => $img->id,
                    'url'  => asset('storage/' . $img->path),
                    'name' => $img->original_name,
                    'size' => (int) $img->size,
                ];
            }
        }

        return response()->json([
            'data' => [
                'id'             => $o->id,
                'code'           => $o->code ?? ('#' . $o->id),
                'category'       => $o->category ?? 'خدمة',
                'description'    => $o->description,
                'payment_method' => $o->payment_method,
                'cost'           => (int) $o->cost,
                'status'         => $o->status,
                'address_text'   => $addressText,
                'address_title'  => $addressTitle,
                'placed_at'      => optional($o->created_at)->toIso8601String(),
                'images'         => $images,
                'provider'       => null, // حالياً ما في علاقة
            ],
        ], 200);
    }

    /**
     * POST /api/service-orders
     * إنشاء طلب خدمة مع صور اختيارية (images[]).
     * Content-Type: multipart/form-data
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // 1) فاليديشن أساسي
        $data = $request->validate([
            'address_id'     => ['required', 'integer', 'exists:addresses,id'],
            'category'       => ['nullable', 'string', 'max:32'],
            'description'    => ['required', 'string', 'max:5000'],
            'payment_method' => ['required', 'in:cash,wallet'],
            'cost'           => ['required', 'integer', 'min:0'],

            'images'         => ['sometimes', 'array'],
            'images.*'       => ['file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'], // 4MB
        ], [
            'address_id.required' => 'الرجاء اختيار عنوان.',
            'address_id.exists'   => 'العنوان المحدد غير موجود.',
            'description.required'=> 'الرجاء كتابة وصف الخدمة.',
            'payment_method.in'   => 'طريقة دفع غير صالحة.',
            'cost.required'       => 'الرجاء إدخال التكلفة التقديرية.',
        ]);

        // 2) تأكد أن العنوان يعود للمستخدم
        $address = Address::where('id', $data['address_id'])
            ->where('user_id', $user->id)
            ->first();

        if (!$address) {
            return response()->json(['message' => 'العنوان المحدد غير موجود ضمن عناوينك.'], 422);
        }

        // 3) لو الدفع بالمحفظة تحقق من الرصيد (فحص أولي سريع)
        if ($data['payment_method'] === 'wallet') {
            $balance = (int) ($user->wallet->balance ?? 0);
            if ((int)$data['cost'] > $balance) {
                return response()->json([
                    'message'         => 'المبلغ يجب أن يكون أقل أو يساوي رصيد محفظتك.',
                    'wallet_balance'  => $balance,
                    'currency'        => $user->wallet->currency ?? 'SYP',
                ], 422);
            }
        }

        // 4) نفّذ الترانزاكشن
        [$order, $images, $walletInfo] = DB::transaction(function () use ($request, $user, $data) {

            $walletInfo = null;
            $wallet = null;
            if ($data['payment_method'] === 'wallet') {
                $wallet = $user->wallet()
                    ->lockForUpdate()
                    ->firstOrCreate(['currency' => 'SYP'], ['balance' => 0, 'currency' => 'SYP']);

                if ((int)$data['cost'] > (int)$wallet->balance) {
                    abort(response()->json([
                        'message'         => 'الرصيد غير كافٍ حالياً لإتمام العملية.',
                        'wallet_balance'  => (int)$wallet->balance,
                        'currency'        => $wallet->currency,
                    ], 422));
                }
            }

            $order = ServiceOrder::create([
                'customer_id'    => $user->id,
                'address_id'     => $data['address_id'],
                'category'       => $data['category'] ?? null,
                'description'    => $data['description'],
                'payment_method' => $data['payment_method'],
                'cost'           => (int) $data['cost'],
                'status'         => 'pending',
            ]);

            $images = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $file) {
                    if (!$file->isValid()) continue;

                    $path = $file->store("service_orders/{$order->id}", 'public');

                    $img = $order->images()->create([
                        'path'          => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'size'          => (int) round(($file->getSize() ?? 0) / 1024),
                    ]);
                    $images[] = [
                        'id'   => $img->id,
                        'url'  => asset('storage/' . $img->path),
                        'name' => $img->original_name,
                        'size' => $img->size,
                    ];
                }
            }

            if ($data['payment_method'] === 'wallet' && $wallet) {
                WalletTransaction::create([
                    'user_id'     => $user->id,
                    'title'       => 'طلب خدمة ' . ($order->code ?? ('#' . $order->id)),
                    'description' => 'خصم تكلفة الطلب من المحفظة.',
                    'type'        => 'service_order',
                    'direction'   => 'debit',
                    'amount'      => (int)$order->cost,
                    'currency'    => $wallet->currency,
                ]);

                $wallet->decrement('balance', (int)$order->cost);

                $walletInfo = [
                    'balance'  => (int)$wallet->fresh()->balance,
                    'currency' => $wallet->currency,
                ];
            }

            return [$order, $images, $walletInfo];
        });

        // 👇 إشعار: تم تقديم الطلب
        try {
            $code = $order->code ?? ('#' . $order->id);
            UserNotification::create([
                'user_id' => $user->id,
                'title'   => 'تم تقديم طلبك',
                'body'    => "تم استلام طلبك {$code} بنجاح. سنعلمك بأي تحديث.",
                'type'    => 'order_created',
                'icon'    => 'bell',
                'data'    => [
                    'order_id' => $order->id,
                    'code'     => $code,
                    'status'   => $order->status,
                ],
            ]);
        } catch (\Throwable $e) {
            // تجاهل أي خطأ في إنشاء الإشعار
        }

        return response()->json([
            'message' => 'تم إنشاء الطلب بنجاح.',
            'data'    => [
                'id'             => $order->id,
                'category'       => $order->category,
                'description'    => $order->description,
                'payment_method' => $order->payment_method,
                'cost'           => (int)$order->cost,
                'status'         => $order->status,
                'address_id'     => $order->address_id,
                'created_at'     => $order->created_at?->toIso8601String(),
                'images'         => $images,
                'wallet'         => $walletInfo, // null لو الدفع كاش
            ],
        ], 201);
    }

    /**
     * GET /api/service-orders/{order}
     * جلب تفاصيل طلب واحد (خاص بالمستخدم المالك).
     */
    public function showOld(Request $request, $order) {} // (موجود فوق النسخة الفعلية)

    /**
     * POST /api/service-orders/{order}/cancel
     * إلغاء طلب من قِبل المستخدم المالك.
     */
    public function cancel(Request $request, $order)
    {
        $user = $request->user();

        $o = ServiceOrder::query()->where('id', $order)->first();
        if (!$o) {
            return response()->json(['message' => 'الطلب غير موجود.'], 404);
        }
        if ((int)$o->customer_id !== (int)$user->id) {
            return response()->json(['message' => 'غير مصرّح لك بإلغاء هذا الطلب.'], 403);
        }

        if ($o->status !== 'pending') {
            return response()->json(['message' => 'لا يمكن إلغاء هذا الطلب في حالته الحالية.'], 422);
        }

        $walletInfo = null;

        DB::transaction(function () use (&$o, $user, &$walletInfo) {
            $o->status = 'canceled';
            $o->save();

            if ($o->payment_method === 'wallet' && (int)$o->cost > 0) {
                $wallet = $user->wallet()->lockForUpdate()->firstOrCreate(
                    ['currency' => 'SYP'],
                    ['balance' => 0, 'currency' => 'SYP']
                );

                WalletTransaction::create([
                    'user_id'     => $user->id,
                    'title'       => 'إلغاء طلب خدمة ' . ($o->code ?? ('#' . $o->id)),
                    'description' => 'استرجاع مبلغ الطلب للمحفظة بعد الإلغاء.',
                    'type'        => 'refund',
                    'direction'   => 'credit',
                    'amount'      => (int)$o->cost,
                    'currency'    => $wallet->currency,
                ]);

                $wallet->increment('balance', (int)$o->cost);

                $walletInfo = [
                    'balance'  => (int)$wallet->fresh()->balance,
                    'currency' => $wallet->currency,
                ];
            }
        });

        // 👇 إشعار: تم إلغاء الطلب
        try {
            $code = $o->code ?? ('#' . $o->id);
            $body = "تم إلغاء طلبك {$code}.";
            if ($walletInfo && isset($walletInfo['balance'])) {
                $body .= ' تم رد المبلغ إلى محفظتك.';
            }

            UserNotification::create([
                'user_id' => $user->id,
                'title'   => 'تم إلغاء الطلب',
                'body'    => $body,
                'type'    => 'order_canceled',
                'icon'    => 'cancel',
                'data'    => [
                    'order_id' => $o->id,
                    'code'     => $code,
                    'status'   => $o->status,
                ],
            ]);
        } catch (\Throwable $e) {
            // تجاهل
        }

        return response()->json([
            'message' => 'تم إلغاء الطلب بنجاح.',
            'data'    => [
                'id'             => $o->id,
                'status'         => $o->status,
                'payment_method' => $o->payment_method,
                'cost'           => (int)$o->cost,
                'wallet'         => $walletInfo, // null لو الدفع كاش أو ما في استرجاع
            ],
        ], 200);
    }
}
