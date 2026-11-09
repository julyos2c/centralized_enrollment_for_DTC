<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\AdmissionRequirement;
use App\Models\AuditLog;
use App\Models\Program;
use App\Services\AdmissionStatistics;
use App\Support\AdmissionSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdmissionDashboardController extends Controller
{
    private const STATUSES = ['submitted', 'under_review', 'revision_required', 'approved', 'rejected', 'cancelled'];

    public function __construct(private AdmissionStatistics $statistics)
    {
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request);

        return view('registrar.admissions.dashboard', [
            'filters' => $filters,
            'stats' => $this->statistics->summary($filters),
            'byProgram' => $this->statistics->byProgram($filters),
            'byType' => $this->statistics->byType($filters),
            'trend' => $this->statistics->monthlyTrend($filters),
            'upcomingSessions' => $this->statistics->upcomingSessions(),
            'documentQueue' => $this->statistics->documentQueue($filters),
            'programs' => Program::orderBy('name')->get(['id', 'name', 'code']),
            'terms' => $this->statistics->termOptions(),
            'statuses' => self::STATUSES,
            'types' => AdmissionRequirement::APPLICANT_TYPES,
            'examMode' => AdmissionSettings::examMode(),
        ]);
    }

    /** Applicant list export, one row per application, honouring the filters. */
    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        AuditLog::record('admission.report.csv', 'Exported the admissions applicant list (CSV).', ['filters' => $filters]);

        $query = $this->statistics->applications($filters)
            ->with(['program:id,code,name', 'currentDocuments:id,application_id,status', 'examBookings.session'])
            ->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows names correctly.
            fputcsv($out, [
                'Reference No.', 'Submitted', 'School Year', 'Semester', 'Last Name', 'First Name', 'Middle Name',
                'Email', 'Phone', 'Applicant Type', 'Program', 'Application Status',
                'Docs Approved', 'Docs Awaiting Review', 'Docs Rejected',
                'Exam Date', 'Exam Status', 'Exam Result', 'Exam Score',
            ]);
            $query->chunk(500, function ($applications) use ($out) {
                foreach ($applications as $application) {
                    $docs = $application->currentDocuments;
                    $booking = $application->examBookings->sortByDesc('id')
                        ->first(fn ($b) => !in_array($b->status, ['rescheduled'], true));
                    fputcsv($out, array_map([$this, 'csvSafe'], [
                        $application->reference_no,
                        $application->created_at?->format('Y-m-d H:i'),
                        $application->school_year,
                        $application->semester,
                        $application->last_name,
                        $application->first_name,
                        $application->middle_name,
                        $application->email,
                        $application->phone,
                        AdmissionRequirement::APPLICANT_TYPES[$application->academic_status] ?? $application->academic_status,
                        $application->program->code ?? '',
                        ucwords(str_replace('_', ' ', $application->status)),
                        $docs->where('status', 'approved')->count(),
                        $docs->where('status', 'submitted')->count(),
                        $docs->where('status', 'rejected')->count(),
                        $booking?->session?->exam_date?->format('Y-m-d'),
                        $booking ? $booking->statusLabel() : '',
                        $booking?->result ? ucfirst($booking->result) : '',
                        $booking?->score,
                    ]));
                }
            });
            fclose($out);
        }, 'DTC_Admissions_' . now()->format('Ymd_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportPdf(Request $request)
    {
        $filters = $this->filters($request);
        AuditLog::record('admission.report.pdf', 'Exported the admissions summary report (PDF).', ['filters' => $filters]);

        $programs = Program::orderBy('name')->pluck('name', 'id');
        $pdf = Pdf::loadView('registrar.admissions.report-pdf', [
            'filters' => $filters,
            'filterProgram' => isset($filters['program_id']) ? $programs[$filters['program_id']] ?? null : null,
            'stats' => $this->statistics->summary($filters),
            'byProgram' => $this->statistics->byProgram($filters),
            'byType' => $this->statistics->byType($filters),
            'types' => AdmissionRequirement::APPLICANT_TYPES,
            'sessions' => \App\Models\EntranceExamSession::query()
                ->when($filters['exam_date'] ?? null, fn ($q, $v) => $q->whereDate('exam_date', $v))
                ->when($filters['school_year'] ?? null, fn ($q, $v) => $q->where(fn ($s) => $s->whereNull('school_year')->orWhere('school_year', $v)))
                ->withCount([
                    'seatedBookings',
                    'bookings as attended_count' => fn ($q) => $q->where('status', 'attended'),
                    'bookings as absent_count' => fn ($q) => $q->where('status', 'absent'),
                    'bookings as passed_count' => fn ($q) => $q->where('status', 'attended')->where('result', 'passed'),
                ])
                ->orderByDesc('exam_date')->limit(40)->get(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('DTC_Admissions_Report_' . now()->format('Ymd') . '.pdf');
    }

    private function filters(Request $request): array
    {
        return array_filter($request->validate([
            'school_year' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', Rule::in(['1st', '2nd', 'Summer'])],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'type' => ['nullable', Rule::in(array_keys(AdmissionRequirement::APPLICANT_TYPES))],
            'exam_date' => ['nullable', 'date'],
        ]), fn ($value) => $value !== null && $value !== '');
    }

    /** Prevent spreadsheet formula injection from applicant-entered text. */
    private function csvSafe($value)
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }
}
