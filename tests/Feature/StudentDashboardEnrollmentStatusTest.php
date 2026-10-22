<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\CourseSubject;
use App\Models\Application;
use App\Models\DocumentRequest;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\SeedsRoles;
use Tests\TestCase;

class StudentDashboardEnrollmentStatusTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_rejected_enrollment_displays_feedback_and_supported_resubmission_action(): void
    {
        $student = $this->makeUserWithRole('student');
        Enrollment::factory()->create([
            'user_id' => $student->id,
            'status' => 'rejected',
            'remarks' => 'Please correct your selected subjects.',
            'is_paid' => false,
        ]);

        $response = $this->actingAs($student)->get(route('portal.dashboard'));

        $response->assertOk()
            ->assertSee('Enrollment Status')
            ->assertSee('Please correct your selected subjects.')
            ->assertSee('Correct and Resubmit')
            ->assertSee(route('portal.enrollment.create'))
            ->assertSee('data-step-state="rejected"', false);

        $this->assertSame(1, substr_count($response->getContent(), 'data-step-state="done"'));
    }

    public function test_rejected_enrollment_without_feedback_has_neutral_recovery_guidance(): void
    {
        $student = $this->makeUserWithRole('student');
        Enrollment::factory()->create([
            'user_id' => $student->id,
            'status' => 'rejected',
            'remarks' => null,
            'is_paid' => false,
        ]);

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('No additional feedback was provided.')
            ->assertSee('Review Enrollment History');
    }

    public function test_student_without_an_application_is_prompted_to_start_and_payment_is_not_applicable(): void
    {
        $student = $this->makeUserWithRole('student');

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Not Started')
            ->assertSee('Not Required')
            ->assertSee('Submit Application')
            ->assertSee('Current Step: Submit Application')
            ->assertSee(route('portal.enrollment.create'));
    }

    public function test_pending_enrollment_is_shown_as_under_registrar_review(): void
    {
        $student = $this->makeUserWithRole('student');
        Enrollment::factory()->pending()->create([
            'user_id' => $student->id,
            'is_paid' => false,
        ]);

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('In Progress')
            ->assertSee('Await Registrar Review')
            ->assertSee('data-step-state="active"', false)
            ->assertDontSee('All steps completed! You are officially enrolled.');
    }

    public function test_rejected_payment_is_not_shown_as_confirmed_or_enrolled(): void
    {
        $student = $this->makeUserWithRole('student');
        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'status' => 'approved',
            'is_paid' => false,
        ]);
        Payment::factory()->rejected()->create(['enrollment_id' => $enrollment->id]);

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Proof Rejected')
            ->assertSee('Your payment proof was rejected.')
            ->assertDontSee('All steps completed! You are officially enrolled.');
    }

    public function test_approved_enrollment_without_a_payment_record_shows_a_payment_action(): void
    {
        $student = $this->makeUserWithRole('student');
        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'status' => 'approved',
            'is_paid' => false,
        ]);

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Awaiting Payment')
            ->assertSee(route('portal.enrollment.payment-info', $enrollment))
            ->assertSee('Pay Enrollment Fee')
            ->assertDontSee('All steps completed! You are officially enrolled.');
    }

    public function test_confirmed_payment_is_the_only_approved_enrollment_shown_as_enrolled(): void
    {
        $student = $this->makeUserWithRole('student');
        $enrollment = Enrollment::factory()->paid()->create(['user_id' => $student->id]);
        Payment::factory()->create(['enrollment_id' => $enrollment->id]);

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Enrolled')
            ->assertSee('Confirmed')
            ->assertSee('All steps completed! You are officially enrolled.');
    }

    public function test_student_dashboard_only_displays_the_signed_in_students_enrollment_feedback(): void
    {
        $student = $this->makeUserWithRole('student');
        $otherStudent = $this->makeUserWithRole('student');
        Enrollment::factory()->create([
            'user_id' => $otherStudent->id,
            'status' => 'rejected',
            'remarks' => 'Private feedback belonging to another student.',
            'is_paid' => false,
        ]);

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertDontSee('Private feedback belonging to another student.')
            ->assertSee('Not Started');

        $this->actingAs($student)->get(route('portal.enrollment.index'))
            ->assertOk()
            ->assertDontSee('Private feedback belonging to another student.');
    }

    public function test_approved_unpaid_enrollment_is_not_presented_as_paid_or_enrolled(): void
    {
        $student = $this->makeUserWithRole('student');
        $program = Program::factory()->create();
        $subject = CourseSubject::factory()->for($program)->create();
        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'program_id' => $program->id,
            'subject_ids' => [$subject->id],
            'status' => 'approved',
            'is_paid' => false,
        ]);
        Payment::factory()->gcashPending()->create(['enrollment_id' => $enrollment->id]);

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Payment Verification')
            ->assertSee('Your payment proof is awaiting Cashier verification.')
            ->assertDontSee('All steps completed! You are officially enrolled.');
    }

    public function test_student_avatar_links_to_the_existing_profile_settings_page(): void
    {
        $student = $this->makeUserWithRole('student');

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('href="' . route('portal.profile.show') . '"', false)
            ->assertSee('aria-label="Open profile settings"', false)
            ->assertSee('aria-label="Choose a new profile photo"', false)
            ->assertSee('data-dashboard-avatar-input', false)
            ->assertSee('action="' . route('portal.profile.update') . '"', false)
            ->assertSee('data-lucide="camera"', false);

        $this->actingAs($student)->get(route('portal.profile.show'))
            ->assertOk()
            ->assertSee('data-lucide="camera"', false)
            ->assertSee('aria-label="Change profile photo"', false);
    }

    public function test_dashboard_avatar_uses_the_saved_profile_photo_when_available(): void
    {
        $student = $this->makeUserWithRole('student', [
            'avatar' => 'avatars/john.jpg',
        ]);

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('src="' . Storage::disk('public')->url($student->avatar) . '"', false)
            ->assertDontSee('>J</div>', false);
    }

    public function test_alumni_and_new_applicant_can_crop_update_and_sync_their_dashboard_avatar(): void
    {
        Storage::fake('public');

        foreach (['new_applicant', 'alumni'] as $role) {
            $user = $this->makeUserWithRole($role);

            $this->actingAs($user)->get(route('portal.dashboard'))
                ->assertOk()
                ->assertSee('href="' . route('portal.profile.show') . '"', false)
                ->assertSee('data-avatar-picker-trigger', false)
                ->assertSee('id="dashboardAvatarCropModal"', false)
                ->assertSee('id="dashboardAvatarConfirmModal"', false)
                ->assertSee('js/dashboard-avatar.js');

            $this->actingAs($user)->get(route('portal.profile.show'))
                ->assertOk()
                ->assertSee('aria-label="Change profile photo"', false)
                ->assertSee('id="avatarCropModal"', false);

            $response = $this->actingAs($user)->post(route('portal.profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'return_to_dashboard' => '1',
                'avatar' => UploadedFile::fake()->image('avatar.jpg'),
            ]);

            $response->assertRedirect(route('portal.dashboard'));
            $this->assertNotNull($user->fresh()->avatar);
            Storage::disk('public')->assertExists($user->fresh()->avatar);
        }
    }

    public function test_new_applicant_dashboard_summary_shows_the_real_pending_application_status(): void
    {
        $applicant = $this->makeUserWithRole('new_applicant');
        $program = Program::factory()->create();
        Application::create([
            'reference_no' => 'DTC-TEST-APP-001',
            'program_id' => $program->id,
            'first_name' => 'Applicant',
            'last_name' => 'Test',
            'email' => $applicant->email,
            'status' => 'under_review',
            'user_id' => $applicant->id,
        ]);

        $this->actingAs($applicant)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('dtc-dashboard-overview', false)
            ->assertSee('Under Review')
            ->assertSee('Await Registrar Review')
            ->assertSee(route('portal.application.show'))
            ->assertDontSee('Not Found');
    }

    public function test_alumni_dashboard_summary_uses_real_document_request_data_and_next_action(): void
    {
        $alumnus = $this->makeUserWithRole('alumni');
        DocumentRequest::create([
            'user_id' => $alumnus->id,
            'document_type' => 'tor',
            'copies' => 1,
            'purpose' => 'Employment application',
            'status' => 'ready',
            'fee' => 100,
            'is_paid' => true,
            'payment_method' => 'walk_in',
            'payment_status' => 'verified',
            'amount_received' => 100,
        ]);

        $this->actingAs($alumnus)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('dtc-dashboard-overview', false)
            ->assertSee('Document requests')
            ->assertSee('Ready for pickup')
            ->assertSee('Pick up a ready document')
            ->assertSee('Transcript of Records')
            ->assertSee(route('portal.documents.index'));

        $this->actingAs($alumnus)->get(route('portal.documents.index'))
            ->assertOk()
            ->assertSee('dtc-dashboard-overview--four', false)
            ->assertSee('Total requests')
            ->assertSee('Ready for pickup')
            ->assertSee('Released')
            ->assertSee('Total fees')
            ->assertSee('Your requests')
            ->assertSee('Transcript of Records');
    }
}
