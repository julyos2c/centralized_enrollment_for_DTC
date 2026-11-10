<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track how far each school record has been checked:
     *   verified      matches an official DepEd record (School ID and location)
     *   needs_review  from an official source, but a detail (e.g. location) is unclear
     *   unverified    added by staff or from an unofficial directory, not yet checked
     */
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('verification_status', 20)->default('unverified')->after('source');
            $table->string('verification_note', 500)->nullable()->after('verification_status');
            $table->index(['is_active', 'city_code']);
        });

        // Rows already imported from DepEd's list carry an official School ID.
        DB::table('schools')->where('source', 'like', 'deped%')->whereNotNull('deped_school_id')
            ->whereNotNull('city_code')->update(['verification_status' => 'verified']);
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'city_code']);
            $table->dropColumn(['verification_status', 'verification_note']);
        });
    }
};
