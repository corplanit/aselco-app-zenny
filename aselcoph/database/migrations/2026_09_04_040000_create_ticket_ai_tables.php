<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->unsignedBigInteger('recommended_category_id')->nullable();
            $table->boolean('category_matches_submitted')->default(true);
            $table->string('recommended_priority', 20)->nullable();
            $table->string('sentiment', 40)->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->text('summary')->nullable();
            $table->string('suggested_department', 40)->nullable();
            $table->unsignedBigInteger('suggested_assignee_id')->nullable();
            $table->string('recommended_next_action', 80)->nullable();
            $table->text('suggested_response')->nullable();
            $table->json('routing_validation')->nullable();
            $table->json('knowledge_citations')->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('model', 80)->nullable();
            $table->string('source', 40)->default('fallback');
            $table->boolean('ok')->default(true);
            $table->string('error_code', 64)->nullable();
            $table->boolean('human_override')->default(false);
            $table->string('override_field', 40)->nullable();
            $table->string('override_reason', 500)->nullable();
            $table->foreignId('override_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('override_at')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();

            $table->foreign('recommended_category_id')->references('id')->on('ticket_categories')->nullOnDelete();
            $table->foreign('suggested_assignee_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['ticket_id', 'analyzed_at']);
            $table->index(['sentiment', 'ok']);
        });

        Schema::create('ticket_ai_response_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('analysis_id')->nullable()->constrained('ticket_ai_analyses')->nullOnDelete();
            $table->text('draft');
            $table->text('edited_body')->nullable();
            $table->string('status', 20)->default('pending_review');
            $table->decimal('confidence', 5, 4)->nullable();
            $table->boolean('auto_send_eligible')->default(false);
            $table->boolean('auto_sent')->default(false);
            $table->boolean('created_by_ai')->default(true);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_notes', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_ai_response_drafts');
        Schema::dropIfExists('ticket_ai_analyses');
    }
};
