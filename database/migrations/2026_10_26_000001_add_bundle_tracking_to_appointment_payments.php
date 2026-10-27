<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_payments', function (Blueprint $table) {
            $table->boolean('is_document_bundle')->default(false)->after('appointment_id');
            $table->string('receipt_no')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_payments', function (Blueprint $table) {
            $table->dropColumn(['is_document_bundle', 'receipt_no']);
        });
    }
};
