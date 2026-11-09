<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrance_exam_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->date('exam_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('venue', 200);
            $table->unsignedInteger('capacity');
            // open | closed | cancelled
            $table->string('status', 20)->default('open');
            $table->string('school_year', 20)->nullable();
            $table->string('semester', 20)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['exam_date', 'status']);
        });

        Schema::create('entrance_exam_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entrance_exam_session_id')->constrained()->restrictOnDelete();
            // pending | confirmed | rejected | cancelled | rescheduled | attended | absent
            $table->string('status', 20)->default('pending');
            // Equals application_id while the booking holds the applicant's
            // single active seat; NULL otherwise. The unique index makes
            // double booking impossible even under concurrent requests.
            $table->unsignedBigInteger('active_application_id')->nullable()->unique();
            $table->decimal('score', 6, 2)->nullable();
            // passed | failed
            $table->string('result', 20)->nullable();
            $table->text('result_remarks')->nullable();
            $table->timestamp('result_recorded_at')->nullable();
            $table->foreignId('result_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('result_released_at')->nullable();
            $table->timestamp('attendance_marked_at')->nullable();
            $table->foreignId('attendance_marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('staff_remarks')->nullable();
            $table->foreignId('rescheduled_to_id')->nullable()->constrained('entrance_exam_bookings')->nullOnDelete();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();

            $table->index(['entrance_exam_session_id', 'status']);
            $table->index(['application_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrance_exam_bookings');
        Schema::dropIfExists('entrance_exam_sessions');
    }
};
