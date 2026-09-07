<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wallet_load_requests')) {
            return;
        }

        if (! Schema::hasColumn('wallet_load_requests', 'remarks')) {
            Schema::table('wallet_load_requests', function (Blueprint $table) {
                $table->string('remarks', 500)->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('wallet_load_requests') && Schema::hasColumn('wallet_load_requests', 'remarks')) {
            Schema::table('wallet_load_requests', function (Blueprint $table) {
                $table->dropColumn('remarks');
            });
        }
    }
};
