<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_cancellation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('previous_status', 32);
            $table->string('status', 16)->default('pending');
            $table->string('reason', 500);
            $table->string('review_remarks', 500)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'status'], 'app_cancel_requests_app_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_cancellation_requests');
    }
};
