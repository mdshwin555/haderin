<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                  ->constrained('conversations')
                  ->cascadeOnDelete();

            $table->foreignId('sender_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // مين المرسل (عميل/دعم/سيستم)
            $table->enum('sender_role', ['customer','support','system'])->default('customer');

            $table->text('body');

            // مؤشرات قراءة اختيارية وبسيطة
            $table->boolean('seen_by_customer')->default(false);
            $table->boolean('seen_by_support')->default(false);

            $table->timestamps();

            $table->index(['conversation_id','id']); // للـ after_id
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
