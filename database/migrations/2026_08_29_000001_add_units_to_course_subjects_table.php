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
        Schema::table('course_subjects', function (Blueprint $table) {
            $table->decimal('units', 3, 1)->default(3.0)->after('subject_name'); // e.g. 3.0, 1.5
        });
    }

    public function down(): void
    {
        Schema::table('course_subjects', function (Blueprint $table) {
            $table->dropColumn('units');
        });
    }
};