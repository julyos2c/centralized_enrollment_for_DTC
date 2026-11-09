<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Admission workflow policy, stored in the settings table. Every default
 * keeps the workflow that existed before these options were introduced:
 * the entrance exam is off until the school turns it on.
 */
class AdmissionSettings
{
    public const EXAM_MODES = [
        'disabled' => 'Disabled — no entrance exam step',
        'optional' => 'Optional — applicants may book; approval does not depend on it',
        'required' => 'Required — approval needs a passing exam result',
    ];

    public static function examMode(): string
    {
        $mode = (string) Setting::get('admission_exam_mode', 'disabled');

        return array_key_exists($mode, self::EXAM_MODES) ? $mode : 'disabled';
    }

    public static function examEnabled(): bool
    {
        return self::examMode() !== 'disabled';
    }

    public static function examRequired(): bool
    {
        return self::examMode() === 'required';
    }

    public static function bookingRequiresApproval(): bool
    {
        return Setting::get('admission_exam_booking_requires_approval', '0') === '1';
    }

    /** Applicants must have every required document approved before booking. */
    public static function documentsRequiredBeforeExam(): bool
    {
        return Setting::get('admission_exam_requires_documents', '1') === '1';
    }

    public static function applicantChangesAllowed(): bool
    {
        return Setting::get('admission_exam_allow_applicant_changes', '1') === '1';
    }

    public static function changeCutoffHours(): int
    {
        return max(0, min(168, (int) Setting::get('admission_exam_change_cutoff_hours', 24)));
    }

    public static function all(): array
    {
        return [
            'exam_mode' => self::examMode(),
            'booking_requires_approval' => self::bookingRequiresApproval(),
            'documents_required_before_exam' => self::documentsRequiredBeforeExam(),
            'applicant_changes_allowed' => self::applicantChangesAllowed(),
            'change_cutoff_hours' => self::changeCutoffHours(),
        ];
    }
}
