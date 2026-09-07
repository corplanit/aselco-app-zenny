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

        Schema::table('wallet_load_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('wallet_load_requests', 'account_number')) {
                $table->string('account_number', 50)->nullable()->after('customer_id');
                $table->index('account_number');
            }
            if (! Schema::hasColumn('wallet_load_requests', 'source')) {
                $table->string('source', 32)->default('threshold')->after('status');
                $table->index(['source', 'status']);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('wallet_load_requests')) {
            return;
        }

        Schema::table('wallet_load_requests', function (Blueprint $table) {
            if (Schema::hasColumn('wallet_load_requests', 'account_number')) {
                $table->dropIndex(['account_number']);
                $table->dropColumn('account_number');
            }
            if (Schema::hasColumn('wallet_load_requests', 'source')) {
                $table->dropIndex(['source', 'status']);
                $table->dropColumn('source');
            }
        });
    }
};
