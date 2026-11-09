<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AdmissionRequirement extends Model
{
    /** File types the school may allow. Anything else is rejected at upload. */
    public const SUPPORTED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    public const MAX_SIZE_LIMIT_KB = 10240;

    public const CONDITIONS = [
        'married' => 'Only for married applicants',
    ];

    public const APPLICANT_TYPES = [
        'new_student' => 'New Student / First Year',
        'transferee' => 'Transferee',
        'shiftee' => 'Shiftee',
        'returnee' => 'Returnee / Readmitted',
        'cross_enrollee' => 'Cross-Enrollee',
    ];

    protected $fillable = [
        'code',
        'name',
        'description',
        'applicant_types',
        'condition',
        'is_required',
        'is_active',
        'allowed_extensions',
        'max_size_kb',
        'sort_order',
    ];

    protected $casts = [
        'applicant_types' => 'array',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'max_size_kb' => 'integer',
        'sort_order' => 'integer',
    ];

    public function documents()
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function appliesTo(?string $applicantType, ?string $maritalStatus = null): bool
    {
        $types = $this->applicant_types;
        if (!empty($types) && !in_array($applicantType, $types, true)) {
            return false;
        }

        return match ($this->condition) {
            'married' => $maritalStatus === 'married',
            default => true,
        };
    }

    public function extensions(): array
    {
        return array_values(array_intersect(
            array_map('trim', explode(',', strtolower((string) $this->allowed_extensions))),
            self::SUPPORTED_EXTENSIONS
        ));
    }

    /** Laravel validation rules for one upload of this requirement. */
    public function uploadRules(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'mimes:' . implode(',', $this->extensions() ?: self::SUPPORTED_EXTENSIONS),
            'max:' . min(max(1, (int) $this->max_size_kb), self::MAX_SIZE_LIMIT_KB),
        ];
    }

    /** Value for an <input type="file" accept="..."> attribute. */
    public function acceptList(): string
    {
        return implode(',', array_map(fn ($ext) => '.' . $ext, $this->extensions()));
    }

    public function formatSummary(): string
    {
        $size = $this->max_size_kb >= 1024
            ? rtrim(rtrim(number_format($this->max_size_kb / 1024, 1), '0'), '.') . ' MB'
            : $this->max_size_kb . ' KB';

        return strtoupper(implode(', ', $this->extensions())) . ' · up to ' . $size;
    }

    public function applicantTypesLabel(): string
    {
        if (empty($this->applicant_types)) {
            return 'All applicant types';
        }

        return collect($this->applicant_types)
            ->map(fn ($type) => self::APPLICANT_TYPES[$type] ?? $type)
            ->implode(', ');
    }
}
