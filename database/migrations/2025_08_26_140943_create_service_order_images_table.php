<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_order_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('service_order_id')
                  ->constrained('service_orders')
                  ->cascadeOnUpdate()
                  ->cascadeOnDelete();

            // مسار الصورة على التخزين
            $table->string('path');          // storage/app/public/service_orders/...
            $table->string('original_name')->nullable();
            $table->unsignedInteger('size')->nullable(); // بالكيلوبايت تقريباً

            $table->timestamps();

            $table->index('service_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_images');
    }
};
