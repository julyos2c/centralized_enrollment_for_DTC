<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * { font-family: Arial, sans-serif; font-size: 12px; }
        body { margin: 30px; color: #1E293B; }
        .header { text-align: center; margin-bottom: 10px; border-bottom: 2px solid #0F4CDB; padding-bottom: 12px; }
        .header h1 { font-size: 20px; color: #0F4CDB; margin: 0 0 2px; }
        .header p  { font-size: 11px; color: #64748B; margin: 0; }
        .header .office { font-weight: bold; color: #1E293B; margin-top: 4px; }
        .title-block { text-align: center; margin: 16px 0; }
        .title-block h2 { font-size: 15px; margin: 0; text-transform: uppercase; }
        .title-block p { font-size: 11px; color: #64748B; margin: 2px 0 0; }
        .meta { display: flex; justify-content: space-between; margin: 16px 0 10px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #0F4CDB; color: #fff; padding: 8px 10px;
            text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px;
        }
        tbody tr:nth-child(even) { background: #F8FAFC; }
        tbody td { padding: 8px 10px; border-bottom: 1px solid #E2E8F0; font-size: 11px; }
        tfoot td { padding: 8px 10px; font-size: 12px; font-weight: bold; border-top: 2px solid #0F4CDB; }
        .badge {
            display: inline-block; padding: 2px 8px; border-radius: 10px;
            font-size: 10px; font-weight: bold; background: #EEF2FF; color: #0F4CDB;
        }
        .signature { text-align: center; margin-top: 60px; }
        .signature .name { font-weight: bold; text-transform: uppercase; border-top: 1px solid #1E293B; display: inline-block; padding-top: 4px; min-width: 260px; }
        .signature .title { font-size: 10px; color: #64748B; margin-top: 2px; }
        .footer { margin-top: 24px; text-align: center; font-size: 9px; color: #94A3B8; }
    </style>
</head>
<body>

<div class="header">
    <h1>Danao Technological College</h1>
    <p>Poblacion, Danao, Bohol, Philippines</p>
    <p class="office">Office of the College Registrar</p>
</div>

<div class="title-block">
    <h2>{{ $enrollment->program->name }}</h2>
    <p>
        {{ $enrollment->semester == 1 ? 'First' : ($enrollment->semester == 2 ? 'Second' : 'Summer') }} Semester
        A.Y. {{ $enrollment->school_year ?? \App\Models\Setting::get('current_school_year', now()->year . '-' . (now()->year + 1)) }}
    </p>
</div>

<div class="meta">
    <div><strong>Name:</strong> {{ $enrollment->user->name }}</div>
    <div><strong>Program:</strong> {{ $enrollment->program->code }} {{ ['','I','II','III','IV'][$enrollment->year_level] ?? '' }}</div>
</div>

<table>
    <thead>
        <tr>
            <th>Time / Day</th>
            <th>Room</th>
            <th>Course Code</th>
            <th>Course Description</th>
            <th>Units</th>
            <th>Instructor</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($subjects as $subject)
            @php $sched = $subject->schedules->first(); @endphp
            <tr>
                <td>
                    @if($sched)
                        {{ \Illuminate\Support\Carbon::parse($sched->time_start)->format('g:i A') }}-{{ \Illuminate\Support\Carbon::parse($sched->time_end)->format('g:i A') }} {{ $sched->day_pattern }}
                    @else
                        TBA
                    @endif
                </td>
                <td>{{ $sched->room ?? 'TBA' }}</td>
                <td><span class="badge">{{ $subject->subject_code }}</span></td>
                <td>{{ $subject->subject_name }}</td>
                <td>{{ rtrim(rtrim(number_format($subject->units, 1), '0'), '.') }}</td>
                <td>{{ $sched->instructor_name ?? 'TBA' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" style="text-align:center; padding:16px;">No subjects found for this enrollment.</td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4" style="text-align:right;">TOTAL UNITS</td>
            <td>{{ rtrim(rtrim(number_format($totalUnits, 1), '0'), '.') }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>

<div class="signature">
    <div class="name">{{ \App\Models\Setting::get('registrar_name', 'College Registrar') }}</div>
    <div class="title">College Registrar</div>
</div>

<div class="footer">
    Generated {{ now()->format('F d, Y h:i A') }} — This is a system-generated document.
</div>

</body>
</html>