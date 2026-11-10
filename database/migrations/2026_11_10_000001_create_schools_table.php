<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * School reference list for the application form's school lookup.
     * Rows come from imported DepEd lists (`schools:import`) or are added
     * by staff. Location codes are PSGC codes when they could be matched.
     */
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            // DepEd School ID (BEIS); null for schools without an official ID.
            $table->string('deped_school_id', 20)->nullable()->unique();
            $table->string('name');
            // public | private | suc | luc ; null when the source does not say.
            $table->string('sector', 20)->nullable();
            $table->string('region_code', 10)->nullable();
            $table->string('province_code', 10)->nullable();
            $table->string('city_code', 10)->nullable();
            $table->string('barangay')->nullable();
            $table->string('street_address')->nullable();
            // Location exactly as the source recorded it (for review and re-matching).
            $table->string('source_location')->nullable();
            // Legacy (2016) SHS program offerings, e.g. ["STEM","ABM","TVL"].
            $table->json('offerings')->nullable();
            $table->string('source', 60)->default('manual');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index(['is_active', 'name']);
            $table->index('city_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
