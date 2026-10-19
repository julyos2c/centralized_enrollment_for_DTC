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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();

            // ── Public reference number (shown to applicant, no account needed to look it up) ──
            $table->string('reference_no')->unique();

            // ── Program applied for ──────────────────────────────────────────
            $table->foreignId('program_id')->constrained()->onDelete('restrict');

            // ── Applicant-submitted information (captured before any account exists) ──
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->date('birthdate')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_contact')->nullable();

            // ── Review workflow state ────────────────────────────────────────
            $table->enum('status', [
                'submitted',
                'under_review',
                'revision_required',
                'approved',
                'rejected',
            ])->default('submitted');

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('remarks')->nullable();

            // ── Link to the account created upon approval (nullable until then) ──
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();

            // ── Indexes for common lookups ───────────────────────────────────
            $table->index('status');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};