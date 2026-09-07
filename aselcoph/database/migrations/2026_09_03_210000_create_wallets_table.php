<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wallets')) {
            return;
        }

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->decimal('balance', 14, 2)->default(0);
            $table->string('status', 16)->default('active'); // active | frozen | closed
            $table->timestamps();

            $table->unique('customer_id');
            $table->index('status');
            $table->foreign('customer_id')->references('id')->on('users')->cascadeOnDelete();
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE wallets ADD CONSTRAINT wallets_balance_nonnegative CHECK (balance >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
