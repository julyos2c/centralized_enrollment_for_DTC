<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record the admission term an application was submitted for so the
     * admissions dashboard and reports can filter by academic year and
     * semester. Existing applications are stamped with the term configured
     * in Settings at migration time (the only term the system has run).
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('school_year', 20)->nullable()->after('program_id');
            $table->string('semester', 20)->nullable()->after('school_year');
            $table->index(['school_year', 'semester']);
        });

        $schoolYear = DB::table('settings')->where('key', 'current_school_year')->value('value');
        $semester = DB::table('settings')->where('key', 'current_semester')->value('value');
        if (filled($schoolYear)) {
            DB::table('applications')->whereNull('school_year')->update([
                'school_year' => $schoolYear,
                'semester' => filled($semester) ? $semester : null,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex(['school_year', 'semester']);
            $table->dropColumn(['school_year', 'semester']);
        });
    }
};
