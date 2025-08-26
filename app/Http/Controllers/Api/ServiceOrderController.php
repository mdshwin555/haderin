<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ServiceOrderController extends Controller
{

     /**
     * GET /api/service-orders/{order}
     * جلب تفاصيل طلب واحد (خاص بالمستخدم المالك).
     */
 public function show(Request $request, $order)
{
    $user = $request->user();

    // لا تحمّل provider الآن
    $o = \App\Models\ServiceOrder::with(['images', 'address'])->find($order);

    if (!$o) {
        return response()->json(['message' => 'الطلب غير موجود.'], 404);
    }
    if ((int)$o->customer_id !== (int)$user->id) {
        return response()->json(['message' => 'غير مصرّح لك بمشاهدة هذا الطلب.'], 403);
    }

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
                'url'  => asset('storage/'.$img->path),
                'name' => $img->original_name,
                'size' => (int) $img->size,
            ];
        }
    }

    return response()->json([
        'data' => [
            'id'             => $o->id,
            'code'           => $o->code ?? ('#'.$o->id),
            'category'       => $o->category ?? 'خدمة',
            'description'    => $o->description,
            'payment_method' => $o->payment_method,
            'cost'           => (int) $o->cost,
            'status'         => $o->status,
            'address_text'   => $addressText,
            'address_title'  => $addressTitle, // 👈 جديد
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

            // صور اختيارية: images[] متعددة
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

        // 3) لو الدفع بالمحفظة تحقق من الرصيد
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

        // 4) أنشئ الطلب
        $order = ServiceOrder::create([
            'customer_id'    => $user->id,
            'address_id'     => $data['address_id'],
            'category'       => $data['category'] ?? null,
            'description'    => $data['description'],
            'payment_method' => $data['payment_method'],
            'cost'           => (int) $data['cost'],
            'status'         => 'pending',
        ]);

        // 5) خزن الصور (إن وُجدت)
        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                if (!$file->isValid()) continue;

                // يخزن داخل storage/app/public/service_orders/{orderId}/...
                $path = $file->store("service_orders/{$order->id}", 'public');

                $img = $order->images()->create([
                    'path'          => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'size'          => (int) round(($file->getSize() ?? 0) / 1024), // KB
                ]);
                $images[] = [
                    'id'   => $img->id,
                    'url'  => asset('storage/'.$img->path),
                    'name' => $img->original_name,
                    'size' => $img->size,
                ];
            }
        }

        return response()->json([
            'message' => 'تم إنشاء الطلب بنجاح.',
            'data'    => [
                'id'             => $order->id,
                'category'       => $order->category,
                'description'    => $order->description,
                'payment_method' => $order->payment_method,
                'cost'           => $order->cost,
                'status'         => $order->status,
                'address_id'     => $order->address_id,
                'created_at'     => $order->created_at?->toIso8601String(),
                'images'         => $images,
            ],
        ], 201);
    }
}
