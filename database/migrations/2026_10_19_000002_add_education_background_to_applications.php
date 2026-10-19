<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('submission_token_hash', 64)->nullable()->unique();
            $table->string('education_level', 100)->nullable();
            $table->string('education_status', 100)->nullable();
            $table->string('most_recent_school')->nullable();
            $table->string('school_year_completed', 30)->nullable();
            $table->string('school_location')->nullable();
            $table->string('shs_strand', 100)->nullable();
            $table->string('previous_program')->nullable();
            $table->string('last_year_level', 100)->nullable();
            $table->string('home_institution')->nullable();
            $table->string('home_program')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropUnique(['submission_token_hash']);
            $table->dropColumn([
                'submission_token_hash',
                'education_level',
                'education_status',
                'most_recent_school',
                'school_year_completed',
                'school_location',
                'shs_strand',
                'previous_program',
                'last_year_level',
                'home_institution',
                'home_program',
            ]);
        });
    }
};
