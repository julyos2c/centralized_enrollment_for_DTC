<?php

namespace Tests\Feature;

use App\Models\CourseSubject;
use App\Models\Program;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class IrregularEnrollmentTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Setting::set('current_school_year', '2026-2027');
        Setting::set('current_semester', '1st');
    }

    private function makeCurriculum(Program $program): array
    {
        // Year 1 Sem 1: two subjects. Year 2 Sem 1: two subjects.
        $y1s1a = CourseSubject::factory()->for($program)->yearSem(1, 1)->create(['subject_code' => 'IS101']);
        $y1s1b = CourseSubject::factory()->for($program)->yearSem(1, 1)->create(['subject_code' => 'IS102']);
        $y2s1a = CourseSubject::factory()->for($program)->yearSem(2, 1)->create(['subject_code' => 'IS201']);
        $y2s1b = CourseSubject::factory()->for($program)->yearSem(2, 1)->create(['subject_code' => 'IS202']);

        return compact('y1s1a', 'y1s1b', 'y2s1a', 'y2s1b');
    }

    /** @test */
    public function regular_enrollment_still_works_with_single_year_semester_subjects()
    {
        $student = $this->makeUserWithRole('student');
        $program = Program::factory()->create();
        ['y1s1a' => $a, 'y1s1b' => $b] = $this->makeCurriculum($program);

        $response = $this->actingAs($student)->post(route('portal.enrollment.store'), [
            'program_id'  => $program->id,
            'year_level'  => 1,
            'semester'    => 1,
            'is_irregular' => '0',
            'subject_ids' => [$a->id, $b->id],
        ]);

        $response->assertRedirect(route('portal.enrollment.index'));

        $this->assertDatabaseHas('enrollments', [
            'user_id'      => $student->id,
            'is_irregular' => 0,
        ]);
    }

    /** @test */
    public function getSubjects_regular_mode_only_returns_the_requested_year_and_semester()
    {
        $student = $this->makeUserWithRole('student');
        $program = Program::factory()->create();
        $this->makeCurriculum($program);

        $response = $this->actingAs($student)
            ->getJson(route('portal.enrollment.subjects', $program) . '?year_level=1&semester=1');

        $response->assertOk();
        $codes = collect($response->json())->pluck('subject_code');
        $this->assertEqualsCanonicalizing(['IS101', 'IS102'], $codes->all());
    }

    /** @test */
    public function getSubjects_all_mode_returns_every_subject_grouped_by_year_and_semester()
    {
        $student = $this->makeUserWithRole('student');
        $program = Program::factory()->create();
        $this->makeCurriculum($program);

        $response = $this->actingAs($student)
            ->getJson(route('portal.enrollment.subjects', $program) . '?mode=all');

        $response->assertOk();
        $groups = $response->json();

        $this->assertCount(2, $groups); // Year1/Sem1 and Year2/Sem1
        $totalSubjects = collect($groups)->sum(fn ($g) => count($g['subjects']));
        $this->assertSame(4, $totalSubjects);
    }

    /** @test */
    public function irregular_enrollment_can_select_subjects_across_multiple_year_levels_in_one_submission()
    {
        $student = $this->makeUserWithRole('student');
        $program = Program::factory()->create();
        ['y1s1a' => $a, 'y2s1a' => $c] = $this->makeCurriculum($program);

        $response = $this->actingAs($student)->post(route('portal.enrollment.store'), [
            'program_id'   => $program->id,
            'year_level'   => 2,
            'semester'     => 1,
            'is_irregular' => '1',
            'subject_ids'  => [$a->id, $c->id], // one from Year 1, one from Year 2
        ]);

        $response->assertRedirect(route('portal.enrollment.index'));

        $enrollment = \App\Models\Enrollment::where('user_id', $student->id)->first();
        $this->assertTrue($enrollment->is_irregular);
        $this->assertEqualsCanonicalizing([$a->id, $c->id], $enrollment->subject_ids);
    }

    /** @test */
    public function subjects_belonging_to_another_program_are_rejected_even_in_irregular_mode()
    {
        $student   = $this->makeUserWithRole('student');
        $programA  = Program::factory()->create();
        $programB  = Program::factory()->create();
        $ownSubject   = CourseSubject::factory()->for($programA)->yearSem(1, 1)->create();
        $otherSubject = CourseSubject::factory()->for($programB)->yearSem(1, 1)->create();

        $this->actingAs($student)->post(route('portal.enrollment.store'), [
            'program_id'   => $programA->id,
            'year_level'   => 1,
            'semester'     => 1,
            'is_irregular' => '1',
            'subject_ids'  => [$ownSubject->id, $otherSubject->id],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('enrollments', ['user_id' => $student->id]);
    }

    /** @test */
    public function duplicate_subject_ids_in_the_same_submission_are_rejected()
    {
        $student = $this->makeUserWithRole('student');
        $program = Program::factory()->create();
        $subject = CourseSubject::factory()->for($program)->yearSem(1, 1)->create();

        $this->actingAs($student)->post(route('portal.enrollment.store'), [
            'program_id'   => $program->id,
            'year_level'   => 1,
            'semester'     => 1,
            'is_irregular' => '0',
            'subject_ids'  => [$subject->id, $subject->id],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('enrollments', ['user_id' => $student->id]);
    }

    /** @test */
    public function enrollment_type_defaults_to_regular_when_not_submitted()
    {
        $student = $this->makeUserWithRole('student');
        $program = Program::factory()->create();
        $subject = CourseSubject::factory()->for($program)->yearSem(1, 1)->create();

        $this->actingAs($student)->post(route('portal.enrollment.store'), [
            'program_id'  => $program->id,
            'year_level'  => 1,
            'semester'    => 1,
            'subject_ids' => [$subject->id],
            // is_irregular intentionally omitted
        ])->assertRedirect(route('portal.enrollment.index'));

        $this->assertDatabaseHas('enrollments', [
            'user_id'      => $student->id,
            'is_irregular' => 0,
        ]);
    }
}
