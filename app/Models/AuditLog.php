<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'description',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /** Record an action by the current user; never interrupts the caller. */
    public static function record(string $action, string $description, array $metadata = []): void
    {
        try {
            static::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'description' => $description,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'metadata' => $metadata ?: null,
            ]);
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::warning('Audit log entry could not be saved.', ['action' => $action]);
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}