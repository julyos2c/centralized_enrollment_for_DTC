<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ShsClassification extends Model
{
    protected $fillable = [
        'shs_curriculum_id',
        'track',
        'code',
        'name',
        'offering_key',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function curriculum()
    {
        return $this->belongsTo(ShsCurriculum::class, 'shs_curriculum_id');
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Text stored in applications.shs_strand, e.g. "STEM (Academic Track)". */
    public function label(): string
    {
        return $this->name === $this->track ? $this->name : "{$this->name} ({$this->track})";
    }
}
