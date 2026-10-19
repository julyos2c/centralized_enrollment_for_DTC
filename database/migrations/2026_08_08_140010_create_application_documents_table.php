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
        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('application_id')->constrained()->onDelete('cascade');

            // e.g. birth_certificate, form137, good_moral, id_photo
            $table->string('document_type');

            // Path on the PRIVATE/local disk — never a publicly resolvable URL.
            // e.g. applications/{application_id}/{uuid}.pdf
            $table->string('file_path');

            // Original client-side filename, kept for display only (not used as storage name).
            $table->string('original_name');

            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();

            $table->timestamps();

            $table->index(['application_id', 'document_type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('application_documents');
    }
};