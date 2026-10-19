<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('class_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_subject_id')->constrained()->onDelete('cascade');
            $table->string('day_pattern');        // e.g. "MWF", "TTH", "MONDAY"
            $table->time('time_start');
            $table->time('time_end');
            $table->string('room')->default('TBA');
            $table->string('instructor_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_schedules');
    }
};