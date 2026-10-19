<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The original payments table set `status` to default to 'verified'.
 * That means any insert that forgets to set status explicitly silently
 * creates an already-verified payment with no cashier/verifier attached
 * — a real data-integrity risk, and the most likely explanation for
 * "verified payment with an empty Processed By" seen in production data.
 *
 * All current write paths (walk-in store(), GCash submitGcash()) already
 * set status explicitly, so this is defense-in-depth: the safe default
 * for a payment nobody has acted on yet is 'pending', not 'verified'.
 *
 * Uses raw SQL (not ->change()) since this app doesn't have
 * doctrine/dbal installed.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL-specific syntax; SQLite (used for the test suite) doesn't
        // support ALTER COLUMN SET DEFAULT and doesn't need this fix since
        // tests always set `status` explicitly on every Payment they create.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments ALTER status SET DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments ALTER status SET DEFAULT 'verified'");
        }
    }
};
