<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();

            // صاحب الطلب
            $table->foreignId('customer_id')
                  ->constrained('users')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            // عنوان من عناوينه
            $table->foreignId('address_id')
                  ->constrained('addresses')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            // نوع الخدمة (اختياري — الشاشة عندك لكل نوع)
            $table->string('category', 32)->nullable(); // delivery / cleaning / repair / shopping / moving...

            // وصف حر
            $table->text('description');

            // طريقة الدفع
            $table->enum('payment_method', ['cash','wallet']);

            // المبلغ الذي أدخله المستخدم (ل.س)
            $table->unsignedBigInteger('cost');

            // حالة الطلب المبدئية
            $table->string('status', 24)->default('pending'); // pending | assigned | cancelled | done...

            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index('payment_method');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
