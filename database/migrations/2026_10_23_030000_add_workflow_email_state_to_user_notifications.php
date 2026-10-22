<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            // Null means a historical event: never replay old emails on deployment.
            $table->string('email_status', 32)->nullable();
            $table->timestamp('email_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', fn (Blueprint $table) => $table->dropColumn(['email_status', 'email_sent_at']));
    }
};
