<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\StudentProfile;
use Illuminate\Http\Request;
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
            'records' => $query->orderBy('last_name')->orderBy('student_number')->paginate(25)->withQueryString(),
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
        $record = StudentProfile::create($data);
        $this->audit($request, 'academic_record.created', $record);
        return redirect()->route('registrar.students.show', $record)->with('success', 'Academic record created. No portal account was created.');
    }

    public function show(StudentProfile $student)
    {
        $student->load(['user', 'program']);
        return view('registrar.students.show', [
            'record' => $student,
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
        $data = $this->validated($request, $student);
        if ($student->user_id && $data['academic_status'] !== $student->academic_status) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'academic_status' => 'Use the established student-to-alumni promotion workflow for account-linked students.',
            ]);
        }
        unset($data['student_number']);
        $student->update($data);
        $this->audit($request, 'academic_record.updated', $student);
        return redirect()->route('registrar.students.show', $student)->with('success', 'Academic record updated.');
    }

    private function validated(Request $request, ?StudentProfile $record = null): array
    {
        return $request->validate([
            'student_number' => ['required', 'string', 'max:50', Rule::unique('student_profiles', 'student_number')->ignore($record?->id)],
            'first_name' => ['required', 'string', 'max:100'], 'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'], 'suffix' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'], 'program_id' => ['nullable', 'exists:programs,id'],
            'year_level' => ['nullable', 'integer', 'between:1,8'], 'enrolled_ay' => ['nullable', 'regex:/^\d{4}-\d{4}$/'],
            'graduation_year' => ['nullable', 'integer', 'between:1900,'.(date('Y') + 1)], 'graduation_date' => ['nullable', 'date'],
            'academic_status' => ['required', Rule::in(['active', 'inactive', 'alumni'])],
        ]);
    }

    private function audit(Request $request, string $action, StudentProfile $record): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'action' => $action, 'description' => 'Academic record #'.$record->id, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'metadata' => ['record_id' => $record->id]]);
    }
}
