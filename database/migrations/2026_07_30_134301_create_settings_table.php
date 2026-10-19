<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed default values so the General Settings page is never empty
        $defaults = [
            ['key' => 'institution_name', 'value' => 'Danao Technological College'],
            ['key' => 'system_name',      'value' => 'DTC Enrollment Management System (EMS)'],
            ['key' => 'tagline',          'value' => 'Excellence in Education, Service to the Community.'],
            ['key' => 'address',          'value' => 'Sta. Rosa St., Danao City, Cebu, Philippines'],
            ['key' => 'default_language', 'value' => 'en'],
            ['key' => 'default_timezone', 'value' => 'Asia/Manila'],
            ['key' => 'date_format',      'value' => 'm/d/Y'],
            ['key' => 'time_format',      'value' => '12'],
            ['key' => 'items_per_page',   'value' => '10'],
            ['key' => 'maintenance_mode', 'value' => '0'],
            ['key' => 'enrollment_fee',   'value' => '500'],
            ['key' => 'gcash_number',     'value' => ''],
            ['key' => 'gcash_name',       'value' => ''],
            ['key' => 'enrollment_open',  'value' => '1'],
            ['key' => 'email_notifications', 'value' => '1'],
            ['key' => 'sms_notifications',   'value' => '0'],
            ['key' => 'app_notifications',   'value' => '1'],
            ['key' => 'min_password_length',   'value' => '8'],
            ['key' => 'require_special_chars', 'value' => '0'],
            ['key' => 'session_timeout',       'value' => '60'],
            ['key' => 'max_login_attempts',    'value' => '5'],
        ];

        DB::table('settings')->insert(
            array_map(fn ($row) => array_merge($row, [
                'created_at' => now(),
                'updated_at' => now(),
            ]), $defaults)
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};