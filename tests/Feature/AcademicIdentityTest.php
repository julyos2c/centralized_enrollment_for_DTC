<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\StudentProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

/**
 * Tests for the enrolled_ay / graduation_year feature on student_profiles.
 *
 * Business rules under test:
 *   - enrolled_ay is copied from enrollments.school_year when the cashier
 *     confirms a walk-in payment (PaymentController::store).
 *   - enrolled_ay is copied from enrollments.school_year when the cashier
 *     verifies a GCash payment (PaymentController::verify).
 *   - enrolled_ay is null on a fresh profile that has not yet been created
 *     (i.e. before any payment).
 *   - graduation_year is nullable and can be updated directly by the
 *     Registrar (no dedicated endpoint yet — tested at the model level
 *     until the Registrar promotion UI is built).
 *   - Student dashboard renders the enrolled_ay pill when the field is set.
 *   - Alumni dashboard renders the Batch YYYY pill when graduation_year is set.
 */
class AcademicIdentityTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Walk-in payment path
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function walk_in_payment_sets_enrolled_ay_from_enrollment_school_year()
    {
        $cashier    = $this->makeUserWithRole('cashier');
        $enrollment = Enrollment::factory()->create([
            'status'      => 'approved',
            'is_paid'     => false,
            'school_year' => '2025-2026',
        ]);

        $this->actingAs($cashier)
            ->post(route('cashier.payments.store', $enrollment));

        $profile = StudentProfile::where('user_id', $enrollment->user_id)->first();

        $this->assertNotNull($profile, 'StudentProfile must be created on payment confirmation.');
        $this->assertSame('2025-2026', $profile->enrolled_ay);
    }

    /** @test */
    public function walk_in_payment_enrolled_ay_is_null_when_enrollment_has_no_school_year()
    {
        $cashier    = $this->makeUserWithRole('cashier');
        $enrollment = Enrollment::factory()->create([
            'status'      => 'approved',
            'is_paid'     => false,
            'school_year' => null,
        ]);

        $this->actingAs($cashier)
            ->post(route('cashier.payments.store', $enrollment));

        $profile = StudentProfile::where('user_id', $enrollment->user_id)->first();
        $this->assertNotNull($profile);
        $this->assertNull($profile->enrolled_ay);
    }

    // ─────────────────────────────────────────────────────────────────────
    // GCash verification path
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function gcash_verification_sets_enrolled_ay_from_enrollment_school_year()
    {
        $cashier    = $this->makeUserWithRole('cashier');
        $enrollment = Enrollment::factory()->create([
            'status'      => 'approved',
            'is_paid'     => false,
            'school_year' => '2026-2027',
        ]);
        $payment = Payment::factory()->gcashPending()->create([
            'enrollment_id' => $enrollment->id,
        ]);

        $this->actingAs($cashier)
            ->post(route('cashier.payments.verify', $payment));

        $profile = StudentProfile::where('user_id', $enrollment->user_id)->first();

        $this->assertNotNull($profile);
        $this->assertSame('2026-2027', $profile->enrolled_ay);
    }

    /** @test */
    public function enrolled_ay_is_not_overwritten_if_student_profile_already_exists()
    {
        // A student who already has a profile (e.g. re-pays after a rejected
        // GCash attempt) must not have their enrolled_ay silently overwritten.
        $cashier    = $this->makeUserWithRole('cashier');
        $enrollment = Enrollment::factory()->create([
            'status'      => 'approved',
            'is_paid'     => false,
            'school_year' => '2026-2027',
        ]);

        // Pre-create a profile with the original AY
        StudentProfile::create([
            'user_id'        => $enrollment->user_id,
            'student_number' => '2025-00001',
            'admission_type' => 'new_student',
            'program_id'     => $enrollment->program_id,
            'enrolled_ay'    => '2025-2026',   // original — must not change
        ]);

        // Now confirm payment (e.g. a walk-in after profile already existed)
        $this->actingAs($cashier)
            ->post(route('cashier.payments.store', $enrollment));

        $profile = StudentProfile::where('user_id', $enrollment->user_id)->first();

        // Profile existed → createStudentProfileIfMissing bailed out →
        // enrolled_ay is unchanged
        $this->assertSame('2025-2026', $profile->enrolled_ay);
    }

    // ─────────────────────────────────────────────────────────────────────
    // graduation_year — model-level tests
    // (Registrar UI TBD — these confirm the column and cast are correct)
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function graduation_year_can_be_stored_and_retrieved_as_an_integer()
    {
        $profile = StudentProfile::create([
            'user_id'         => \App\Models\User::factory()->create()->id,
            'student_number'  => '2025-00099',
            'admission_type'  => 'new_student',
            'graduation_year' => 2026,
        ]);

        $this->assertSame(2026, $profile->fresh()->graduation_year);
    }

    /** @test */
    public function graduation_year_is_nullable_by_default()
    {
        $profile = StudentProfile::create([
            'user_id'        => \App\Models\User::factory()->create()->id,
            'student_number' => '2025-00098',
            'admission_type' => 'new_student',
        ]);

        $this->assertNull($profile->fresh()->graduation_year);
    }

    /** @test */
    public function graduation_year_can_be_set_after_creation()
    {
        $profile = StudentProfile::create([
            'user_id'        => \App\Models\User::factory()->create()->id,
            'student_number' => '2025-00097',
            'admission_type' => 'new_student',
        ]);

        $profile->update(['graduation_year' => 2027]);

        $this->assertSame(2027, $profile->fresh()->graduation_year);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Dashboard display — student
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function student_dashboard_shows_enrolled_ay_pill_when_profile_has_ay()
    {
        $student = $this->makeUserWithRole('student');
        StudentProfile::create([
            'user_id'        => $student->id,
            'student_number' => '2026-00001',
            'admission_type' => 'new_student',
            'enrolled_ay'    => '2025-2026',
        ]);

        $response = $this->actingAs($student)
            ->get(route('portal.dashboard'));

        $response->assertOk();
        $response->assertSee('2025-2026');
        $response->assertSee('Enrolled AY');
    }

    /** @test */
    public function student_dashboard_shows_no_ay_pill_when_enrolled_ay_is_null()
    {
        $student = $this->makeUserWithRole('student');
        // No student profile created → $studentProfile is null in the view

        $response = $this->actingAs($student)
            ->get(route('portal.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Enrolled AY');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Dashboard display — alumni
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function alumni_dashboard_shows_batch_year_when_graduation_year_is_set()
    {
        $alumni = $this->makeUserWithRole('alumni');
        StudentProfile::create([
            'user_id'         => $alumni->id,
            'student_number'  => '2022-00001',
            'admission_type'  => 'new_student',
            'graduation_year' => 2026,
        ]);

        $response = $this->actingAs($alumni)
            ->get(route('portal.dashboard'));

        $response->assertOk();
        $response->assertSee('Batch');
        $response->assertSee('2026');
    }

    /** @test */
    public function alumni_dashboard_shows_no_batch_pill_when_graduation_year_is_null()
    {
        $alumni = $this->makeUserWithRole('alumni');
        // No student profile

        $response = $this->actingAs($alumni)
            ->get(route('portal.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Batch');
    }
}
