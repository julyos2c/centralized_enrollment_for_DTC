<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Append-only: a recorded agreement, acknowledgment, or consent is never changed. */
class PolicyAcknowledgment extends Model
{
    public const UPDATED_AT = null;

    public const KINDS = [
        'terms' => 'agreement',
        'privacy' => 'acknowledgment',
        'sensitive_consent' => 'consent',
    ];

    protected $fillable = [
        'application_id',
        'user_id',
        'document',
        'kind',
        'version',
        'acknowledged_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Policy acknowledgments cannot be changed; record a new one.'));
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function documentTitle(): string
    {
        return config("legal.{$this->document}.title", $this->document);
    }
}
