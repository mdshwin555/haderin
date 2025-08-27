<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function isPgsql(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }

    public function up(): void
    {
        // أضف الأعمدة إذا مش موجودة
        if (!Schema::hasColumn('users', 'referral_code')) {
            Schema::table('users', function (Blueprint $table) {
                // ملاحظة: في PG ما في "after", فLaravel يتجاهلها تلقائياً
                $table->string('referral_code', 16)->nullable()->after('status');
            });
        }
        if (!Schema::hasColumn('users', 'invited_by_code')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('invited_by_code', 16)->nullable()->after('referral_code');
            });
        }

        if ($this->isPgsql()) {
            // PostgreSQL: أسلم شيء نستعمل IF NOT EXISTS
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS users_referral_code_unique ON users (referral_code)");
            DB::statement("CREATE INDEX IF NOT EXISTS users_invited_by_code_index ON users (invited_by_code)");
        } else {
            // MySQL/MariaDB: نفحص information_schema بدل SHOW INDEX
            $exists = DB::selectOne("
                SELECT 1
                FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                  AND table_name = 'users'
                  AND index_name = 'users_referral_code_unique'
            ");
            if (!$exists) {
                Schema::table('users', function (Blueprint $table) {
                    $table->unique('referral_code', 'users_referral_code_unique');
                });
            }

            $exists = DB::selectOne("
                SELECT 1
                FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                  AND table_name = 'users'
                  AND index_name = 'users_invited_by_code_index'
            ");
            if (!$exists) {
                Schema::table('users', function (Blueprint $table) {
                    $table->index('invited_by_code', 'users_invited_by_code_index');
                });
            }
        }
    }

    public function down(): void
    {
        if ($this->isPgsql()) {
            // في PG لازم تسقط الإندكس بدون ذكر الجدول
            DB::statement("DROP INDEX IF EXISTS users_referral_code_unique");
            DB::statement("DROP INDEX IF EXISTS users_invited_by_code_index");
        } else {
            // MySQL/MariaDB
            Schema::table('users', function (Blueprint $table) {
                // هالدوال ما رح تفشل لو الإندكس مش موجود (معظم الإصدارات تتجاهل)
                try { $table->dropUnique('users_referral_code_unique'); } catch (\Throwable $e) {}
                try { $table->dropIndex('users_invited_by_code_index'); } catch (\Throwable $e) {}
            });
        }
        // ما عم نحذف الأعمدة حفاظاً على البيانات
    }
};
