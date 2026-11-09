<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-document review. Existing uploads keep their files and become
     * "submitted" (awaiting review). Replaced uploads are never deleted;
     * they are marked superseded so the review history stays on file.
     */
    public function up(): void
    {
        Schema::table('application_documents', function (Blueprint $table) {
            $table->foreignId('admission_requirement_id')->nullable()->after('application_id')
                ->constrained('admission_requirements')->nullOnDelete();
            // submitted | approved | rejected
            $table->string('status', 20)->default('submitted')->after('size_bytes');
            $table->text('review_remarks')->nullable()->after('status');
            $table->foreignId('reviewed_by')->nullable()->after('review_remarks')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->timestamp('superseded_at')->nullable()->after('reviewed_at');

            $table->index(['application_id', 'superseded_at']);
            $table->index('status');
        });

        // Link existing uploads to the requirement with the same code.
        foreach (DB::table('admission_requirements')->pluck('id', 'code') as $code => $id) {
            DB::table('application_documents')
                ->whereNull('admission_requirement_id')
                ->where('document_type', $code)
                ->update(['admission_requirement_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('application_documents', function (Blueprint $table) {
            $table->dropForeign(['admission_requirement_id']);
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex(['application_id', 'superseded_at']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'admission_requirement_id', 'status', 'review_remarks',
                'reviewed_by', 'reviewed_at', 'superseded_at',
            ]);
        });
    }
};
