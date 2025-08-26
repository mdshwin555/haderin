<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    /** تحقّق بسيط لوجود فهرس باسم معيّن */
    private function indexExists(string $table, string $indexName): bool
    {
        $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        return !empty($rows);
    }

    public function up(): void
    {
        // احفظ حالة وجود الفهارس قبل إغلاق الـ closure
        $hasReferralUnique = $this->indexExists('users', 'users_referral_code_unique');
        $hasInvitedIndex   = $this->indexExists('users', 'users_invited_by_code_index');

        Schema::table('users', function (Blueprint $table) use ($hasReferralUnique, $hasInvitedIndex) {
            // أضف الأعمدة إن لم تكن موجودة
            if (!Schema::hasColumn('users', 'referral_code')) {
                $table->string('referral_code', 16)->nullable()->after('status');
            }
            if (!Schema::hasColumn('users', 'invited_by_code')) {
                $table->string('invited_by_code', 16)->nullable()->after('referral_code');
            }

            // أضف الفهارس فقط إذا غير موجودة
            if (!$hasReferralUnique) {
                $table->unique('referral_code', 'users_referral_code_unique');
            }
            if (!$hasInvitedIndex) {
                $table->index('invited_by_code', 'users_invited_by_code_index');
            }
        });
    }

    public function down(): void
    {
        // إسقاط الفهارس فقط إذا كانت موجودة
        if ($this->indexExists('users', 'users_referral_code_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_referral_code_unique');
            });
        }
        if ($this->indexExists('users', 'users_invited_by_code_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('users_invited_by_code_index');
            });
        }
        // ما رح نحذف الأعمدة حفاظاً على البيانات
    }
};
