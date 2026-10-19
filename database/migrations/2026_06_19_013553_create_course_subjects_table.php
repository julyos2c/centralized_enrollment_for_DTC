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
        Schema::create('course_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->onDelete('cascade');
            $table->string('subject_code');   // e.g. "IS101"
            $table->string('subject_name');   // e.g. "Introduction to Computing"
            $table->integer('year_level');    // 1, 2, 3, 4
            $table->integer('semester');      // 1 or 2
            $table->timestamps();
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('course_subjects');
    }
};
