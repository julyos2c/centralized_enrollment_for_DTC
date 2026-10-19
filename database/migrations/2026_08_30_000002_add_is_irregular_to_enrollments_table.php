<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds is_irregular to enrollments.
 *
 * Irregular status is a per-enrollment (per-semester) property, not a
 * permanent user attribute. A student can be regular in Year 1 Sem 1,
 * irregular in Year 2 Sem 1, and regular again in Year 3 Sem 1.
 *
 * This is why it does NOT belong in users or student_profiles.
 * Do NOT create users.role = 'irregular'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->boolean('is_irregular')
                ->default(false)
                ->after('school_year')
                ->comment('True if the student is taking subjects outside their normal year-level curriculum this semester.');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('is_irregular');
        });
    }
};
