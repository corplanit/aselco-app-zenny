<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ast_ledger_entries')) {
            return;
        }

        Schema::table('ast_ledger_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('ast_ledger_entries', 'billing_upload_id')) {
                $table->foreignId('billing_upload_id')->nullable()->after('wallet_id')->constrained('billing_uploads')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ast_ledger_entries') || ! Schema::hasColumn('ast_ledger_entries', 'billing_upload_id')) {
            return;
        }

        Schema::table('ast_ledger_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('billing_upload_id');
        });
    }
};
