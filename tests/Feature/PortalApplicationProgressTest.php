<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class PortalApplicationProgressTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    public function test_signed_in_approved_applicant_gets_enrollment_action_without_activation_instructions(): void
    {
        $this->seedRoles();
        $user = $this->makeUserWithRole('new_applicant');
        Application::create(['reference_no' => 'APP-PROGRESS-TEST', 'user_id' => $user->id,
            'program_id' => Program::factory()->create()->id, 'first_name' => 'Test', 'last_name' => 'Applicant',
            'email' => $user->email, 'gender' => 'female', 'birthdate' => '2005-01-01',
            'academic_status' => 'transferee', 'status' => 'approved', 'reviewed_at' => now()]);
        $this->actingAs($user)->get(route('portal.application.show'))->assertOk()
            ->assertViewHas('nextActionUrl', route('portal.enrollment.create'))
            ->assertSee('Start enrollment')->assertSee('The Registrar reviewed your application.')
            ->assertDontSee('Use the account activation email')->assertDontSee('Activate your account')
            ->assertDontSee('No corrections have been requested.');
    }

    public function test_unapproved_applications_do_not_offer_an_enrollment_action_and_keep_feedback_escaped(): void
    {
        $this->seedRoles();
        $user = $this->makeUserWithRole('new_applicant');
        $application = Application::create(['reference_no' => 'APP-PROGRESS-OTHER', 'user_id' => $user->id,
            'program_id' => Program::factory()->create()->id, 'first_name' => 'Test', 'last_name' => 'Applicant',
            'email' => $user->email, 'gender' => 'female', 'birthdate' => '2005-01-01',
            'academic_status' => 'new_student', 'status' => 'revision_required', 'remarks' => '<script>unsafe()</script>', 'reviewed_at' => now()]);
        foreach (['revision_required', 'rejected', 'under_review'] as $status) {
            $application->update(['status' => $status]);
            $this->actingAs($user)->get(route('portal.application.show'))->assertOk()
                ->assertViewHas('nextActionUrl', null)->assertDontSee('Start enrollment')
                ->assertDontSee('<script>unsafe()</script>', false)->assertSee('&lt;script&gt;unsafe()&lt;/script&gt;', false);
        }
    }
}
