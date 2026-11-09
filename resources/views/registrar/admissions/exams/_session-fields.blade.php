@php
    $v = fn ($key, $default = null) => old($key, $default);
    $time = fn ($value) => $value ? substr($value, 0, 5) : null;
@endphp
<div class="form-row">
    <div class="form-group col-md-6">
        <label for="s-title">Title</label>
        <input id="s-title" name="title" class="form-control" maxlength="150" required value="{{ $v('title', $session?->title ?? 'College Entrance Examination') }}">
    </div>
    <div class="form-group col-md-6">
        <label for="s-venue">Venue</label>
        <input id="s-venue" name="venue" class="form-control" maxlength="200" required value="{{ $v('venue', $session?->venue) }}" placeholder="e.g. Main Building, Room 201">
    </div>
</div>
<div class="form-row">
    <div class="form-group col-md-3">
        <label for="s-date">Exam date</label>
        <input id="s-date" type="date" name="exam_date" class="form-control" required min="{{ $session ? min($session->exam_date->toDateString(), today()->toDateString()) : today()->toDateString() }}" value="{{ $v('exam_date', $session?->exam_date?->toDateString()) }}">
    </div>
    <div class="form-group col-md-2">
        <label for="s-start">Starts</label>
        <input id="s-start" type="time" name="start_time" class="form-control" required value="{{ $v('start_time', $time($session?->start_time) ?? '08:00') }}">
    </div>
    <div class="form-group col-md-2">
        <label for="s-end">Ends</label>
        <input id="s-end" type="time" name="end_time" class="form-control" required value="{{ $v('end_time', $time($session?->end_time) ?? '11:00') }}">
    </div>
    <div class="form-group col-md-2">
        <label for="s-capacity">Max examinees</label>
        <input id="s-capacity" type="number" name="capacity" class="form-control" min="1" max="2000" required value="{{ $v('capacity', $session?->capacity ?? 40) }}">
    </div>
    <div class="form-group col-md-2">
        <label for="s-sy">School year</label>
        <input id="s-sy" name="school_year" class="form-control" maxlength="20" value="{{ $v('school_year', $session?->school_year ?? ($defaultTerm[0] ?? null)) }}" placeholder="2026-2027">
    </div>
    <div class="form-group col-md-1">
        <label for="s-sem">Sem</label>
        <select id="s-sem" name="semester" class="form-control">
            <option value=""></option>
            @foreach(['1st', '2nd', 'Summer'] as $semester)
                <option value="{{ $semester }}" @selected($v('semester', $session?->semester ?? ($defaultTerm[1] ?? null)) === $semester)>{{ $semester }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group mb-0">
    <label for="s-notes">Instructions for examinees <span class="text-muted font-weight-normal">(optional, shown to applicants)</span></label>
    <textarea id="s-notes" name="notes" class="form-control" rows="2" maxlength="1000" placeholder="e.g. Bring two pencils, an eraser, and a valid ID.">{{ $v('notes', $session?->notes) }}</textarea>
</div>
