<?php

namespace Tests\Feature;

use App\Models\CourseSubject;
use App\Models\CreditedSubject;
use App\Models\Enrollment;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class CreditedSubjectTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function makeEnrollmentWithSubject(): array
    {
        $program = Program::factory()->create();
        $subject = CourseSubject::factory()->for($program)->create(['units' => 3]);
        $enrollment = Enrollment::factory()->create([
            'program_id'  => $program->id,
            'subject_ids' => [$subject->id],
            'status'      => 'approved',
            'is_paid'     => true,
        ]);

        return compact('program', 'subject', 'enrollment');
    }

    /** @test */
    public function registrar_can_add_a_credited_subject()
    {
        $registrar = $this->makeUserWithRole('registrar');
        ['subject' => $subject, 'enrollment' => $enrollment] = $this->makeEnrollmentWithSubject();

        $response = $this->actingAs($registrar)->post(
            route('registrar.enrollments.credited-subjects.store', $enrollment),
            [
                'course_subject_id'  => $subject->id,
                'equivalent_subject' => 'Mathematics 101',
                'equivalent_school'  => 'ABC College',
                'credited_units'     => 3,
                'remarks'            => 'Equivalent subject completed previously.',
            ]
        );

        $response->assertRedirect(route('registrar.enrollments.show', $enrollment));

        $this->assertDatabaseHas('credited_subjects', [
            'enrollment_id'      => $enrollment->id,
            'course_subject_id'  => $subject->id,
            'equivalent_subject' => 'Mathematics 101',
            'equivalent_school'  => 'ABC College',
        ]);
    }

    /** @test */
    public function a_student_cannot_add_a_credited_subject()
    {
        $student = $this->makeUserWithRole('student');
        ['subject' => $subject, 'enrollment' => $enrollment] = $this->makeEnrollmentWithSubject();

        $this->actingAs($student)->post(
            route('registrar.enrollments.credited-subjects.store', $enrollment),
            ['course_subject_id' => $subject->id]
        )->assertForbidden();

        $this->assertDatabaseMissing('credited_subjects', ['enrollment_id' => $enrollment->id]);
    }

    /** @test */
    public function a_cashier_cannot_add_a_credited_subject()
    {
        $cashier = $this->makeUserWithRole('cashier');
        ['subject' => $subject, 'enrollment' => $enrollment] = $this->makeEnrollmentWithSubject();

        $this->actingAs($cashier)->post(
            route('registrar.enrollments.credited-subjects.store', $enrollment),
            ['course_subject_id' => $subject->id]
        )->assertForbidden();
    }

    /** @test */
    public function a_credited_subject_from_another_program_is_rejected()
    {
        $registrar = $this->makeUserWithRole('registrar');
        ['enrollment' => $enrollment] = $this->makeEnrollmentWithSubject();

        $otherProgram = Program::factory()->create();
        $foreignSubject = CourseSubject::factory()->for($otherProgram)->create();

        $this->actingAs($registrar)->post(
            route('registrar.enrollments.credited-subjects.store', $enrollment),
            ['course_subject_id' => $foreignSubject->id]
        )->assertStatus(422);

        $this->assertDatabaseMissing('credited_subjects', ['enrollment_id' => $enrollment->id]);
    }

    /** @test */
    public function registrar_can_edit_a_credited_subject()
    {
        $registrar = $this->makeUserWithRole('registrar');
        ['subject' => $subject, 'enrollment' => $enrollment] = $this->makeEnrollmentWithSubject();

        $credit = CreditedSubject::create([
            'enrollment_id'     => $enrollment->id,
            'course_subject_id' => $subject->id,
            'equivalent_subject' => 'Old Value',
        ]);

        $response = $this->actingAs($registrar)->put(
            route('registrar.enrollments.credited-subjects.update', $credit),
            ['equivalent_subject' => 'Updated Value', 'credited_units' => 3]
        );

        $response->assertRedirect(route('registrar.enrollments.show', $enrollment));

        $this->assertDatabaseHas('credited_subjects', [
            'id' => $credit->id,
            'equivalent_subject' => 'Updated Value',
        ]);
    }

    /** @test */
    public function a_student_cannot_edit_a_credited_subject()
    {
        $student = $this->makeUserWithRole('student');
        ['subject' => $subject, 'enrollment' => $enrollment] = $this->makeEnrollmentWithSubject();

        $credit = CreditedSubject::create([
            'enrollment_id'     => $enrollment->id,
            'course_subject_id' => $subject->id,
        ]);

        $this->actingAs($student)->put(
            route('registrar.enrollments.credited-subjects.update', $credit),
            ['equivalent_subject' => 'Hacked Value']
        )->assertForbidden();

        $this->assertDatabaseMissing('credited_subjects', ['equivalent_subject' => 'Hacked Value']);
    }

    /** @test */
    public function registrar_can_delete_a_credited_subject()
    {
        $registrar = $this->makeUserWithRole('registrar');
        ['subject' => $subject, 'enrollment' => $enrollment] = $this->makeEnrollmentWithSubject();

        $credit = CreditedSubject::create([
            'enrollment_id'     => $enrollment->id,
            'course_subject_id' => $subject->id,
        ]);

        $this->actingAs($registrar)
            ->delete(route('registrar.enrollments.credited-subjects.destroy', $credit))
            ->assertRedirect(route('registrar.enrollments.show', $enrollment));

        $this->assertDatabaseMissing('credited_subjects', ['id' => $credit->id]);
    }

    /** @test */
    public function a_student_cannot_delete_a_credited_subject()
    {
        $student = $this->makeUserWithRole('student');
        ['subject' => $subject, 'enrollment' => $enrollment] = $this->makeEnrollmentWithSubject();

        $credit = CreditedSubject::create([
            'enrollment_id'     => $enrollment->id,
            'course_subject_id' => $subject->id,
        ]);

        $this->actingAs($student)
            ->delete(route('registrar.enrollments.credited-subjects.destroy', $credit))
            ->assertForbidden();

        $this->assertDatabaseHas('credited_subjects', ['id' => $credit->id]);
    }

    /** @test */
    public function adding_a_credited_subject_does_not_change_the_cor_total_units_or_subject_list()
    {
        $registrar = $this->makeUserWithRole('registrar');
        ['subject' => $subject, 'enrollment' => $enrollment] = $this->makeEnrollmentWithSubject();

        // Baseline COR before any credit is recorded.
        $before = $this->actingAs($registrar)->get(route('registrar.enrollments.cor.show', $enrollment));
        $before->assertOk();
        $before->assertSee((string) $subject->subject_code);

        // Record a credit for that same subject.
        $this->actingAs($registrar)->post(
            route('registrar.enrollments.credited-subjects.store', $enrollment),
            ['course_subject_id' => $subject->id, 'credited_units' => 3]
        );

        // Enrollment's subject_ids and the enrollment fee/payment must be
        // completely unaffected — credited_subjects is documentation only.
        $enrollment->refresh();
        $this->assertEqualsCanonicalizing([$subject->id], $enrollment->subject_ids);
        $this->assertTrue($enrollment->is_paid);

        $after = $this->actingAs($registrar)->get(route('registrar.enrollments.cor.show', $enrollment));
        $after->assertOk();
        $after->assertSee((string) $subject->subject_code);
    }
}
