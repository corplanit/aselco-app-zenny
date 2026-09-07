<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_conversation')) {
            return;
        }

        Schema::table('chat_conversation', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_conversation', 'kind')) {
                $table->string('kind', 32)->default('general')->after('type')->index();
            }
            if (! Schema::hasColumn('chat_conversation', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->after('kind')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('chat_conversation', 'status')) {
                $table->string('status', 32)->default('open')->after('customer_id')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('chat_conversation')) {
            return;
        }

        Schema::table('chat_conversation', function (Blueprint $table) {
            if (Schema::hasColumn('chat_conversation', 'customer_id')) {
                $table->dropConstrainedForeignId('customer_id');
            }
            if (Schema::hasColumn('chat_conversation', 'kind')) {
                $table->dropColumn('kind');
            }
            if (Schema::hasColumn('chat_conversation', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
