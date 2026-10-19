<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\SeedsRoles;
use Tests\TestCase;

class StudentGcashPaymentTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('local');
    }

    /** @test */
    public function a_student_can_submit_a_gcash_proof_for_their_own_approved_unpaid_enrollment()
    {
        $student    = $this->makeUserWithRole('new_applicant');
        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'status'  => 'approved',
            'is_paid' => false,
        ]);

        $response = $this->actingAs($student)->post(
            route('portal.enrollment.payment.gcash', $enrollment),
            [
                'reference_number' => '1234567890',
                'proof_of_payment' => UploadedFile::fake()->image('proof.jpg'),
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('payments', [
            'enrollment_id'     => $enrollment->id,
            'payment_method'    => 'gcash',
            'reference_number'  => '1234567890',
            'status'            => 'pending',
        ]);

        // Status must always be hardcoded server-side, never trusted from
        // client input — assert the record really is pending, not
        // whatever the request could have tried to smuggle in.
        $payment = Payment::where('enrollment_id', $enrollment->id)->first();
        $this->assertNull($payment->processed_by);
        $this->assertNull($payment->verified_by);
        $this->assertNull($payment->receipt_no);
    }

    /** @test */
    public function a_student_cannot_submit_gcash_payment_for_someone_elses_enrollment()
    {
        $student       = $this->makeUserWithRole('new_applicant');
        $otherStudent  = $this->makeUserWithRole('new_applicant');
        $enrollment    = Enrollment::factory()->create([
            'user_id' => $otherStudent->id,
            'status'  => 'approved',
            'is_paid' => false,
        ]);

        $this->actingAs($student)->post(
            route('portal.enrollment.payment.gcash', $enrollment),
            [
                'reference_number' => '1234567890',
                'proof_of_payment' => UploadedFile::fake()->image('proof.jpg'),
            ]
        )->assertForbidden();

        $this->assertDatabaseMissing('payments', ['enrollment_id' => $enrollment->id]);
    }

    /** @test */
    public function a_student_cannot_view_someone_elses_payment_proof()
    {
        $student      = $this->makeUserWithRole('new_applicant');
        $otherStudent = $this->makeUserWithRole('new_applicant');
        $enrollment   = Enrollment::factory()->create(['user_id' => $otherStudent->id]);
        $payment      = Payment::factory()->gcashPending()->create(['enrollment_id' => $enrollment->id]);

        $this->actingAs($student)
            ->get(route('portal.enrollment.payment.proof', $payment))
            ->assertForbidden();
    }

    /** @test */
    public function submitting_gcash_payment_twice_while_one_is_pending_is_blocked()
    {
        $student    = $this->makeUserWithRole('new_applicant');
        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'status'  => 'approved',
            'is_paid' => false,
        ]);
        Payment::factory()->gcashPending()->create(['enrollment_id' => $enrollment->id]);

        $response = $this->actingAs($student)->post(
            route('portal.enrollment.payment.gcash', $enrollment),
            [
                'reference_number' => '0987654321',
                'proof_of_payment' => UploadedFile::fake()->image('proof2.jpg'),
            ]
        );

        $response->assertSessionHas('error');
        $this->assertSame(1, Payment::where('enrollment_id', $enrollment->id)->count());
    }

    /** @test */
    public function a_rejected_gcash_attempt_can_be_resubmitted()
    {
        $student = $this->makeUserWithRole('new_applicant');
        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'status' => 'approved',
            'is_paid' => false,
        ]);
        Payment::factory()->rejected()->create(['enrollment_id' => $enrollment->id]);

        $this->actingAs($student)->post(route('portal.enrollment.payment.gcash', $enrollment), [
            'reference_number' => '1234567890',
            'proof_of_payment' => UploadedFile::fake()->image('retry.jpg'),
        ])->assertSessionHas('success');

        $this->assertSame(2, Payment::where('enrollment_id', $enrollment->id)->count());
        $this->assertSame('pending', Payment::where('enrollment_id', $enrollment->id)->latest('id')->first()->status);
    }

    /** @test */
    public function gcash_submission_requires_a_reference_number_and_a_valid_proof_file()
    {
        $student    = $this->makeUserWithRole('new_applicant');
        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'status'  => 'approved',
            'is_paid' => false,
        ]);

        $this->actingAs($student)
            ->post(route('portal.enrollment.payment.gcash', $enrollment), [])
            ->assertSessionHasErrors(['reference_number', 'proof_of_payment']);

        $this->assertDatabaseMissing('payments', ['enrollment_id' => $enrollment->id]);
    }

    /** @test */
    public function a_student_cannot_pay_for_an_enrollment_that_is_not_yet_approved()
    {
        $student    = $this->makeUserWithRole('new_applicant');
        $enrollment = Enrollment::factory()->pending()->create(['user_id' => $student->id]);

        $response = $this->actingAs($student)->post(
            route('portal.enrollment.payment.gcash', $enrollment),
            [
                'reference_number' => '1234567890',
                'proof_of_payment' => UploadedFile::fake()->image('proof.jpg'),
            ]
        );

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('payments', ['enrollment_id' => $enrollment->id]);
    }
}
