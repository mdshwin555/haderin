<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // نخزّن الرصيد كقيمة صحيحة (أصغر وحدة) — لسوريا غالباً بدون كسور، بنستخدم BIGINT للراحة
            $table->bigInteger('balance')->default(0);
            $table->string('currency', 3)->default('SYP');
            $table->timestamps();

            $table->index(['currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
