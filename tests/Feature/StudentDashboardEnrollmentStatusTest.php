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

    /** @dataProvider calendarDates */
    public function test_calendar_progress_uses_configured_dates(string $start, string $end, string $today, string $status, ?int $progress, ?int $daysLeft): void
    {
        $student = $this->makeUserWithRole('student');
        \App\Models\Setting::set('default_timezone', 'Asia/Manila');
        \App\Models\Setting::set('semester_starts_on', $start);
        \App\Models\Setting::set('semester_ends_on', $end);
        $this->travelTo(\Carbon\Carbon::parse($today, 'Asia/Manila'));

        try {
            $response = $this->actingAs($student)->get(route('portal.dashboard'));
            $response->assertOk()
                ->assertViewHas('termStatus', $status)
                ->assertViewHas('termProgress', $progress)
                ->assertViewHas('termDaysLeft', $daysLeft);
            if ($progress === null) {
                $response->assertDontSee('role="progressbar"', false)
                    ->assertSee('Semester dates are unavailable.');
            } else {
                $response->assertSee('aria-valuenow="'.$progress.'"', false);
            }
        } finally {
            $this->travelBack();
        }
    }

    public static function calendarDates(): array
    {
        return [
            'midterm' => ['2026-10-01', '2026-10-10', '2026-10-06 12:00:00', 'in_progress', 50, 4],
            'upcoming' => ['2026-10-01', '2026-10-10', '2026-09-29 12:00:00', 'upcoming', 0, 11],
            'ended' => ['2026-10-01', '2026-10-10', '2026-10-11 12:00:00', 'ended', 100, 0],
            'last day' => ['2026-10-01', '2026-10-10', '2026-10-10 12:00:00', 'in_progress', 90, 0],
            'reversed dates' => ['2026-10-10', '2026-10-01', '2026-10-06 12:00:00', 'dates_missing', null, null],
            'invalid dates' => ['invalid', '2026-10-10', '2026-10-06 12:00:00', 'dates_missing', null, null],
        ];
    }

    public function test_new_semester_starts_a_new_journey_instead_of_showing_last_terms_completion(): void
    {
        $student = $this->makeUserWithRole('student');
        \App\Models\Setting::set('current_school_year', '2026-2027');
        \App\Models\Setting::set('current_semester', '2nd');
        Enrollment::factory()->paid()->create([
            'user_id' => $student->id, 'school_year' => '2026-2027', 'semester' => 1,
        ]);

        $response = $this->actingAs($student)->get(route('portal.dashboard'));
        $response->assertOk()
            ->assertViewHas('latestEnrollment', null)
            ->assertViewHas('subjects', fn ($subjects) => $subjects->isEmpty())
            ->assertSee('Enrollment progress')
            ->assertSee('2nd semester')
            ->assertSee('0 of 5 complete')
            ->assertSee('Current Step: Submit Application')
            ->assertDontSee('First enrollment progress')
            ->assertDontSee('All steps completed! You are officially enrolled.');
    }

    public function test_journey_selects_the_configured_term_even_when_an_old_term_record_was_created_later(): void
    {
        $student = $this->makeUserWithRole('student');
        \App\Models\Setting::set('current_school_year', '2027-2028');
        \App\Models\Setting::set('current_semester', '1st');
        $current = Enrollment::factory()->pending()->create([
            'user_id' => $student->id, 'school_year' => '2027-2028', 'semester' => 1,
            'created_at' => now()->subDay(),
        ]);
        Enrollment::factory()->paid()->create([
            'user_id' => $student->id, 'school_year' => '2026-2027', 'semester' => 1,
        ]);

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertViewHas('latestEnrollment', fn ($enrollment) => $enrollment->is($current))
            ->assertViewHas('currentTermEnrollment', fn ($enrollment) => $enrollment->is($current))
            ->assertSee('1 of 5 complete')
            ->assertSee('Await Registrar Review')
            ->assertDontSee('All steps completed! You are officially enrolled.');
    }

    public function test_summer_enrollment_can_complete_its_own_journey(): void
    {
        $student = $this->makeUserWithRole('student');
        \App\Models\Setting::set('current_school_year', '2026-2027');
        \App\Models\Setting::set('current_semester', 'Summer');
        Enrollment::factory()->paid()->create([
            'user_id' => $student->id, 'school_year' => '2026-2027', 'semester' => 3,
        ]);

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Summer semester')
            ->assertSee('5 of 5 complete')
            ->assertSee('All steps completed! You are officially enrolled.');
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
            ->assertSee('Your payment is confirmed.')
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

    public function test_student_dashboard_shows_a_subject_preview_with_a_view_all_footer(): void
    {
        $student = $this->makeUserWithRole('student');
        $program = Program::factory()->create();
        $subjects = collect(range(1, 5))->map(fn ($index) => CourseSubject::factory()->for($program)->create([
            'subject_code' => 'SUBJ'.$index,
            'subject_name' => 'Subject name '.$index,
            'units' => 3,
        ]));
        Enrollment::factory()->paid()->create([
            'user_id' => $student->id,
            'program_id' => $program->id,
            'subject_ids' => $subjects->pluck('id')->all(),
        ]);

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('5 subjects · 15 units')
            ->assertSee('Subject name 1')
            ->assertSee('Subject name 2')
            ->assertSee('Subject name 3')
            ->assertDontSee('Subject name 4')
            ->assertDontSee('Subject name 5')
            ->assertSee('Showing 3 of 5 subjects')
            ->assertSee('View all subjects')
            ->assertSee('href="'.route('portal.cor.show').'"', false);
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
