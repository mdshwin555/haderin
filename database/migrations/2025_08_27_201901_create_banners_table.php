<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150)->nullable();  // عنوان مختصر اختياري
            $table->text('description')->nullable();   // وصف يظهر بالـ bottom sheet
            $table->string('image_path');              // مسار الصورة داخل storage/app/public
            $table->string('link_url')->nullable();    // لينك اختياري (إن أردت فتح صفحة)
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0); // ترتيب العرض
            $table->unsignedBigInteger('views_count')->default(0); // اختياري
            $table->timestamp('starts_at')->nullable(); // نافذة تفعيل زمنية (اختياري)
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
            $table->index(['sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
