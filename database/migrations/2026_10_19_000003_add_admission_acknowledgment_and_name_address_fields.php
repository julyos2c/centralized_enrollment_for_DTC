<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('honorific', 20)->nullable();
            $table->string('suffix', 20)->nullable();
            $table->string('region')->nullable();
            $table->string('barangay')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['honorific', 'suffix', 'region', 'barangay', 'acknowledged_at']);
        });
    }
};
