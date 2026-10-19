<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

/**
 * Covers the Registrar "Promote to Alumni" action.
 *
 * Business rules under test:
 *   - Registrar can promote a paid student; role becomes alumni, graduation_year is set.
 *   - Admin can also promote (same route middleware: registrar|admin).
 *   - graduation_year is required and must be a realistic integer.
 *   - Non-students (alumni, cashiers) cannot be promoted via this endpoint.
 *   - Students (and other non-registrars) cannot access the endpoint at all.
 *   - After promotion the user's student profile graduation_year is persisted.
 *   - After promotion the user no longer has the student role.
 */
class PromoteToAlumniTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    // ── helpers ───────────────────────────────────────────────────────────

    private function paidStudentWithProfile(): User
    {
        $student    = $this->makeUserWithRole('student');
        $enrollment = Enrollment::factory()->paid()->create(['user_id' => $student->id]);

        StudentProfile::firstOrCreate(
            ['user_id' => $student->id],
            [
                'student_number' => '2025-00001',
                'admission_type' => 'new_student',
                'enrolled_ay'    => '2024-2025',
            ]
        );

        return $student;
    }

    private function promoteRoute(User $user): string
    {
        return route('registrar.students.promote-alumni', $user);
    }

    // ── happy path ────────────────────────────────────────────────────────

    /** @test */
    public function registrar_can_promote_a_paid_student_to_alumni()
    {
        $registrar = $this->makeUserWithRole('registrar');
        $student   = $this->paidStudentWithProfile();

        $response = $this->actingAs($registrar)
            ->post($this->promoteRoute($student), ['graduation_year' => 2026]);

        $response->assertRedirect(route('registrar.enrollments.index'));
        $response->assertSessionHas('success');

        // Role must have flipped
        $student->refresh();
        $this->assertTrue($student->hasRole('alumni'));
        $this->assertFalse($student->hasRole('student'));

        // graduation_year persisted
        $profile = StudentProfile::where('user_id', $student->id)->first();
        $this->assertNotNull($profile);
        $this->assertSame(2026, $profile->graduation_year);
    }

    /** @test */
    public function admin_can_also_promote_a_student_to_alumni()
    {
        $admin   = $this->makeUserWithRole('admin');
        $student = $this->paidStudentWithProfile();

        $this->actingAs($admin)
            ->post($this->promoteRoute($student), ['graduation_year' => 2025])
            ->assertRedirect(route('registrar.enrollments.index'))
            ->assertSessionHas('success');

        $student->refresh();
        $this->assertTrue($student->hasRole('alumni'));
    }

    /** @test */
    public function graduation_year_is_stored_on_existing_student_profile()
    {
        $registrar = $this->makeUserWithRole('registrar');
        $student   = $this->paidStudentWithProfile();

        $this->actingAs($registrar)
            ->post($this->promoteRoute($student), ['graduation_year' => 2027]);

        $profile = StudentProfile::where('user_id', $student->id)->first();
        $this->assertSame(2027, $profile->graduation_year);
    }

    // ── validation ────────────────────────────────────────────────────────

    /** @test */
    public function graduation_year_is_required()
    {
        $registrar = $this->makeUserWithRole('registrar');
        $student   = $this->paidStudentWithProfile();

        $this->actingAs($registrar)
            ->post($this->promoteRoute($student), [])
            ->assertSessionHasErrors('graduation_year');

        // Role unchanged
        $student->refresh();
        $this->assertTrue($student->hasRole('student'));
    }

    /** @test */
    public function graduation_year_must_be_an_integer()
    {
        $registrar = $this->makeUserWithRole('registrar');
        $student   = $this->paidStudentWithProfile();

        $this->actingAs($registrar)
            ->post($this->promoteRoute($student), ['graduation_year' => 'twenty-twenty-six'])
            ->assertSessionHasErrors('graduation_year');
    }

    /** @test */
    public function graduation_year_cannot_be_before_1990()
    {
        $registrar = $this->makeUserWithRole('registrar');
        $student   = $this->paidStudentWithProfile();

        $this->actingAs($registrar)
            ->post($this->promoteRoute($student), ['graduation_year' => 1989])
            ->assertSessionHasErrors('graduation_year');
    }

    /** @test */
    public function graduation_year_cannot_be_more_than_one_year_in_the_future()
    {
        $registrar = $this->makeUserWithRole('registrar');
        $student   = $this->paidStudentWithProfile();

        $this->actingAs($registrar)
            ->post($this->promoteRoute($student), ['graduation_year' => date('Y') + 2])
            ->assertSessionHasErrors('graduation_year');
    }

    // ── role guard ────────────────────────────────────────────────────────

    /** @test */
    public function cannot_promote_a_user_who_is_already_alumni()
    {
        $registrar = $this->makeUserWithRole('registrar');
        $alumni    = $this->makeUserWithRole('alumni');

        $response = $this->actingAs($registrar)
            ->post($this->promoteRoute($alumni), ['graduation_year' => 2026]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Still alumni, not broken
        $alumni->refresh();
        $this->assertTrue($alumni->hasRole('alumni'));
    }

    /** @test */
    public function cannot_promote_a_cashier_or_registrar_via_this_endpoint()
    {
        $registrar = $this->makeUserWithRole('registrar');
        $cashier   = $this->makeUserWithRole('cashier');

        $response = $this->actingAs($registrar)
            ->post($this->promoteRoute($cashier), ['graduation_year' => 2026]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $cashier->refresh();
        $this->assertTrue($cashier->hasRole('cashier'));
        $this->assertFalse($cashier->hasRole('alumni'));
    }

    // ── access control ────────────────────────────────────────────────────

    /** @test */
    public function a_student_cannot_access_the_promote_endpoint()
    {
        $actor   = $this->makeUserWithRole('student');
        $student = $this->paidStudentWithProfile();

        $this->actingAs($actor)
            ->post($this->promoteRoute($student), ['graduation_year' => 2026])
            ->assertForbidden();

        $student->refresh();
        $this->assertTrue($student->hasRole('student'));
    }

    /** @test */
    public function a_cashier_cannot_access_the_promote_endpoint()
    {
        $cashier = $this->makeUserWithRole('cashier');
        $student = $this->paidStudentWithProfile();

        $this->actingAs($cashier)
            ->post($this->promoteRoute($student), ['graduation_year' => 2026])
            ->assertForbidden();
    }

    /** @test */
    public function unauthenticated_users_are_redirected_to_login()
    {
        $student = $this->paidStudentWithProfile();

        $this->post($this->promoteRoute($student), ['graduation_year' => 2026])
            ->assertRedirect(route('login'));
    }
}
