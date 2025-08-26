<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // الهاتف + معلومات شخصية
            $table->string('phone', 20)->unique()->after('id');
            $table->string('full_name', 120)->nullable()->after('phone');
            $table->string('gender', 10)->nullable()->after('full_name'); // 'ذكر'/'أنثى' أو en
            $table->string('city', 120)->nullable()->after('gender');

            // الدور: admin (لوحة التحكم) | customer (طالب الخدمة) | provider (منفّذ الخدمة: دليفري/سبّاك...)
            $table->enum('role', ['admin', 'customer', 'provider'])
                  ->default('customer')
                  ->after('city');

            // الحالة: active | blocked (للبلوك)
            $table->enum('status', ['active', 'blocked'])
                  ->default('active')
                  ->after('role')
                  ->index();

            // الإحالات
            $table->string('referral_code', 16)->nullable()->unique()->after('status');     // كود المستخدم نفسه للمشاركة
            $table->string('invited_by_code', 16)->nullable()->after('referral_code');      // الكود الذي أدخله عند التسجيل
            $table->timestamp('profile_completed_at')->nullable()->after('invited_by_code'); // وقت إكمال البيانات
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone','full_name','gender','city',
                'role','status',
                'referral_code','invited_by_code','profile_completed_at',
            ]);
        });
    }
};
