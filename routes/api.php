<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthOtpController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\PrivacyPolicyController;
use App\Http\Controllers\Api\SupportChatController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\ServiceOrderController;
use App\Http\Controllers\Api\NotificationController;


Route::post('/auth/request-otp', [AuthOtpController::class, 'requestOtp']);
Route::post('/auth/verify-otp',  [AuthOtpController::class, 'verifyOtp']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']); // 👈 NEW
    Route::put('/profile', [ProfileController::class, 'update']);

    // يبقى /me كما هو إن بدك (يعيد كائن اليوزر الخام من لارافيل)
    Route::get('/me', function (\Illuminate\Http\Request $request) {
        return $request->user();
    });
});


Route::middleware('auth:sanctum')->group(function () {
    // الموجودة عندك مسبقاً
    Route::get('/addresses', [AddressController::class, 'index']);   // جلب
    Route::post('/addresses', [AddressController::class, 'store']);  // إضافة
       Route::get('/addresses/{id}', [AddressController::class, 'show'])  // جلب واحد
        ->whereNumber('id'); // تقييد أن id رقم

    // المطلوبين:
    Route::put('/addresses/{id}', [AddressController::class, 'update']);    // تعديل
    Route::delete('/addresses/{id}', [AddressController::class, 'destroy']); // حذف
    // Route::put('/addresses/{id}/default', [AddressController::class, 'setDefault']);
});

Route::middleware('auth:sanctum')->group(function () {
    // 👇 محفظة
    Route::get('/wallet/overview',     [WalletController::class, 'overview']);
    Route::get('/wallet/transactions', [WalletController::class, 'transactions']);
});


Route::get('/legal/privacy-policy', [PrivacyPolicyController::class, 'show']);
Route::get('/legal/terms',          [PrivacyPolicyController::class, 'terms']);


Route::middleware('auth:sanctum')->prefix('support-chat')->group(function () {
    // العميل يبدأ محادثة (أو يرجع المفتوحة)
    Route::post('/start', [SupportChatController::class, 'start']);

    // موظف الدعم يشوف قائمة المحادثات
    Route::get('/conversations', [SupportChatController::class, 'index']);

    // رسائل محادثة معيّنة
    Route::get('/conversations/{conversation}/messages', [SupportChatController::class, 'messages'])
        ->whereNumber('conversation');

    // إرسال رسالة
    Route::post('/conversations/{conversation}/messages', [SupportChatController::class, 'send'])
        ->whereNumber('conversation');
});


Route::middleware('auth:sanctum')->prefix('referrals')->group(function () {
    Route::get('/mine',      [ReferralController::class, 'mine']);      // كود دعوتي + ملخّص
});
Route::middleware('auth:sanctum')->group(function () {
    // الطلبات "الحالية" (pending/accepted/in_progress)
    Route::get('/service-orders', [ServiceOrderController::class, 'indexCurrent']);

    // الطلبات "المنتهية" (completed/canceled)  ← جديد
    Route::get('/service-orders/history', [ServiceOrderController::class, 'indexHistory']);

    // إنشاء الطلب
    Route::post('/service-orders', [ServiceOrderController::class, 'store']);

    // جلب طلب واحد
    Route::get('/service-orders/{order}', [ServiceOrderController::class, 'show'])
        ->whereNumber('order');

    // إلغاء الطلب
    Route::post('/service-orders/{order}/cancel', [ServiceOrderController::class, 'cancel'])
        ->whereNumber('order');

    // رصيد المحفظة المختصر
    Route::get('/wallet/balance', [WalletController::class, 'balance']);

    // (اختياري) نظرة عامة على المحفظة
    Route::get('/wallet/overview', [WalletController::class, 'overview']);

    // (اختياري) قائمة الحركات بالمحفظة
    Route::get('/wallet/transactions', [WalletController::class, 'transactions']);
});


Route::middleware('auth:sanctum')->group(function () {

    // إشعارات
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->whereNumber('id');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->whereNumber('id');
});

use App\Http\Controllers\Api\BannerController;

Route::get('/banners', [BannerController::class, 'index']); // عام للموبايل

// إن أردت حصر الإدارة بالمصادقة:
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/banners',               [BannerController::class, 'store']);
    Route::post('/banners/{banner}',      [BannerController::class, 'update']); // أو PUT حسب عميلك
    Route::delete('/banners/{banner}',    [BannerController::class, 'destroy']);
    Route::post('/banners/{banner}/view', [BannerController::class, 'addView']); // أو بدون auth إذا حابب
Route::post('/banners/{banner}/view', [BannerController::class, 'addView']);
});
Route::get('/banners', [BannerController::class, 'index']);

