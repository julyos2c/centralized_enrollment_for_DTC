<?php

namespace App\Support;

class ApplicationTextNormalizer
{
    /** Human-entered names, places, addresses, and descriptive labels. */
    private const TITLE_CASE_FIELDS = [
        'first_name', 'middle_name', 'last_name', 'father_name', 'mother_name', 'spouse_name', 'guardian_name',
        'birth_place', 'religion', 'nationality', 'current_address', 'city', 'province', 'region',
        'barangay', 'international_location', 'country', 'father_occupation', 'mother_occupation',
        'parent_address', 'occupation', 'disability', 'most_recent_school', 'school_location',
        'previous_program', 'home_institution', 'home_program', 'last_year_level',
    ];

    /** Free text whose capitalization has a defined meaning or is an acronym/code. */
    private const WHITESPACE_ONLY_FIELDS = [
        'email', 'phone', 'parent_contact', 'guardian_contact', 'lrn', 'pwd_id', 'postal_code',
        'school_year_completed', 'shs_strand',
    ];

    public static function normalizeFields(array $values): array
    {
        foreach (['honorific', 'suffix'] as $field) {
            if (array_key_exists($field, $values) && (is_string($values[$field]) || $values[$field] === null)) {
                $values[$field] = NameNormalizer::normalizeAffix($values[$field], $field);
            }
        }
        foreach (self::TITLE_CASE_FIELDS as $field) {
            if (array_key_exists($field, $values) && (is_string($values[$field]) || $values[$field] === null)) {
                $values[$field] = NameNormalizer::normalize($values[$field]);
            }
        }

        foreach (self::WHITESPACE_ONLY_FIELDS as $field) {
            if (array_key_exists($field, $values) && is_string($values[$field])) {
                $values[$field] = trim($values[$field]);
            }
        }

        return $values;
    }

    public static function isTitleCaseField(string $field): bool
    {
        return in_array($field, self::TITLE_CASE_FIELDS, true);
    }

    public static function titleCaseFields(): array
    {
        return self::TITLE_CASE_FIELDS;
    }
}
