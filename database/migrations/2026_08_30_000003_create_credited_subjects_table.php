<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * credited_subjects — registrar documentation of subjects credited/waived
 * for a transferee, shiftee, or cross-enrollee.
 *
 * This is documentation only. It intentionally does NOT alter:
 *   - the enrollment's subject_ids
 *   - COR rendering / total units
 *   - payment / tuition calculation
 *
 * A credited subject always maps to a real DTC course_subject (the subject
 * being waived); equivalent_subject/equivalent_school describe the outside
 * course that satisfied it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credited_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_subject_id')->constrained('course_subjects')->onDelete('cascade');

            $table->string('equivalent_subject')->nullable();
            $table->string('equivalent_school')->nullable();
            $table->decimal('credited_units', 3, 1)->nullable();
            $table->text('remarks')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->unique(['enrollment_id', 'course_subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credited_subjects');
    }
};
