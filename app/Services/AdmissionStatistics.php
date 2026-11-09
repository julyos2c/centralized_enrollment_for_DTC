<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\EntranceExamBooking;
use App\Models\EntranceExamSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Admission figures computed from live records. Every number shown on the
 * admissions dashboard and in its exports comes from here.
 */
class AdmissionStatistics
{
    public const FILTER_KEYS = ['school_year', 'semester', 'program_id', 'status', 'type', 'exam_date'];

    public function applications(array $filters): Builder
    {
        return Application::query()
            ->when($filters['school_year'] ?? null, fn ($q, $v) => $q->where('school_year', $v))
            ->when($filters['semester'] ?? null, fn ($q, $v) => $q->where('semester', $v))
            ->when($filters['program_id'] ?? null, fn ($q, $v) => $q->where('program_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('academic_status', $v))
            ->when($filters['exam_date'] ?? null, fn ($q, $v) => $q->whereHas('examBookings', fn ($b) => $b
                ->whereIn('status', EntranceExamBooking::SEATED_STATUSES)
                ->whereHas('session', fn ($s) => $s->whereDate('exam_date', $v))));
    }

    public function summary(array $filters): array
    {
        $ids = $this->applications($filters)->select('applications.id');

        $statusCounts = $this->applications($filters)
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $total = (int) $statusCounts->sum();
        $approved = (int) ($statusCounts['approved'] ?? 0);
        $rejected = (int) ($statusCounts['rejected'] ?? 0);

        $documentCounts = ApplicationDocument::whereNull('superseded_at')
            ->whereIn('application_id', $ids)
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $bookingCounts = EntranceExamBooking::whereIn('application_id', $ids)
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $resultCounts = EntranceExamBooking::whereIn('application_id', $ids)->where('status', 'attended')
            ->whereNotNull('result')->selectRaw('result, COUNT(*) as total')->groupBy('result')->pluck('total', 'result');
        $scheduled = EntranceExamBooking::whereIn('application_id', $ids)
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereHas('session', fn ($q) => $q->upcoming())->count();

        $attended = (int) ($bookingCounts['attended'] ?? 0);
        $absent = (int) ($bookingCounts['absent'] ?? 0);

        $enrolled = $this->applications($filters)->where('status', 'approved')
            ->whereNotNull('user_id')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))->from('enrollments')
                    ->whereColumn('enrollments.user_id', 'applications.user_id')
                    ->whereColumn('enrollments.program_id', 'applications.program_id')
                    ->where('enrollments.status', 'approved')
                    ->where('enrollments.is_paid', true);
            })->count();

        return [
            'total' => $total,
            'status' => $statusCounts->all(),
            'pending' => (int) (($statusCounts['submitted'] ?? 0) + ($statusCounts['under_review'] ?? 0)),
            'revision' => (int) ($statusCounts['revision_required'] ?? 0),
            'approved' => $approved,
            'rejected' => $rejected,
            'cancelled' => (int) ($statusCounts['cancelled'] ?? 0),
            'documents_pending' => (int) ($documentCounts['submitted'] ?? 0),
            'documents_approved' => (int) ($documentCounts['approved'] ?? 0),
            'documents_rejected' => (int) ($documentCounts['rejected'] ?? 0),
            'scheduled_examinees' => $scheduled,
            'awaiting_booking_approval' => (int) ($bookingCounts['pending'] ?? 0),
            'attended' => $attended,
            'absent' => $absent,
            'attendance_rate' => $attended + $absent > 0 ? round($attended / ($attended + $absent) * 100) : null,
            'passed' => (int) ($resultCounts['passed'] ?? 0),
            'failed' => (int) ($resultCounts['failed'] ?? 0),
            'acceptance_rate' => $approved + $rejected > 0 ? round($approved / ($approved + $rejected) * 100) : null,
            'enrolled' => $enrolled,
            'conversion_rate' => $approved > 0 ? round($enrolled / $approved * 100) : null,
        ];
    }

    public function byProgram(array $filters): array
    {
        return $this->applications($filters)
            ->join('programs', 'programs.id', '=', 'applications.program_id')
            ->selectRaw("programs.code as label, COUNT(*) as total, SUM(applications.status = 'approved') as admitted")
            ->groupBy('programs.code')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'total' => (int) $row->total, 'admitted' => (int) $row->admitted])
            ->all();
    }

    public function byType(array $filters): array
    {
        return $this->applications($filters)
            ->selectRaw('academic_status as label, COUNT(*) as total')
            ->groupBy('academic_status')->orderByDesc('total')
            ->pluck('total', 'label')->map(fn ($v) => (int) $v)->all();
    }

    /** Submissions per month for the last six months, oldest first. */
    public function monthlyTrend(array $filters): array
    {
        $start = now()->startOfMonth()->subMonths(5);
        $counts = $this->applications($filters)
            ->where('created_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total")
            ->groupBy('month')->pluck('total', 'month');

        $trend = [];
        for ($month = $start->copy(); $month <= now(); $month->addMonth()) {
            $trend[$month->format('M Y')] = (int) ($counts[$month->format('Y-m')] ?? 0);
        }

        return $trend;
    }

    public function upcomingSessions(int $limit = 5)
    {
        return EntranceExamSession::upcoming()->where('status', '!=', 'cancelled')
            ->withCount(['seatedBookings', 'bookings as pending_count' => fn ($q) => $q->where('status', 'pending')])
            ->orderBy('exam_date')->orderBy('start_time')
            ->limit($limit)->get();
    }

    /** Applications with uploads waiting for a document decision. */
    public function documentQueue(array $filters, int $limit = 6)
    {
        return $this->applications($filters)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->whereHas('currentDocuments', fn ($q) => $q->where('status', 'submitted'))
            ->withCount(['currentDocuments as awaiting_count' => fn ($q) => $q->where('status', 'submitted')])
            ->with('program:id,code')
            ->orderBy('updated_at')
            ->limit($limit)->get();
    }

    public function termOptions(): array
    {
        return [
            'school_years' => Application::whereNotNull('school_year')->distinct()->orderByDesc('school_year')->pluck('school_year')->all(),
            'semesters' => ['1st', '2nd', 'Summer'],
        ];
    }
}
