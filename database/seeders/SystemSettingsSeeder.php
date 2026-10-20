<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Mandatory system reference data: operational defaults for the settings table.
 *
 * Insert-only: a key is written only when it does not exist yet, so values an
 * administrator has changed are never overwritten. Institutional, academic and
 * financial values (institution details, academic term, fees, GCash payee,
 * registrar name, enrollment open/closed) are deliberately NOT seeded here;
 * they must come from approved institutional records via the admin Settings
 * pages or AcademicConfigurationSeeder.
 */
class SystemSettingsSeeder extends Seeder
{
    public const DEFAULTS = [
        'default_language'      => 'en',
        'default_timezone'      => 'Asia/Manila',
        'date_format'           => 'm/d/Y',
        'time_format'           => '12',
        'items_per_page'        => '10',
        'maintenance_mode'      => '0',
        'email_notifications'   => '1',
        'sms_notifications'     => '0',
        'app_notifications'     => '1',
        'min_password_length'   => '8',
        'require_special_chars' => '0',
        'session_timeout'       => '60',
        'max_login_attempts'    => '5',
    ];

    public function run(): void
    {
        $created = [];

        foreach (self::DEFAULTS as $key => $value) {
            $setting = Setting::firstOrCreate(['key' => $key], ['value' => $value]);
            if ($setting->wasRecentlyCreated) {
                $created[] = $key;
            }
        }

        $this->command?->info($created
            ? 'Settings created: ' . implode(', ', $created)
            : 'Settings: all ' . count(self::DEFAULTS) . ' operational defaults already present, nothing changed.');
    }
}
