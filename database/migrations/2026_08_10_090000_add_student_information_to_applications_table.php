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
        Schema::table('applications', function (Blueprint $table) {
            // ── Applicant type ────────────────────────────────────────────
            $table->enum('academic_status', [
                'new_student', 'transferee', 'shiftee', 'returnee', 'cross_enrollee',
            ])->default('new_student')->after('program_id');

            // ── Personal information ─────────────────────────────────────
            $table->enum('gender', ['male', 'female'])->nullable()->after('middle_name');
            $table->string('birth_place')->nullable()->after('birthdate');
            $table->string('religion')->nullable()->after('birth_place');
            $table->string('nationality')->nullable()->default('Filipino')->after('religion');
            $table->string('lrn')->nullable()->after('nationality');
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])
                ->nullable()->after('lrn');

            // ── Structured address (existing "address" column left untouched) ──
            $table->string('current_address')->nullable()->after('address');
            $table->string('city')->nullable()->after('current_address');
            $table->string('province')->nullable()->after('city');
            $table->string('postal_code')->nullable()->after('province');
            $table->string('country')->nullable()->default('Philippines')->after('postal_code');

            // ── Family / parent information ──────────────────────────────
            $table->string('father_name')->nullable()->after('guardian_contact');
            $table->string('father_occupation')->nullable()->after('father_name');
            $table->string('mother_name')->nullable()->after('father_occupation');
            $table->string('mother_occupation')->nullable()->after('mother_name');
            $table->string('parent_address')->nullable()->after('mother_occupation');
            $table->string('parent_contact')->nullable()->after('parent_address');
            $table->string('spouse_name')->nullable()->after('parent_contact');

            // ── Other information ─────────────────────────────────────────
            $table->string('occupation')->nullable()->after('spouse_name');
            $table->string('disability')->nullable()->after('occupation');
            $table->string('pwd_id')->nullable()->after('disability');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn([
                'academic_status',
                'gender',
                'birth_place',
                'religion',
                'nationality',
                'lrn',
                'marital_status',
                'current_address',
                'city',
                'province',
                'postal_code',
                'country',
                'father_name',
                'father_occupation',
                'mother_name',
                'mother_occupation',
                'parent_address',
                'parent_contact',
                'spouse_name',
                'occupation',
                'disability',
                'pwd_id',
            ]);
        });
    }
};