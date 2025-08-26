<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // لازم نشيل الـ UNIQUE قبل حذف عمود email
            if (Schema::hasColumn('users', 'email')) {
                // الاسم الافتراضي لفهرس الـ unique هو users_email_unique
                $table->dropUnique('users_email_unique');
                $table->dropColumn('email');
            }

            if (Schema::hasColumn('users', 'name')) {
                $table->dropColumn('name');
            }

            if (Schema::hasColumn('users', 'email_verified_at')) {
                $table->dropColumn('email_verified_at');
            }

            if (Schema::hasColumn('users', 'password')) {
                $table->dropColumn('password');
            }

            if (Schema::hasColumn('users', 'remember_token')) {
                $table->dropColumn('remember_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // رجّع الأعمدة إذا عملت rollback
            if (!Schema::hasColumn('users', 'name')) {
                $table->string('name')->nullable();
            }
            if (!Schema::hasColumn('users', 'email')) {
                $table->string('email')->nullable()->unique();
            }
            if (!Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'password')) {
                $table->string('password')->nullable();
            }
            if (!Schema::hasColumn('users', 'remember_token')) {
                $table->rememberToken();
            }
        });
    }
};
