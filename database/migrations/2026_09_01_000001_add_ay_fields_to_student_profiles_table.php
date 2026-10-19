<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add AY / batch identity fields to student_profiles.
 *
 *   enrolled_ay      — the school year string in which the student first paid
 *                      their enrollment fee; e.g. "2025-2026".
 *                      Copied from enrollments.school_year at the moment
 *                      the cashier (or GCash verification) marks is_paid = true.
 *                      Written once, never updated.
 *
 *   graduation_year  — the calendar year the student graduated, e.g. 2026.
 *                      Written by the Registrar when promoting a student to alumni.
 *                      Nullable until set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            // "2025-2026" — same format used throughout enrollments.school_year
            $table->string('enrolled_ay', 20)->nullable()->after('program_id');

            // Four-digit calendar year
            $table->smallInteger('graduation_year')->unsigned()->nullable()->after('enrolled_ay');
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn(['enrolled_ay', 'graduation_year']);
        });
    }
};
