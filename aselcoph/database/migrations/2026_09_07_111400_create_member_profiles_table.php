<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('region_code', 20)->nullable();
            $table->string('region_name', 120);
            $table->string('province_code', 20)->nullable();
            $table->string('province_name', 120)->nullable();
            $table->string('city_municipality_code', 20)->nullable();
            $table->string('city_municipality_name', 120);
            $table->string('barangay_code', 20)->nullable();
            $table->string('barangay_name', 120);
            $table->string('street')->nullable();
            $table->string('sitio')->nullable();
            $table->string('civil_status', 40);
            $table->string('sex', 20);
            $table->string('contact_no', 40);
            $table->date('date_of_seminar')->nullable();
            $table->text('remarks')->nullable();
            $table->string('address', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_profiles');
    }
};
