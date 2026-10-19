<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * student_profiles — student-specific institutional identity.
 *
 * Separates authentication identity (users table) from academic identity.
 * Created when:
 *   a) A new_applicant's payment is confirmed → promoted to student
 *   b) A student is imported via Bulk Import (future feature)
 *
 * The admission_type column mirrors applications.academic_status so the
 * classification persists after the application record is no longer the
 * primary context (e.g. after graduation, imports).
 *
 * This table intentionally has NO is_irregular column — irregularity is
 * a per-enrollment property (each semester a student may be regular or
 * irregular independently). See enrollments.is_irregular migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->onDelete('cascade');

            // Academic identity
            $table->string('student_number')->unique()->nullable();
            $table->string('lrn')->nullable();             // Learner Reference Number

            // Admission classification — not a role, not mutable with every enrollment.
            // Copied from applications.academic_status on approval/import.
            $table->enum('admission_type', [
                'new_student',
                'transferee',
                'shiftee',
                'returnee',
                'cross_enrollee',
                'imported',       // for Bulk Import path (no application record)
            ])->default('new_student');

            // Personal information (denormalised from applications for convenience;
            // applications record remains the source of truth for the original submission)
            $table->string('gender')->nullable();
            $table->date('birthdate')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();

            // Current program (may differ from application if student shifts program)
            $table->foreignId('program_id')
                ->nullable()
                ->constrained('programs')
                ->onDelete('set null');

            $table->timestamps();

            $table->index('student_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
