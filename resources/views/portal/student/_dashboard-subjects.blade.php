@php $subjectUnits = $subjects->sum(fn ($subject) => $subject->units ?? 3); @endphp
@php $subjectPreview = $subjects->take(3); @endphp
<section class="card mb-3 student-cor" aria-labelledby="student-cor-heading">
    <div class="student-cor-header">
        <div>
            <h2 id="student-cor-heading">Subjects</h2>
            <p>{{ $subjects->count() }} {{ $subjects->count() === 1 ? 'subject' : 'subjects' }} · {{ rtrim(rtrim(number_format($subjectUnits, 1), '0'), '.') }} units</p>
        </div>
    </div>
    @if($subjects->isNotEmpty())
        <div class="student-cor-status">@include('portal.student._subject-status')</div>
        <ul class="student-cor-list">
            @foreach($subjectPreview as $subject)
                <li>
                    <div class="student-cor-subject">
                        <span class="student-cor-code">{{ $subject->subject_code }}</span>
                        <h3>{{ $subject->subject_name }}</h3>
                    </div>
                    <span class="student-cor-units"><strong>{{ rtrim(rtrim(number_format($subject->units ?? 3, 1), '0'), '.') }}</strong> units</span>
                </li>
            @endforeach
        </ul>
        @if($latestEnrollment && $latestEnrollment->status === 'approved' && $latestEnrollment->is_paid)
            <footer class="student-cor-footer">
                @if($subjects->count() > $subjectPreview->count())
                    <span>Showing {{ $subjectPreview->count() }} of {{ $subjects->count() }} subjects</span>
                @endif
                <a href="{{ route('portal.cor.show') }}" class="student-cor-link">View all subjects <i data-lucide="arrow-right" aria-hidden="true"></i></a>
            </footer>
        @endif
    @else
        <div class="student-cor-empty">
            <p>No subjects enrolled yet.</p>
            @if(!$latestEnrollment)
                <a href="{{ route('portal.enrollment.create') }}" class="student-cor-link">Enroll Now</a>
            @endif
        </div>
    @endif
</section>
