<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('billing_uploads')) {
            return;
        }

        Schema::table('billing_uploads', function (Blueprint $table) {
            if (! Schema::hasColumn('billing_uploads', 'status')) {
                $table->string('status', 32)->default('pending')->after('uploaded_by');
            }
            if (! Schema::hasColumn('billing_uploads', 'paid_amount')) {
                $table->decimal('paid_amount', 10, 2)->default(0)->after('status');
            }
            if (! Schema::hasColumn('billing_uploads', 'balance_due')) {
                $table->decimal('balance_due', 10, 2)->default(0)->after('paid_amount');
            }
            if (! Schema::hasColumn('billing_uploads', 'last_payment_reference')) {
                $table->string('last_payment_reference', 40)->nullable()->after('balance_due');
            }
        });

        DB::table('billing_uploads')
            ->whereNull('balance_due')
            ->update(['balance_due' => DB::raw('amount')]);

        DB::table('billing_uploads')
            ->where('balance_due', 0)
            ->update(['balance_due' => DB::raw('amount')]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('billing_uploads')) {
            return;
        }

        Schema::table('billing_uploads', function (Blueprint $table) {
            foreach (['last_payment_reference', 'balance_due', 'paid_amount', 'status'] as $column) {
                if (Schema::hasColumn('billing_uploads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
