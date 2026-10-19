<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->char('confirmation_token_hash', 64)->nullable()->unique();
            $table->timestamp('confirmation_token_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropUnique(['confirmation_token_hash']);
            $table->dropColumn(['confirmation_token_hash', 'confirmation_token_expires_at']);
        });
    }
};
