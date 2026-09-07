<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'department_code')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('department_code', 40)->nullable()->after('role');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'department_code')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('department_code');
            });
        }
    }
};
