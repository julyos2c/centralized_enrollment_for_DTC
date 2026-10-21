<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_import_batch_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_import_batch_id')->constrained('academic_import_batches')->cascadeOnDelete();
            $table->foreignId('student_profile_id')->unique()->constrained('student_profiles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['academic_import_batch_id', 'student_profile_id'], 'import_batch_profile_pair_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_import_batch_profiles');
    }
};
