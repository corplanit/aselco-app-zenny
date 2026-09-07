<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wallet_transactions')) {
            return;
        }

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wallet_id');
            $table->string('type', 16); // load | payment | reversal | adjustment
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_before', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('reference_no', 40);
            $table->string('idempotency_key', 128);
            $table->string('source', 16); // admin | system
            $table->unsignedBigInteger('source_id')->nullable(); // admin user id when source=admin
            $table->string('status', 16)->default('pending'); // pending | completed | failed | reversed
            $table->string('remarks', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique('reference_no');
            $table->unique('idempotency_key');
            $table->index(['wallet_id', 'created_at']);
            $table->index(['type', 'status']);
            $table->foreign('wallet_id')->references('id')->on('wallets')->restrictOnDelete();
            $table->foreign('source_id')->references('id')->on('users')->nullOnDelete();
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE wallet_transactions ADD CONSTRAINT wallet_transactions_amount_positive CHECK (amount > 0)');
            DB::statement('ALTER TABLE wallet_transactions ADD CONSTRAINT wallet_transactions_balance_after_nonnegative CHECK (balance_after >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
