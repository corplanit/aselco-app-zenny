<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->nullable()->after('email');
            });
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'contact_no')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('contact_no')->nullable();
            });
        }

        if (! Schema::hasTable('ast_wallets')) {
            Schema::create('ast_wallets', function (Blueprint $table) {
                $table->id();
                $table->string('account_number')->unique();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('balance', 14, 2)->default(0);
                $table->timestamps();

                $table->index('user_id');
            });
        }

        if (! Schema::hasTable('ast_ledger_entries')) {
            Schema::create('ast_ledger_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('wallet_id')->constrained('ast_wallets')->cascadeOnDelete();
                $table->string('type', 32); // load | pay | adjust | reversal
                $table->decimal('amount', 14, 2);
                $table->decimal('balance_after', 14, 2);
                $table->string('idempotency_key')->unique();
                $table->string('reference', 32)->unique();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('cis_status', 32)->default('not_applicable');
                $table->timestamp('cis_posted_at')->nullable();
                $table->foreignId('cis_posted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('cis_external_ref')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['wallet_id', 'created_at']);
                $table->index(['cis_status', 'created_at']);
            });
        }

        if (! Schema::hasTable('ast_audit_logs')) {
            Schema::create('ast_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('wallet_id')->nullable()->constrained('ast_wallets')->nullOnDelete();
                $table->foreignId('ledger_entry_id')->nullable()->constrained('ast_ledger_entries')->nullOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('actor_role')->nullable();
                $table->string('action', 64);
                $table->decimal('amount', 14, 2)->nullable();
                $table->decimal('balance_before', 14, 2)->nullable();
                $table->decimal('balance_after', 14, 2)->nullable();
                $table->string('idempotency_key')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['wallet_id', 'created_at']);
                $table->index(['actor_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ast_audit_logs');
        Schema::dropIfExists('ast_ledger_entries');
        Schema::dropIfExists('ast_wallets');
    }
};
