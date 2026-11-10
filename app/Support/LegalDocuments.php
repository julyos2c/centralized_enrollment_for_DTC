<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\Carbon;

/** Shared data for the Terms and Privacy Notice views. */
class LegalDocuments
{
    public static function meta(string $document): array
    {
        $meta = config("legal.{$document}");
        $date = fn (?string $value) => $value ? Carbon::parse($value)->format('F j, Y') : null;

        return [
            'title' => $meta['title'],
            'version' => $meta['version'],
            'effective_date' => $date($meta['effective_date'] ?? null),
            'last_updated' => $date($meta['last_updated'] ?? null),
        ];
    }

    /** Institution and privacy contact details from Settings (never invented). */
    public static function context(): array
    {
        $value = fn (string $key) => filled($v = Setting::get($key)) ? $v : null;

        return [
            'institution' => $value('institution_name') ?? 'Danao Technological College',
            'system' => 'DTC Nexus',
            'address' => $value('address'),
            'dpo' => [
                'name' => $value('privacy_officer_name'),
                'email' => $value('privacy_officer_email'),
                'phone' => $value('privacy_officer_phone'),
                'office' => $value('privacy_officer_office'),
            ],
            'terms' => self::meta('terms'),
            'privacy' => self::meta('privacy'),
            'sensitive' => self::meta('sensitive_consent'),
        ];
    }
}
