<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticket_status_history')) {
            return;
        }

        Schema::table('ticket_status_history', function (Blueprint $table) {
            if (! Schema::hasColumn('ticket_status_history', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('remarks');
            }
            if (! Schema::hasColumn('ticket_status_history', 'user_agent')) {
                $table->string('user_agent', 512)->nullable()->after('ip_address');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ticket_status_history')) {
            return;
        }

        Schema::table('ticket_status_history', function (Blueprint $table) {
            if (Schema::hasColumn('ticket_status_history', 'user_agent')) {
                $table->dropColumn('user_agent');
            }
            if (Schema::hasColumn('ticket_status_history', 'ip_address')) {
                $table->dropColumn('ip_address');
            }
        });
    }
};
