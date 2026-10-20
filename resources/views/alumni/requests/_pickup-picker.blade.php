{{--
    Pickup day + time picker for a ready request (My Requests).
    Expects: $slots (the open pickup slots the controller already supplies), $uid (unique per request).
    Posts appointment_slot_id only; the server still validates the slot and its capacity.
--}}
@php
    $groups  = $slots->groupBy(fn ($s) => \Carbon\Carbon::parse($s->date)->format('Y-m-d'));
    $left    = $slots->mapWithKeys(fn ($s) => [$s->id => max(0, (int) $s->availableSlots())]);
    $openDay = $groups->map(fn ($g) => $g->filter(fn ($s) => $left[$s->id] > 0)->count());
    $today   = now()->format('Y-m-d');
@endphp

<div class="dp dp--compact" data-pickup-picker>
    <div class="dp-dates">
        <span class="dp-label" id="pp-day-{{ $uid }}">Day</span>
        <div class="dp-rail" role="radiogroup" aria-labelledby="pp-day-{{ $uid }}" data-date-rail>
            @foreach ($groups as $date => $daySlots)
                @php $d = \Carbon\Carbon::parse($date); $n = $openDay[$date]; @endphp
                <label class="dp-day {{ $n === 0 ? 'is-full' : '' }}">
                    <input type="radio" name="pickup_date" value="{{ $date }}" class="dp-input dr-visually-hidden"
                           aria-label="{{ $d->format('l, F j') }}, {{ $n === 0 ? 'no open times' : $n.' open '.($n === 1 ? 'time' : 'times') }}"
                           {{ $n === 0 ? 'disabled' : '' }}>
                    <span class="dp-day-wd">{{ $date === $today ? 'Today' : $d->format('D') }}</span>
                    <span class="dp-day-num">{{ $d->format('j') }}</span>
                    <span class="dp-day-mo">{{ $d->format('M') }}</span>
                    <span class="dp-day-count">{{ $n === 0 ? 'Full' : $n.' open' }}</span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="dp-times" aria-live="polite">
        <p class="dp-hint" data-pp-hint>Choose a day to see open times.</p>
        @foreach ($groups as $day => $daySlots)
            <div class="dp-panel" data-pp-panel="{{ $day }}" hidden>
                <div class="dp-grid">
                    @foreach ($daySlots as $slot)
                        @php
                            $l     = $left[$slot->id];
                            $start = \Carbon\Carbon::parse($slot->start_time)->format('g:i A');
                            $end   = \Carbon\Carbon::parse($slot->end_time)->format('g:i A');
                        @endphp
                        <label class="dp-time {{ $l === 0 ? 'is-full' : '' }}">
                            <input type="radio" name="appointment_slot_id" value="{{ $slot->id }}" class="dp-input dr-visually-hidden"
                                   data-date="{{ \Carbon\Carbon::parse($slot->date)->format('D, M j, Y') }}"
                                   data-time="{{ $start }} to {{ $end }}"
                                   {{ $l === 0 ? 'disabled' : '' }}>
                            <span class="dp-time-start">{{ $start }}</span>
                            <span class="dp-time-end">to {{ $end }}</span>
                            <span class="dp-time-flag {{ $l === 0 ? 'is-full' : ($l <= 2 ? 'is-low' : '') }}">
                                {{ $l === 0 ? 'Full' : ($l === 1 ? '1 spot left' : $l.' spots left') }}
                            </span>
                            <span class="dp-time-check" aria-hidden="true"></span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <p class="dp-picked" data-pp-picked aria-live="polite">No time chosen.</p>
</div>
