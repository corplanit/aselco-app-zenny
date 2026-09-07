<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_conversations')) {
            Schema::create('ai_conversations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('channel', 20)->default('customer');
                $table->string('title', 160)->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'updated_at']);
            });
        }

        if (! Schema::hasTable('ai_messages')) {
            Schema::create('ai_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
                $table->string('role', 20);
                $table->text('content');
                $table->string('intent', 40)->nullable();
                $table->string('suggested_action', 40)->nullable();
                $table->boolean('escalate')->default(false);
                $table->json('meta')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['conversation_id', 'id']);
            });
        }

        if (! Schema::hasTable('ai_request_logs')) {
            Schema::create('ai_request_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('channel', 20);
                $table->string('provider', 40);
                $table->string('model', 80)->nullable();
                $table->unsignedSmallInteger('prompt_chars')->default(0);
                $table->unsignedInteger('prompt_tokens')->nullable();
                $table->unsignedInteger('completion_tokens')->nullable();
                $table->unsignedSmallInteger('latency_ms')->nullable();
                $table->boolean('ok')->default(true);
                $table->string('error_code', 64)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['created_at', 'ok']);
            });
        }

        if (! Schema::hasTable('ai_admin_audit_logs')) {
            Schema::create('ai_admin_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('ticket_id')->nullable();
                $table->string('action', 64);
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 512)->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_admin_audit_logs');
        Schema::dropIfExists('ai_request_logs');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
