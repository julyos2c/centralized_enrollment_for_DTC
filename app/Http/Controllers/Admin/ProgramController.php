<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\CourseSubject;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    public function index()
    {
        $programs = Program::withCount('subjects')->get();
        return view('admin.programs.index', compact('programs'));
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
            'year_level'   => ['required', 'integer', 'min:1', 'max:4'],
            'semester'     => ['required', 'integer', 'in:1,2'],
        ]);

        CourseSubject::create([
            ...$validated,
            'program_id' => $program->id,
        ]);

        return redirect()->route('admin.programs.subjects', $program)
            ->with('success', 'Subject added.');
    }

    public function destroySubject(CourseSubject $subject)
    {
        $programId = $subject->program_id;
        $subject->delete();

        return redirect()->route('admin.programs.subjects', $programId)
            ->with('success', 'Subject removed.');
    }
}