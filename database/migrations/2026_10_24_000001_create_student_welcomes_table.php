<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_welcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('promoted_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->uuid('claim_token')->nullable();
            $table->timestamp('claimed_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_welcomes');
    }
};
