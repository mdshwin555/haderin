<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('otps', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();   // 09XXXXXXXX
            $table->string('otp', 10);              // الكود
            $table->boolean('used')->default(false)->index();
            $table->timestamp('expires_at')->index();
            $table->unsignedSmallInteger('resend_count')->default(0);
            $table->timestamps();

            $table->index(['phone', 'otp', 'used']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otps');
    }
};
