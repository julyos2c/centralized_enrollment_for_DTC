<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Structured education details. All columns are nullable and additive:
     * the existing text columns (most_recent_school, school_location,
     * shs_strand, school_year_completed, education_status) keep being filled,
     * so existing records, reports, and views are unaffected.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('most_recent_school')
                ->constrained('schools')->nullOnDelete();
            // Snapshot of the official ID and sector at submission time.
            $table->string('school_deped_id', 20)->nullable()->after('school_id');
            $table->string('school_sector', 20)->nullable()->after('school_deped_id');
            $table->string('school_region_code', 10)->nullable()->after('school_location');
            $table->string('school_province_code', 10)->nullable()->after('school_region_code');
            $table->string('school_city_code', 10)->nullable()->after('school_province_code');
            $table->string('school_barangay')->nullable()->after('school_city_code');
            $table->string('school_street')->nullable()->after('school_barangay');
            $table->string('school_year_started', 30)->nullable()->after('school_year_completed');
            $table->string('education_status_other')->nullable()->after('education_status');
            $table->foreignId('shs_curriculum_id')->nullable()->after('shs_strand')
                ->constrained('shs_curricula')->nullOnDelete();
            $table->foreignId('shs_classification_id')->nullable()->after('shs_curriculum_id')
                ->constrained('shs_classifications')->nullOnDelete();
            // "selected" | "other" | "not_sure"; null for older applications.
            $table->string('shs_strand_choice', 20)->nullable()->after('shs_classification_id');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropForeign(['shs_curriculum_id']);
            $table->dropForeign(['shs_classification_id']);
            $table->dropColumn([
                'school_id', 'school_deped_id', 'school_sector', 'school_region_code',
                'school_province_code', 'school_city_code', 'school_barangay', 'school_street',
                'school_year_started', 'education_status_other', 'shs_curriculum_id',
                'shs_classification_id', 'shs_strand_choice',
            ]);
        });
    }
};
