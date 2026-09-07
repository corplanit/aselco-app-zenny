<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ticket_categories')) {
            return;
        }

        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160)->unique();
            $table->string('department_code', 40);
            $table->string('default_assignee_role', 80);
            $table->unsignedInteger('sla_minutes');
            $table->boolean('requires_payment_check')->default(false);
            $table->boolean('requires_tsd_check')->default(false);
            $table->timestamps();
        });

        DB::table('ticket_categories')->insert([
            [
                'name' => 'Transmission Line / Sub-transmission Line / Power Substation',
                'department_code' => 'TSD',
                'default_assignee_role' => 'scada_operator',
                'sla_minutes' => 60,
                'requires_payment_check' => false,
                'requires_tsd_check' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Distribution Line',
                'department_code' => 'COMD',
                'default_assignee_role' => 'comd_day_or_guard_night',
                'sla_minutes' => 30,
                'requires_payment_check' => false,
                'requires_tsd_check' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Billing & Collection',
                'department_code' => 'CCAD',
                'default_assignee_role' => 'ccad',
                'sla_minutes' => 10,
                'requires_payment_check' => true,
                'requires_tsd_check' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'kWh meter and/or application for electric service connection',
                'department_code' => 'AO-CDS',
                'default_assignee_role' => 'ao_cds',
                'sla_minutes' => 10,
                'requires_payment_check' => false,
                'requires_tsd_check' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Institutional concerns or requests',
                'department_code' => 'ISD-CCSMDD',
                'default_assignee_role' => 'isd_ccsmdd',
                'sla_minutes' => 10,
                'requires_payment_check' => false,
                'requires_tsd_check' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Other concerns involving other departments',
                'department_code' => 'FOCAL',
                'default_assignee_role' => 'designated_focal_person',
                'sla_minutes' => 10,
                'requires_payment_check' => false,
                'requires_tsd_check' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_categories');
    }
};
