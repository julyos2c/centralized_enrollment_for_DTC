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
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('appointment_id')->nullable()->constrained()->onDelete('set null');
            $table->string('document_type'); // tor, diploma, certification, true_copy
            $table->integer('copies')->default(1);
            $table->text('purpose')->nullable();
            $table->enum('status', ['submitted', 'processing', 'ready', 'released'])->default('submitted');
            $table->decimal('fee', 8, 2)->default(0.00);
            $table->boolean('is_paid')->default(false);
            $table->string('receipt_no')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_requests');
    }
};
