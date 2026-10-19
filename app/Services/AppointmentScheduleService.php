<?php

namespace App\Services;

use App\Models\AppointmentSlot;
use App\Models\OfficeClosure;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AppointmentScheduleService
{
    public function schedule(): ?array
    {
        $raw = Setting::get('appointment_schedule');
        if (! $raw) return null;
        $schedule = json_decode($raw, true);
        if (! is_array($schedule) || empty($schedule['weekdays']) || empty($schedule['opens_at']) || empty($schedule['closes_at']) || empty($schedule['duration']) || empty($schedule['capacity']) || empty($schedule['window'])) return null;
        return $schedule;
    }

    public function isClosed(string $date, string $start, string $end): bool
    {
        return OfficeClosure::where('is_active', true)->where('is_confirmed', true)
            ->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)
            ->where(function ($query) use ($start, $end) {
                $query->whereNull('start_time')->orWhere(function ($q) use ($start, $end) {
                    $q->where('start_time', '<', $end)->where('end_time', '>', $start);
                });
            })->exists();
    }

    public function generate(?Carbon $from = null): int
    {
        $schedule = $this->schedule();
        if (! $schedule) return 0;
        $from = ($from ?: now())->copy()->startOfDay();
        $through = $from->copy()->addDays(max(0, (int) $schedule['window'] - 1));
        $created = 0;
        $fromDate = $from->toDateString();
        $throughDate = $through->toDateString();
        $lock = Cache::lock('appointment-slot-generation', 300);
        if (! $lock->get()) return 0;
        try {
        DB::transaction(function () use ($schedule, $from, $through, $fromDate, $throughDate, &$created) {
            // Load existing slots and confirmed closures once. A long booking window
            // should not issue a pair of database queries for every candidate slot.
            $existingByDate = AppointmentSlot::whereBetween('date', [$fromDate, $throughDate])
                ->get(['date', 'start_time', 'end_time'])
                ->groupBy(fn ($slot) => Carbon::parse($slot->date)->toDateString());
            $closures = OfficeClosure::where('is_active', true)->where('is_confirmed', true)
                ->whereDate('start_date', '<=', $throughDate)
                ->whereDate('end_date', '>=', $fromDate)
                ->get(['start_date', 'end_date', 'start_time', 'end_time']);

            for ($day = $from->copy(); $day->lte($through); $day->addDay()) {
                if (! in_array(strtolower($day->format('l')), $schedule['weekdays'], true)) continue;
                $date = $day->toDateString();
                $cursor = Carbon::parse($day->toDateString().' '.$schedule['opens_at'], config('app.timezone'));
                $close = Carbon::parse($day->toDateString().' '.$schedule['closes_at'], config('app.timezone'));
                $breakStart = ! empty($schedule['break_start']) ? Carbon::parse($day->toDateString().' '.$schedule['break_start'], config('app.timezone')) : null;
                $breakEnd = ! empty($schedule['break_end']) ? Carbon::parse($day->toDateString().' '.$schedule['break_end'], config('app.timezone')) : null;
                while ($cursor->copy()->addMinutes((int) $schedule['duration'])->lte($close)) {
                    $end = $cursor->copy()->addMinutes((int) $schedule['duration']);
                    $startTime = $cursor->format('H:i:s');
                    $endTime = $end->format('H:i:s');
                    $duringBreak = $breakStart && $cursor->lt($breakEnd) && $end->gt($breakStart);
                    $duringClosure = $closures->contains(function ($closure) use ($date, $startTime, $endTime) {
                        $closureStart = Carbon::parse($closure->start_date)->toDateString();
                        $closureEnd = Carbon::parse($closure->end_date)->toDateString();
                        return $closureStart <= $date && $closureEnd >= $date
                            && (! $closure->start_time || ($closure->start_time < $endTime && $closure->end_time > $startTime));
                    });
                    if ($cursor->gte(now()) && ! $duringBreak && ! $duringClosure) {
                        $daySlots = $existingByDate->get($date, collect());
                        $overlaps = $daySlots->contains(fn ($slot) => $slot->start_time < $endTime && $slot->end_time > $startTime);
                        if (! $overlaps) {
                            AppointmentSlot::create(['date' => $date, 'start_time' => $startTime, 'end_time' => $endTime, 'max_bookings' => (int) $schedule['capacity'], 'source' => 'automatic', 'status' => 'open']);
                            $daySlots->push((object) ['start_time' => $startTime, 'end_time' => $endTime]);
                            $existingByDate->put($date, $daySlots);
                            $created++;
                        }
                    }
                    $cursor = $end;
                    if ($breakStart && $cursor->lt($breakEnd) && $cursor->copy()->addMinutes((int) $schedule['duration'])->gt($breakStart)) $cursor = $breakEnd->copy();
                }
            }
        });
        return $created;
        } finally {
            $lock->release();
        }
    }
}
