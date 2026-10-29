<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Setting;
use App\Models\StudentProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AcademicRecordController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->query('type') === 'alumni' ? 'alumni' : 'students';
        $query = StudentProfile::with(['user', 'program'])->when($type === 'alumni', fn ($q) => $q->where('academic_status', 'alumni'), fn ($q) => $q->where('academic_status', '!=', 'alumni'));
        if ($request->filled('q')) $query->where(function ($q) use ($request) {
            $term = '%'.$request->query('q').'%';
            $q->where('student_number', 'like', $term)->orWhere('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('email', 'like', $term)->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term));
        });
        $query->when($request->filled('program_id'), fn ($q) => $q->where('program_id', $request->integer('program_id')))
            ->when($request->filled('academic_status'), fn ($q) => $q->where('academic_status', $request->query('academic_status')))
            ->when($request->filled('year'), fn ($q) => $q->where('graduation_year', $request->integer('year')))
            ->when($request->filled('school_year'), fn ($q) => $q->where('enrolled_ay', $request->query('school_year')));

        return view('registrar.students.index', [
            'records' => $query->orderBy('last_name')->orderBy('student_number')->paginate(\App\Support\TablePagination::perPage(25))->withQueryString(),
            'programs' => Program::orderBy('name')->get(), 'type' => $type,
        ]);
    }

    public function create(Request $request)
    {
        return view('registrar.students.form', ['record' => new StudentProfile(['academic_status' => $request->query('type') === 'alumni' ? 'alumni' : 'active']), 'programs' => Program::orderBy('name')->get(), 'type' => $request->query('type') === 'alumni' ? 'alumni' : 'students']);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['user_id'] = null;
        $data['admission_type'] = 'imported';
        $record = DB::transaction(function () use ($request, $data) {
            $record = StudentProfile::create($data);
            $this->audit($request, 'academic_record.created', $record);
            return $record;
        });
        return redirect()->route('registrar.students.show', $record)->with('success', 'Academic record created. No portal account was created.');
    }

    public function show(StudentProfile $student)
    {
        $student->load(['user', 'program', 'portalInvitation']);
        $invitation = $student->portalInvitation;
        if ($invitation && $invitation->status === 'pending' && $invitation->expires_at && $invitation->expires_at->isPast()) {
            $invitation->update(['status' => 'expired', 'token_hash' => null]);
        }
        $currentSchoolYear = Setting::get('current_school_year');
        $currentSemester = Setting::get('current_semester');
        $semesterNumbers = ['1st' => 1, '2nd' => 2, 'Summer' => 3];
        $hasConfiguredTerm = $currentSchoolYear && isset($semesterNumbers[$currentSemester]);
        $portalEnrollment = $student->user
            ? Enrollment::where('user_id', $student->user_id)
                ->when($hasConfiguredTerm, fn ($query) => $query
                    ->where('school_year', $currentSchoolYear)
                    ->where('semester', $semesterNumbers[$currentSemester]))
                ->latest()->orderByDesc('id')->first()
            : null;

        return view('registrar.students.show', [
            'record' => $student,
            'portalEnrollment' => $portalEnrollment,
            'enrollments' => $student->user ? Enrollment::with('program')->where('user_id', $student->user_id)->latest('id')->get() : collect(),
            'documentRequests' => $student->user ? DocumentRequest::where('user_id', $student->user_id)->latest('id')->get() : collect(),
        ]);
    }

    public function edit(StudentProfile $student)
    {
        return view('registrar.students.form', ['record' => $student, 'programs' => Program::orderBy('name')->get(), 'type' => $student->academic_status === 'alumni' ? 'alumni' : 'students']);
    }

    public function update(Request $request, StudentProfile $student)
    {
        DB::transaction(function () use ($request, $student) {
            // Provisioning uses the same profile lock: recheck the persisted link
            // rather than trusting a route model loaded before another request.
            $student = StudentProfile::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $data = $this->validated($request, $student);
            if ($student->user_id && $data['academic_status'] !== $student->academic_status) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'academic_status' => 'Use the established student-to-alumni promotion workflow for account-linked students.',
                ]);
            }
            if ($student->user_id && ($data['email'] ?? null) !== $student->email) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email' => 'This academic record is linked to a portal account. Resolve email changes through the protected account process.',
                ]);
            }
            unset($data['student_number']);
            $student->update($data);
            $this->audit($request, 'academic_record.updated', $student);
        });
        return redirect()->route('registrar.students.show', $student)->with('success', 'Academic record updated.');
    }

    private function validated(Request $request, ?StudentProfile $record = null): array
    {
        $data = $request->validate([
            'record_type' => ['nullable', Rule::in(['students', 'alumni'])],
            'student_number' => ['required', 'string', 'max:50', Rule::unique('student_profiles', 'student_number')->ignore($record?->id)],
            'first_name' => ['required', 'string', 'max:100'], 'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'], 'suffix' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'], 'program_id' => ['nullable', 'exists:programs,id'],
            'year_level' => ['nullable', 'bail', 'integer', 'between:1,'.StudentProfile::MAX_YEAR_LEVEL], 'enrolled_ay' => ['nullable', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'graduation_year' => ['nullable', 'integer', 'between:1900,'.(date('Y') + 1)], 'graduation_date' => ['nullable', 'date'],
            'academic_status' => ['required', Rule::in(['active', 'inactive', 'alumni'])],
        ], [
            'year_level.integer' => 'Enter a whole-number year level.',
            'year_level.between' => 'Year level must be between 1 and '.StudentProfile::MAX_YEAR_LEVEL.'.',
        ]);
        if (!empty($data['enrolled_ay'])) {
            [$start, $end] = array_map('intval', explode('-', $data['enrolled_ay']));
            if ($end <= $start) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'enrolled_ay' => 'The school year must end after its starting year.',
                ]);
            }
        }
        if (isset($data['record_type']) && (($data['record_type'] === 'alumni') !== ($data['academic_status'] === 'alumni'))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'academic_status' => 'The academic status must match the selected Student or Alumni record type.',
            ]);
        }
        if (!empty($data['graduation_year']) && !empty($data['graduation_date'])
            && (int) \Illuminate\Support\Carbon::parse($data['graduation_date'])->year !== (int) $data['graduation_year']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'graduation_date' => 'The graduation date must fall within the graduation year.',
            ]);
        }
        unset($data['record_type']);
        return $data;
    }

    private function audit(Request $request, string $action, StudentProfile $record): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'action' => $action, 'description' => 'Academic record #'.$record->id, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'metadata' => ['record_id' => $record->id]]);
    }
}
