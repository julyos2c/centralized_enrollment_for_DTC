<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointment_slots', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('max_bookings');
            $table->string('status', 20)->default('open')->after('source');
            $table->index(['date', 'start_time', 'end_time']);
        });

        Schema::create('office_closures', function (Blueprint $table) {
            $table->id();
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('title');
            $table->string('type', 40);
            $table->text('reason')->nullable();
            $table->boolean('is_confirmed')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['start_date', 'end_date', 'is_confirmed', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_closures');
        Schema::table('appointment_slots', function (Blueprint $table) {
            $table->dropIndex(['date', 'start_time', 'end_time']);
            $table->dropColumn(['source', 'status']);
        });
    }
};
