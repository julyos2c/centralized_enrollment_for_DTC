<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    public const CATEGORIES = [
        'payment' => ['label' => 'Payments', 'icon' => 'wallet', 'terms' => ['payment', 'gcash', 'receipt']],
        'appointment' => ['label' => 'Appointments', 'icon' => 'calendar-days', 'terms' => ['appointment', 'pickup']],
        'application' => ['label' => 'Applications', 'icon' => 'file-check', 'terms' => ['application']],
        'enrollment' => ['label' => 'Enrollment', 'icon' => 'graduation-cap', 'terms' => ['enrollment']],
        'document' => ['label' => 'Documents', 'icon' => 'files', 'terms' => ['document']],
        'account' => ['label' => 'Account', 'icon' => 'shield-check', 'terms' => ['account', 'password', 'invitation']],
        'general' => ['label' => 'General', 'icon' => 'bell', 'terms' => []],
    ];

    public function getCategoryAttribute(): string
    {
        $title = strtolower($this->title);
        foreach (self::CATEGORIES as $key => $category) {
            foreach ($category['terms'] as $term) {
                if (str_contains($title, $term)) return $key;
            }
        }
        return 'general';
    }

    public function scopeInCategory($query, string $category)
    {
        // Constants only; this CASE uses the same precedence as the display accessor.
        $expression = 'CASE';
        foreach (self::CATEGORIES as $key => $definition) {
            if (!$definition['terms']) continue;
            $conditions = array_map(fn ($term) => "LOWER(title) LIKE '%{$term}%'", $definition['terms']);
            $expression .= ' WHEN ('.implode(' OR ', $conditions).") THEN '{$key}'";
        }
        return $query->whereRaw($expression." ELSE 'general' END = ?", [$category]);
    }

    protected $fillable = ['user_id', 'title', 'message', 'type', 'link', 'event_key', 'read_at'];

    protected $casts = [
        'read_at' => 'datetime',
        'email_sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function markAsRead()
    {
        $this->update(['read_at' => now()]);
    }

    public function isRead(): bool
    {
        return ! is_null($this->read_at);
    }
}
