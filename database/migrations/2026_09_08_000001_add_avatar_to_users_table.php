<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add avatar column to users table.
 *
 * Stores the storage-disk-relative path of the user's uploaded profile
 * photo (e.g. "avatars/abc123.jpg").  Null means no custom photo; the
 * UI falls back to an initials avatar in that case.
 *
 * Nullable so existing rows are unaffected and no backfill is needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Placed after 'status' to keep the columns grouped logically.
            $table->string('avatar')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar');
        });
    }
};
