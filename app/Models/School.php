<?php

namespace App\Models;

use App\Services\PsgcLocationCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    public const SECTORS = [
        'public' => 'Public',
        'private' => 'Private',
        'suc' => 'State university or college',
        'luc' => 'Local university or college',
    ];

    protected $fillable = [
        'deped_school_id',
        'name',
        'sector',
        'region_code',
        'province_code',
        'city_code',
        'barangay',
        'street_address',
        'source_location',
        'offerings',
        'source',
        'is_active',
    ];

    protected $casts = [
        'offerings' => 'array',
        'is_active' => 'boolean',
    ];

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Name or DepEd School ID search, best matches (prefix) first. */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim(preg_replace('/\s+/', ' ', $term));
        if (preg_match('/^\d{6}$/', $term)) {
            return $query->where('deped_school_id', $term);
        }
        $like = '%' . addcslashes($term, '%_\\') . '%';
        foreach (array_slice(explode(' ', $term), 0, 6) as $word) {
            $query->where('name', 'like', '%' . addcslashes($word, '%_\\') . '%');
        }

        return $query->orderByRaw('CASE WHEN name LIKE ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END', [
            addcslashes($term, '%_\\') . '%',
            $like,
        ])->orderBy('name');
    }

    public function sectorLabel(): ?string
    {
        return self::SECTORS[$this->sector] ?? null;
    }

    /** "Barangay, City, Province" from PSGC codes, falling back to the source text. */
    public function locationLabel(): string
    {
        $catalog = app(PsgcLocationCatalog::class);
        $city = $this->city_code ? $catalog->cityByCode($this->city_code) : null;
        $province = $this->province_code
            ? collect($catalog->areas($this->region_code ?? ''))->firstWhere('code', $this->province_code)
            : null;
        $parts = array_filter([
            $this->street_address,
            $this->barangay,
            $city['name'] ?? null,
            $province['name'] ?? null,
        ]);

        return $parts ? implode(', ', $parts) : (string) $this->source_location;
    }

    public function toLookupArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'deped_school_id' => $this->deped_school_id,
            'sector' => $this->sector,
            'sector_label' => $this->sectorLabel(),
            'location' => $this->locationLabel(),
            'region_code' => $this->region_code,
            'province_code' => $this->province_code,
            'city_code' => $this->city_code,
            'barangay' => $this->barangay,
            'street_address' => $this->street_address,
            'offerings' => $this->offerings ?? [],
        ];
    }
}
