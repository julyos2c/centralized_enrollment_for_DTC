<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->string('payment_method')->default('walk_in')->after('is_paid');
            $table->string('payment_status')->default('unpaid')->after('payment_method');
            $table->decimal('amount_received', 8, 2)->nullable()->after('payment_status');
            $table->string('reference_number')->nullable()->after('amount_received');
            $table->string('proof_of_payment')->nullable()->after('reference_number');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete()->after('proof_of_payment');
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->timestamp('payment_submitted_at')->nullable()->after('verified_at');
            $table->text('payment_rejection_reason')->nullable()->after('payment_submitted_at');
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete()->after('payment_rejection_reason');
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
        });

        // Preserve the legacy paid flag and receipt information for existing rows.
        DB::table('document_requests')->where('is_paid', true)->update([
            'payment_status' => 'verified',
            'amount_received' => DB::raw('fee'),
        ]);
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropColumn([
                'payment_method', 'payment_status', 'amount_received', 'reference_number',
                'proof_of_payment', 'verified_by', 'verified_at', 'payment_submitted_at',
                'payment_rejection_reason', 'rejected_by', 'rejected_at',
            ]);
        });
    }
};
