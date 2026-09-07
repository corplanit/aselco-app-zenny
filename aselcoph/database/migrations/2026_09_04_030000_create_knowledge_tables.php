<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name', 160);
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('knowledge_categories')->restrictOnDelete();
            $table->string('title', 200);
            $table->string('department', 40)->nullable();
            $table->string('service_type', 80)->nullable();
            $table->string('source', 160)->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('current_version')->default(1);
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('file_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'category_id']);
        });

        Schema::create('knowledge_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('knowledge_documents')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('body');
            $table->string('checksum', 64);
            $table->timestamp('indexed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['document_id', 'version']);
        });

        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('knowledge_documents')->cascadeOnDelete();
            $table->foreignId('version_id')->constrained('knowledge_document_versions')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->text('content');
            $table->json('tokens')->nullable();
            $table->json('embedding')->nullable();
            $table->string('embedding_model', 80)->nullable();
            $table->unsignedSmallInteger('char_count')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['active', 'document_id']);
        });

        Schema::create('knowledge_retrieval_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20);
            $table->string('query_hash', 64);
            $table->unsignedSmallInteger('query_chars')->default(0);
            $table->unsignedTinyInteger('hit_count')->default(0);
            $table->decimal('top_score', 8, 4)->nullable();
            $table->boolean('sufficient')->default(false);
            $table->json('hit_ids')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_retrieval_logs');
        Schema::dropIfExists('knowledge_chunks');
        Schema::dropIfExists('knowledge_document_versions');
        Schema::dropIfExists('knowledge_documents');
        Schema::dropIfExists('knowledge_categories');
    }
};
