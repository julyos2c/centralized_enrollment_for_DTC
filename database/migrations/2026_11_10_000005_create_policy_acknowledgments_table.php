<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only record of which policy version an applicant agreed to or
     * acknowledged, and when. Rows are never updated: a new version creates
     * a new row, so earlier acknowledgments stay on file.
     */
    public function up(): void
    {
        Schema::create('policy_acknowledgments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // terms (agreement) | privacy (acknowledgment) | sensitive_consent (consent)
            $table->string('document', 30);
            $table->string('kind', 20);
            $table->string('version', 20);
            $table->timestamp('acknowledged_at');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['application_id', 'document']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_acknowledgments');
    }
};
