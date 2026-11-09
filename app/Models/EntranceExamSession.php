<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EntranceExamSession extends Model
{
    public const STATUSES = ['open', 'closed', 'cancelled'];

    protected $fillable = [
        'title',
        'exam_date',
        'start_time',
        'end_time',
        'venue',
        'capacity',
        'status',
        'school_year',
        'semester',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'capacity' => 'integer',
    ];

    public function bookings()
    {
        return $this->hasMany(EntranceExamBooking::class);
    }

    /** Bookings that occupy a seat (including historical attendance). */
    public function seatedBookings()
    {
        return $this->hasMany(EntranceExamBooking::class)->whereIn('status', EntranceExamBooking::SEATED_STATUSES);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function startsAt(): Carbon
    {
        return Carbon::parse($this->exam_date->toDateString() . ' ' . $this->start_time, config('app.timezone'));
    }

    public function endsAt(): Carbon
    {
        return Carbon::parse($this->exam_date->toDateString() . ' ' . $this->end_time, config('app.timezone'));
    }

    public function hasStarted(): bool
    {
        return $this->startsAt()->isPast();
    }

    /** Seats remaining. Uses a preloaded seated_bookings_count when available. */
    public function remainingSeats(): int
    {
        $taken = $this->seated_bookings_count ?? $this->seatedBookings()->count();

        return max(0, $this->capacity - $taken);
    }

    public function isBookable(): bool
    {
        return $this->status === 'open' && !$this->hasStarted() && $this->remainingSeats() > 0;
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('exam_date', '>=', today());
    }

    public function scheduleLabel(): string
    {
        return $this->exam_date->format('D, M j, Y') . ' · '
            . Carbon::parse($this->start_time)->format('g:i A') . '–' . Carbon::parse($this->end_time)->format('g:i A');
    }
}
