<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_complaints')) {
            return;
        }

        Schema::table('customer_complaints', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_complaints', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('customer_complaints', 'attachment')) {
                $table->string('attachment', 500)->nullable()->after('complaint');
            }
            if (! Schema::hasColumn('customer_complaints', 'status')) {
                $table->string('status', 40)->default('Pending')->after('attachment');
            }
            if (! Schema::hasColumn('customer_complaints', 'reference_number')) {
                $table->string('reference_number', 40)->nullable()->after('status');
            }
            if (! Schema::hasColumn('customer_complaints', 'priority')) {
                $table->string('priority', 20)->nullable();
            }
        });
    }

    public function down(): void
    {
        // Live databases already had these columns before this alignment migration.
    }
};
