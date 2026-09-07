<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticket_actions')) {
            return;
        }

        Schema::table('ticket_actions', function (Blueprint $table) {
            if (! Schema::hasColumn('ticket_actions', 'requires_payment')) {
                $table->boolean('requires_payment')->nullable()->after('minutes_taken');
            }
            if (! Schema::hasColumn('ticket_actions', 'requires_tsd_intervention')) {
                $table->boolean('requires_tsd_intervention')->nullable()->after('requires_payment');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ticket_actions')) {
            return;
        }

        Schema::table('ticket_actions', function (Blueprint $table) {
            foreach (['requires_tsd_intervention', 'requires_payment'] as $column) {
                if (Schema::hasColumn('ticket_actions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
