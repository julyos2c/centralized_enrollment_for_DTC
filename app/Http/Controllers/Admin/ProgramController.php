<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\CourseSubject;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    public function index(Request $request)
    {
        $programs = Program::withCount('subjects')
            ->orderBy('name')
            ->paginate(10, ['*'], 'page');

        $selectedProgram = null;
        $subjects = collect();

        if ($request->filled('program')) {
            $selectedProgram = Program::find($request->query('program'));

            if ($selectedProgram) {
                $subjects = CourseSubject::where('program_id', $selectedProgram->id)
                    ->with('schedules')
                    ->orderBy('year_level')
                    ->orderBy('semester')
                    ->orderBy('subject_name')
                    ->paginate(10, ['*'], 'subjects_page');
            }
        }

        // AJAX program-select: return only the Subjects card markup so the
        // page doesn't do a full reload (navbar/sidebar/programs table stay
        // untouched). Triggered by fetch() with X-Requested-With header.
        if ($request->ajax()) {
            return view('admin.programs.partials.subjects-card', compact('selectedProgram', 'subjects'));
        }

        return view('admin.programs.index', compact('programs', 'selectedProgram', 'subjects'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:programs,code'],
        ]);

        Program::create($validated);

        return redirect()->route('admin.programs.index')
            ->with('success', 'Program added successfully.');
    }

    public function update(Request $request, Program $program)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:programs,code,' . $program->id],
        ]);

        $program->update($validated);

        return redirect()->route('admin.programs.index', $request->only('program'))
            ->with('success', 'Program updated successfully.');
    }

    public function destroy(Program $program)
    {
        $program->delete();
        return redirect()->route('admin.programs.index')
            ->with('success', 'Program removed.');
    }

    public function subjects(Program $program)
    {
        $subjects = CourseSubject::where('program_id', $program->id)
            ->orderBy('year_level')
            ->orderBy('semester')
            ->get();

        return view('admin.programs.subjects', compact('program', 'subjects'));
    }

    public function storeSubject(Request $request, Program $program)
    {
        $validated = $request->validate([
            'subject_code' => ['required', 'string', 'max:20'],
            'subject_name' => ['required', 'string', 'max:255'],
            'units'        => ['required', 'numeric', 'min:0.5', 'max:6'],
            'year_level'   => ['required', 'integer', 'min:1', 'max:4'],
            'semester'     => ['required', 'integer', 'in:1,2'],
        ]);

        CourseSubject::create(array_merge($validated, [
            'program_id' => $program->id,
        ]));

        return redirect()->route('admin.programs.index', ['program' => $program->id])
            ->with('success', 'Subject added.');
    }

    public function updateSubject(Request $request, CourseSubject $subject)
    {
        $validated = $request->validate([
            'subject_code' => ['required', 'string', 'max:20'],
            'subject_name' => ['required', 'string', 'max:255'],
            'units'        => ['required', 'numeric', 'min:0.5', 'max:6'],
            'year_level'   => ['required', 'integer', 'min:1', 'max:4'],
            'semester'     => ['required', 'integer', 'in:1,2'],
        ]);

        $subject->update($validated);

        return redirect()->route('admin.programs.index', ['program' => $subject->program_id])
            ->with('success', 'Subject updated.');
    }

    public function destroySubject(CourseSubject $subject)
    {
        $programId = $subject->program_id;
        $subject->delete();

        return redirect()->route('admin.programs.index', ['program' => $programId])
            ->with('success', 'Subject removed.');
    }

    // ── SCHEDULE (per subject) ──────────────────────────────────────────────
    // Modeled as hasMany on CourseSubject, but the UI manages one schedule
    // per subject for now (updateOrCreate keeps it that way).

    public function storeSchedule(Request $request, CourseSubject $subject)
    {
        $validated = $request->validate([
            'day_pattern'      => ['required', 'string', 'max:20'],
            'time_start'       => ['required', 'date_format:H:i'],
            'time_end'         => ['required', 'date_format:H:i', 'after:time_start'],
            'room'             => ['nullable', 'string', 'max:50'],
            'instructor_name'  => ['nullable', 'string', 'max:255'],
        ]);

        $subject->schedules()->updateOrCreate(
            ['course_subject_id' => $subject->id],
            array_merge($validated, [
                'room' => $validated['room'] ?? 'TBA',
            ])
        );

        return redirect()->route('admin.programs.index', ['program' => $subject->program_id])
            ->with('success', 'Schedule saved.');
    }

    public function destroySchedule(\App\Models\ClassSchedule $schedule)
    {
        $programId = $schedule->courseSubject->program_id;
        $schedule->delete();

        return redirect()->route('admin.programs.index', ['program' => $programId])
            ->with('success', 'Schedule removed.');
    }
}