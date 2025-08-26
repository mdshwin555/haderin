<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // معلومات العرض
            $table->string('title');                 // مثال: "شحن رصيد عبر وكيل بسوق الساحة"
            $table->text('description')->nullable(); // تفاصيل إضافية (اختياري)

            // التصنيفات والاتجاه
            $table->string('type');                  // مثال: topup|purchase|repair|delivery|fee|refund|adjustment
            $table->enum('direction', ['credit', 'debit']);

            // المبلغ والعملة
            $table->bigInteger('amount');            // بالمليم/أصغر وحدة؛ لسوريا عادة رقم صحيح
            $table->string('currency', 3)->default('SYP');

            $table->timestamps();

            // فهارس للاستخدامات الشائعة
            $table->index(['user_id', 'created_at']);
            $table->index(['type']);
            $table->index(['direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
