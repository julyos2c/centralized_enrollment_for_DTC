<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('suffix', 30)->nullable();
            $table->string('email')->nullable();
            $table->unsignedTinyInteger('year_level')->nullable();
            $table->string('academic_status', 20)->default('active');
            $table->date('graduation_date')->nullable();
            $table->index('academic_status');
            $table->index('graduation_year');
        });

        if (Schema::hasTable('roles') && Schema::hasTable('model_has_roles')) {
            $roleId = DB::table('roles')->where('name', 'alumni')->value('id');
            if ($roleId) {
                $userIds = DB::table('model_has_roles')->where('role_id', $roleId)
                    ->where('model_type', 'App\\Models\\User')->pluck('model_id');
                if ($userIds->isNotEmpty()) DB::table('student_profiles')->whereIn('user_id', $userIds)->update(['academic_status' => 'alumni']);
            }
        }

        Schema::create('academic_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->string('import_type', 20);
            $table->string('status', 20)->default('previewed');
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_path');
            $table->string('source_sha256', 64);
            $table->string('report_path')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('existing_rows')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('previewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
            $table->index(['import_type', 'created_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('academic_import_batches') && DB::table('academic_import_batches')->exists()) {
            throw new RuntimeException('Cannot roll back while academic import history exists.');
        }
        if (Schema::hasColumn('student_profiles', 'academic_status') && DB::table('student_profiles')->whereNull('user_id')->exists()) {
            throw new RuntimeException('Cannot roll back while account-free academic records exist.');
        }
        if (Schema::hasColumn('student_profiles', 'academic_status') && DB::table('student_profiles')->where(function ($query) {
            $query->where('academic_status', '!=', 'active')->orWhereNotNull('first_name')->orWhereNotNull('middle_name')
                ->orWhereNotNull('last_name')->orWhereNotNull('suffix')->orWhereNotNull('email')->orWhereNotNull('year_level')->orWhereNotNull('graduation_date');
        })->exists()) {
            throw new RuntimeException('Cannot roll back while the new academic record fields contain data.');
        }

        Schema::dropIfExists('academic_import_batches');
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['academic_status']);
            $table->dropIndex(['graduation_year']);
            $table->dropColumn(['first_name', 'middle_name', 'last_name', 'suffix', 'email', 'year_level', 'academic_status', 'graduation_date']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
