<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_method')->default('walk_in')->after('enrollment_id');
            $table->string('reference_number')->nullable()->after('payment_method');
            $table->string('proof_of_payment')->nullable()->after('reference_number');
            $table->string('status')->default('verified')->after('proof_of_payment');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete()->after('status');
            $table->timestamp('verified_at')->nullable()->after('verified_by');
        });

        // Make processed_by nullable without Doctrine: drop FK, drop column, re-add nullable
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['processed_by']);
            $table->dropColumn('processed_by');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['processed_by']);
            $table->dropColumn('processed_by');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('processed_by')->constrained('users')->after('id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropColumn([
                'payment_method',
                'reference_number',
                'proof_of_payment',
                'status',
                'verified_by',
                'verified_at',
            ]);
        });
    }
};