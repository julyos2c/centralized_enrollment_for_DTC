<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Program;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class ApplicationProgressTimelineTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_application_approval_alone_does_not_mark_enrollment_complete(): void
    {
        $application = $this->makeApplication('approved');

        $this->assertFalse($application->isEnrollmentComplete(null));
        $this->assertSame('current', $application->getProgressTimeline()[4]['status']);
        $this->assertStringContainsString('enrollment request', $application->getProgressTimeline()[4]['description']);
    }

    public function test_enrollment_requires_approval_payment_verification_and_student_profile_to_be_complete(): void
    {
        $application = $this->makeApplication('approved');
        $user = User::factory()->create();
        $application->user()->associate($user);
        $application->save();

        $enrollment = Enrollment::factory()->create([
            'user_id' => $user->id,
            'program_id' => $application->program_id,
            'status' => 'approved',
            'is_paid' => true,
            'school_year' => '2026-2027',
        ]);
        $payment = Payment::factory()->create([
            'enrollment_id' => $enrollment->id,
            'status' => 'verified',
            'verified_at' => now(),
            'processed_by' => $user->id,
            'verified_by' => $user->id,
        ]);

        $this->assertFalse($application->isEnrollmentComplete($enrollment->fresh('payment')));

        StudentProfile::create([
            'user_id' => $user->id,
            'program_id' => $application->program_id,
            'enrolled_ay' => '2026-2027',
        ]);
        $application->load('user.studentProfile');
        $enrollment->load('payment');

        $this->assertTrue($application->isEnrollmentComplete($enrollment));
        $this->assertSame('completed', $application->getProgressTimeline($enrollment)[4]['status']);
        $this->assertSame($payment->verified_at->toIso8601String(), $application->getProgressTimeline($enrollment)[4]['timestamp']->toIso8601String());
    }

    public function test_rejected_enrollment_is_an_action_state_not_completion(): void
    {
        $application = $this->makeApplication('approved');
        $user = User::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'user_id' => $user->id,
            'program_id' => $application->program_id,
            'status' => 'rejected',
        ]);

        $this->assertFalse($application->isEnrollmentComplete($enrollment));
        $this->assertSame('action_required', $application->getProgressTimeline($enrollment)[4]['status']);
    }

    private function makeApplication(string $status): Application
    {
        $program = Program::factory()->create();

        return Application::create([
            'reference_no' => 'APP-' . strtoupper(substr(md5(uniqid('', true)), 0, 12)),
            'program_id' => $program->id,
            'academic_status' => 'new_student',
            'first_name' => 'Timeline',
            'last_name' => 'Applicant',
            'gender' => 'female',
            'birthdate' => '2005-01-01',
            'email' => 'timeline-' . strtolower(substr(md5(uniqid('', true)), 0, 8)) . '@example.test',
            'status' => $status,
        ]);
    }
}
