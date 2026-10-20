<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->string('event_key', 120)->nullable()->after('link');
            $table->unique(['user_id', 'event_key'], 'user_notifications_user_event_unique');
        });

        Schema::create('document_payment_notification_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 120)->unique();
            $table->unsignedBigInteger('document_request_id')->index();
            $table->json('recipient_ids');
            $table->string('title');
            $table->text('message');
            $table->string('type', 20)->default('success');
            $table->string('link');
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(8);
            $table->timestamp('available_at')->index();
            $table->timestamp('delivered_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_payment_notification_outbox');

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropUnique('user_notifications_user_event_unique');
            $table->dropColumn('event_key');
        });
    }
};
