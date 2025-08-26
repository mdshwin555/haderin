<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('privacy_policies', function (Blueprint $table) {
            $table->id();
            $table->string('title')->default('سياسة الخصوصية');
            $table->string('version', 16)->nullable()->default('1.0');
            $table->string('lang', 5)->default('ar');
            $table->longText('content'); // النص الكامل للسياسة
            $table->boolean('is_active')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['lang', 'is_active']);
        });

        // (اختياري) تأكيد الترميز العربي على مستوى الجدول
        Schema::table('privacy_policies', function (Blueprint $table) {
            $table->charset   = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_policies');
    }
};
