<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ShsCurriculum extends Model
{
    protected $table = 'shs_curricula';

    protected $fillable = [
        'code',
        'name',
        'description',
        'first_graduation_year',
        'last_graduation_year',
        'choice_label',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'first_graduation_year' => 'integer',
        'last_graduation_year' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function classifications()
    {
        return $this->hasMany(ShsClassification::class)->orderBy('sort_order')->orderBy('name');
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Whether a student finishing in the school year starting $year could follow this curriculum. */
    public function coversGraduationYear(?int $year): bool
    {
        if ($year === null) {
            return true;
        }

        return ($this->first_graduation_year === null || $year >= $this->first_graduation_year)
            && ($this->last_graduation_year === null || $year <= $this->last_graduation_year);
    }

    public function yearsLabel(): string
    {
        $from = $this->first_graduation_year ? 'SY ' . $this->first_graduation_year . '–' . ($this->first_graduation_year + 1) : 'any year';
        $to = $this->last_graduation_year ? 'SY ' . $this->last_graduation_year . '–' . ($this->last_graduation_year + 1) : 'present';

        return "Graduates from {$from} to {$to}";
    }
}
