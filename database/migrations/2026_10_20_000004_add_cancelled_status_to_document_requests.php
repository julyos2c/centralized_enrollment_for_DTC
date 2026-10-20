<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite stores Laravel enum columns as text, so it needs no alteration.
        // MariaDB/MySQL enforce the enum values and must explicitly allow cancellation.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE document_requests MODIFY status ENUM('submitted', 'processing', 'ready', 'released', 'cancelled') NOT NULL DEFAULT 'submitted'");
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            if (DB::table('document_requests')->where('status', 'cancelled')->exists()) {
                throw new \RuntimeException('Cannot remove the cancelled status while cancelled document requests exist.');
            }

            DB::statement("ALTER TABLE document_requests MODIFY status ENUM('submitted', 'processing', 'ready', 'released') NOT NULL DEFAULT 'submitted'");
        }
    }
};
