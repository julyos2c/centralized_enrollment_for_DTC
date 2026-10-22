<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE applications MODIFY status ENUM('submitted','under_review','revision_required','approved','rejected','cancelled') NOT NULL DEFAULT 'submitted'");
    }

    public function down(): void
    {
        if (DB::table('applications')->where('status', 'cancelled')->exists()) {
            throw new RuntimeException('Cannot remove the cancelled status while cancelled applications exist.');
        }

        DB::statement("ALTER TABLE applications MODIFY status ENUM('submitted','under_review','revision_required','approved','rejected') NOT NULL DEFAULT 'submitted'");
    }
};
