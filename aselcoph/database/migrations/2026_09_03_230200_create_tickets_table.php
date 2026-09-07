<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tickets')) {
            return;
        }

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_no', 40)->unique();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('ticket_categories')->restrictOnDelete();
            $table->string('subcategory', 160)->nullable();
            $table->string('channel', 20);
            $table->text('description');
            $table->string('status', 32)->default('new');
            $table->string('priority', 20)->default('normal');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assigned_department', 40)->nullable();
            $table->timestamp('sla_due_at')->nullable();
            $table->string('created_by', 40);
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['assigned_department', 'status']);
            $table->index(['category_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
