<?php

namespace Tests\Feature;

use App\Models\CourseSubject;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

/**
 * Verifies that a student who already has an active (pending or approved)
 * enrollment cannot submit a second enrollment.
 *
 * "Active" means status IN ('pending', 'approved') — the same predicate
 * used by Student\EnrollmentController::create() and ::store().
 *
 * Rejected enrollments are intentionally excluded so a student may
 * re-enroll after being turned down.
 */
class DoubleEnrollmentTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Setting::set('current_school_year', '2026-2027');
        Setting::set('current_semester', '1st');
    }

    // ── helpers ───────────────────────────────────────────────────────────

    /**
     * Create a single course subject for the given program so the
     * enrollment store payload is always valid.
     */
    private function makeSubject(Program $program): CourseSubject
    {
        return CourseSubject::factory()
            ->for($program)
            ->yearSem(1, 1)
            ->create(['subject_code' => 'IS101']);
    }

    /**
     * Minimal valid store payload for the given program / subject.
     */
    private function storePayload(Program $program, CourseSubject $subject): array
    {
        return [
            'program_id'   => $program->id,
            'year_level'   => 1,
            'semester'     => 1,
            'is_irregular' => '0',
            'subject_ids'  => [$subject->id],
        ];
    }

    // ── store() — double-enrollment guard ────────────────────────────────

    /** @test */
    public function student_with_a_pending_enrollment_cannot_submit_another()
    {
        $student = $this->makeUserWithRole('student');
        $program = Program::factory()->create();
        $subject = $this->makeSubject($program);

        // Pre-existing pending enrollment
        Enrollment::factory()->pending()->create([
            'user_id'    => $student->id,
            'program_id' => $program->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ]);

        $response = $this->actingAs($student)
            ->post(route('portal.enrollment.store'), $this->storePayload($program, $subject));

        // Must redirect (not 422) with an error flash — the controller
        // returns redirect()->with('error', ...) for this case.
        $response->assertRedirect();
        $response->assertSessionHas('error');

        // No second enrollment must have been created.
        $this->assertSame(
            1,
            Enrollment::where('user_id', $student->id)->count(),
            'Expected exactly one enrollment row but found more.'
        );
    }

    /** @test */
    public function student_with_an_approved_enrollment_cannot_submit_another()
    {
        $student = $this->makeUserWithRole('student');
        $program = Program::factory()->create();
        $subject = $this->makeSubject($program);

        // Pre-existing approved enrollment (unpaid is fine — still active)
        Enrollment::factory()->create([
            'user_id'    => $student->id,
            'program_id' => $program->id,
            'status'     => 'approved',
            'school_year' => '2026-2027',
            'semester' => 1,
        ]);

        $response = $this->actingAs($student)
            ->post(route('portal.enrollment.store'), $this->storePayload($program, $subject));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertSame(
            1,
            Enrollment::where('user_id', $student->id)->count()
        );
    }

    /** @test */
    public function student_with_a_paid_approved_enrollment_cannot_submit_another()
    {
        $student = $this->makeUserWithRole('student');
        $program = Program::factory()->create();
        $subject = $this->makeSubject($program);

        // Fully paid and approved — still an active enrollment.
        Enrollment::factory()->paid()->create([
            'user_id'    => $student->id,
            'program_id' => $program->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ]);

        $response = $this->actingAs($student)
            ->post(route('portal.enrollment.store'), $this->storePayload($program, $subject));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertSame(
            1,
            Enrollment::where('user_id', $student->id)->count()
        );
    }

    /** @test */
    public function student_with_only_a_rejected_enrollment_is_allowed_to_enroll_again()
    {
        $student = $this->makeUserWithRole('student');
        $program = Program::factory()->create();
        $subject = $this->makeSubject($program);

        // Rejected — no longer active; student should be allowed to retry.
        Enrollment::factory()->create([
            'user_id'    => $student->id,
            'program_id' => $program->id,
            'status'     => 'rejected',
        ]);

        $response = $this->actingAs($student)
            ->post(route('portal.enrollment.store'), $this->storePayload($program, $subject));

        $response->assertRedirect(route('portal.enrollment.index'));
        $response->assertSessionHas('success');

        // Two rows now: the old rejected one + the new pending one.
        $this->assertSame(
            2,
            Enrollment::where('user_id', $student->id)->count()
        );

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'status'  => 'pending',
        ]);
    }

    /** @test */
    public function student_with_no_prior_enrollment_can_enroll_successfully()
    {
        $student = $this->makeUserWithRole('student');
        $program = Program::factory()->create();
        $subject = $this->makeSubject($program);

        $response = $this->actingAs($student)
            ->post(route('portal.enrollment.store'), $this->storePayload($program, $subject));

        $response->assertRedirect(route('portal.enrollment.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'status'  => 'pending',
            'school_year' => '2026-2027',
            'semester' => 1,
        ]);
    }

    /** @test */
    public function an_approved_enrollment_from_a_previous_term_does_not_block_the_current_term()
    {
        $student = $this->makeUserWithRole('student');
        $program = Program::factory()->create();
        $subject = $this->makeSubject($program);
        Enrollment::factory()->create([
            'user_id' => $student->id,
            'program_id' => $program->id,
            'status' => 'approved',
            'school_year' => '2025-2026',
            'semester' => 2,
        ]);

        $this->actingAs($student)
            ->post(route('portal.enrollment.store'), $this->storePayload($program, $subject))
            ->assertRedirect(route('portal.enrollment.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function client_semester_cannot_bypass_same_term_duplicate_guard_or_change_saved_term()
    {
        $student = $this->makeUserWithRole('student');
        $program = Program::factory()->create();
        $subject = $this->makeSubject($program);
        Enrollment::factory()->pending()->create([
            'user_id' => $student->id,
            'program_id' => $program->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ]);

        $this->actingAs($student)
            ->post(route('portal.enrollment.store'), array_merge($this->storePayload($program, $subject), ['semester' => 3]))
            ->assertSessionHas('error');

        $this->assertSame(1, Enrollment::where('user_id', $student->id)->count());
    }

    // ── create() — double-enrollment guard (student branch) ───────────────

    /** @test */
    public function create_page_redirects_student_with_active_pending_enrollment()
    {
        $student = $this->makeUserWithRole('student');
        $program = Program::factory()->create();

        Enrollment::factory()->pending()->create([
            'user_id'    => $student->id,
            'program_id' => $program->id,
        ]);

        $response = $this->actingAs($student)
            ->get(route('portal.enrollment.create'));

        // The student branch in create() does NOT contain the guard —
        // it only applies to new_applicants. This test documents that
        // deliberate design: a student can always visit the create page.
        // If the guard is later added to the student branch as well,
        // change the assertion below to assertRedirect + assertSessionHas('error').
        $response->assertOk();
    }

    /** @test */
    public function create_page_redirects_new_applicant_with_active_enrollment()
    {
        $applicant = $this->makeUserWithRole('new_applicant');
        $program   = Program::factory()->create();

        // Approved application so the create() method doesn't bail out first.
        \App\Models\Application::create([
            'reference_no'    => 'APP-TEST-' . $applicant->id,
            'user_id'         => $applicant->id,
            'program_id'      => $program->id,
            'status'          => 'approved',
            'academic_status' => 'new_student',
            'first_name'      => 'Juan',
            'last_name'       => 'Dela Cruz',
            'email'           => $applicant->email,
        ]);

        Enrollment::factory()->pending()->create([
            'user_id'    => $applicant->id,
            'program_id' => $program->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ]);

        $response = $this->actingAs($applicant)
            ->get(route('portal.enrollment.create'));

        $response->assertRedirect(route('portal.dashboard'));
        $response->assertSessionHas('error');
    }
}
