<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wallet_load_requests')) {
            return;
        }

        Schema::create('wallet_load_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('admin_id'); // maker
            $table->decimal('amount', 14, 2);
            $table->string('reference_no', 40);
            $table->string('idempotency_key', 128);
            $table->string('status', 16)->default('pending'); // pending | approved | rejected | completed
            $table->unsignedBigInteger('approved_by')->nullable(); // checker; must differ from admin_id in app
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique('reference_no');
            $table->unique('idempotency_key');
            $table->index(['status', 'created_at']);
            $table->index('customer_id');
            $table->foreign('customer_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('admin_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE wallet_load_requests ADD CONSTRAINT wallet_load_requests_amount_positive CHECK (amount > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_load_requests');
    }
};
