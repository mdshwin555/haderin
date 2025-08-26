<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // 'home' | 'work' | 'other'
            $table->string('label', 16)->default('home');

            // عنوان اختياري لعرضه (إذا بدك غير "المنزل/العمل")
            $table->string('title', 120)->nullable();

            $table->string('details', 255)->nullable();     // تفاصيل عامة
            $table->string('street', 120)->nullable();      // الشارع
            $table->string('floor', 60)->nullable();        // الطابق/شقة
            $table->string('city', 120)->nullable();        // المدينة
            $table->string('contact_phone', 20)->nullable();// هاتف للعنوان (اختياري)

            // إحداثيات
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            $table->boolean('is_default')->default(false);

            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
