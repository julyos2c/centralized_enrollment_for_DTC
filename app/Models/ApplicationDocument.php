<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ApplicationDocument extends Model
{
    public const PREVIEWABLE_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    protected $fillable = [
        'application_id',
        'admission_requirement_id',
        'document_type',
        'file_path',
        'original_name',
        'mime_type',
        'size_bytes',
        'status',
        'review_remarks',
        'reviewed_by',
        'reviewed_at',
        'superseded_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];

    /**
     * The application this document belongs to.
     */
    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function requirement()
    {
        return $this->belongsTo(AdmissionRequirement::class, 'admission_requirement_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Uploads that have not been replaced by a newer file. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('superseded_at');
    }

    public function getStatusAttribute($value): string
    {
        return $value ?: 'submitted';
    }

    public function isPreviewable(): bool
    {
        return in_array($this->mime_type, self::PREVIEWABLE_MIME_TYPES, true);
    }

    public function getSizeLabelAttribute(): ?string
    {
        if (!$this->size_bytes) {
            return null;
        }

        $sizeKb = $this->size_bytes / 1024;

        return $sizeKb >= 1024 ? number_format($sizeKb / 1024, 2) . ' MB' : number_format($sizeKb, 1) . ' KB';
    }
}
