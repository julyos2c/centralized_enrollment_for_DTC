<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\PolicyAcknowledgment;
use App\Models\Program;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Tests\SeedsRoles;
use Tests\TestCase;

class LegalDocumentsTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Mail::fake();
        config(['services.application_submission.per_ip_per_hour' => 100]);
    }

    public function test_public_pages_show_complete_versioned_documents(): void
    {
        $this->get(route('legal.terms'))->assertOk()
            ->assertSee('Terms and Conditions — DTC Online Admission and Enrollment Portal')
            ->assertSee('Version')->assertSee('1.0')
            ->assertSee('Pending approval by DTC management and the Data Protection Officer')
            ->assertSee('November 10, 2026')
            ->assertSee('21. Contact and support')
            ->assertSee('Submitting an application does not guarantee admission.');

        $this->get(route('legal.privacy'))->assertOk()
            ->assertSee('Privacy Notice — DTC Online Admission and Enrollment Portal')
            ->assertSee('Republic Act No. 10173')
            ->assertSee('to lodge a complaint', false)
            ->assertSee('Applicants who are minors')
            ->assertSee('does not use advertising cookies or third-party analytics')
            ->assertSee('retention schedule is not yet published');

        config(['legal.terms.effective_date' => '2026-12-01']);
        $this->get(route('legal.terms'))->assertSee('December 1, 2026');
    }

    public function test_privacy_contact_comes_from_settings_and_is_never_invented(): void
    {
        $this->get(route('legal.privacy'))
            ->assertSee('will be published here')
            ->assertDontSee('mailto:');

        $admin = $this->makeUserWithRole('admin');
        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'institution_name' => 'Danao Technological College', 'system_name' => 'DTC Nexus',
            'default_language' => 'en', 'default_timezone' => 'Asia/Manila', 'date_format' => 'm/d/Y',
            'time_format' => '12', 'items_per_page' => '10',
            'privacy_officer_name' => 'Data Protection Officer', 'privacy_officer_email' => 'dpo@example.test',
        ])->assertRedirect();
        $this->post(route('logout'));

        $this->get(route('legal.privacy'))
            ->assertSee('Data Protection Officer')
            ->assertSee('mailto:dpo@example.test', false)
            ->assertDontSee('will be published here');
    }

    public function test_form_shows_documents_in_modals_with_separate_unticked_boxes(): void
    {
        $html = $this->get(route('public.application.create'))->assertOk()->getContent();

        $this->assertStringContainsString('id="privacyModal"', $html);
        $this->assertStringContainsString('id="termsModal"', $html);
        $this->assertStringContainsString('19. Limitation of liability', $html);
        $this->assertStringContainsString('9. Your rights', $html);
        $this->assertStringNotContainsString('checked', $this->between($html, 'id="applicationAcknowledgement"', '</fieldset>'));
        $this->assertStringContainsString('id="sensitiveConsentField"  hidden', $html);
    }

    public function test_agreement_and_acknowledgment_are_required_and_recorded_separately(): void
    {
        $this->assertRejected(['ack_enrollment' => null], 'ack_enrollment');
        $this->assertRejected(['acknowledge_privacy' => null], 'acknowledge_privacy');

        $application = $this->submit([]);
        $records = PolicyAcknowledgment::where('application_id', $application->id)->orderBy('id')->get();
        $this->assertSame(['terms', 'privacy'], $records->pluck('document')->all());
        $this->assertSame(['agreement', 'acknowledgment'], $records->pluck('kind')->all());
        $this->assertSame(['1.0', '1.0'], $records->pluck('version')->all());
        $this->assertNotNull($records[0]->acknowledged_at);
        $this->assertSame('127.0.0.1', $records[0]->ip_address);
    }

    public function test_optional_sensitive_details_need_separate_consent(): void
    {
        $this->assertRejected(['religion' => 'Roman Catholic'], 'consent_sensitive');
        $this->assertRejected(['disability' => 'Low vision', 'pwd_id' => 'PWD-1'], 'consent_sensitive');

        $withConsent = $this->submit(['religion' => 'Roman Catholic', 'consent_sensitive' => '1']);
        $this->assertSame('Roman Catholic', $withConsent->religion);
        $this->assertTrue(PolicyAcknowledgment::where('application_id', $withConsent->id)->where('document', 'sensitive_consent')->where('kind', 'consent')->exists());

        // A ticked box without any sensitive details records no consent.
        $without = $this->submit(['consent_sensitive' => '1']);
        $this->assertFalse(PolicyAcknowledgment::where('application_id', $without->id)->where('document', 'sensitive_consent')->exists());
    }

    public function test_acknowledgments_are_never_overwritten(): void
    {
        $application = $this->submit([]);
        config(['legal.terms.version' => '2.0']);
        $second = $this->submit([]);

        $this->assertSame('1.0', PolicyAcknowledgment::where('application_id', $application->id)->where('document', 'terms')->value('version'));
        $this->assertSame('2.0', PolicyAcknowledgment::where('application_id', $second->id)->where('document', 'terms')->value('version'));

        $this->expectException(LogicException::class);
        PolicyAcknowledgment::first()->update(['version' => '9.9']);
    }

    private function payload(array $overrides): array
    {
        $this->get(route('public.application.create'))->assertOk();

        return array_filter(array_merge([
            'submission_token' => $this->app['session.store']->get('public_application_submission_token'),
            'academic_status' => 'new_student', 'program_id' => Program::factory()->create()->id,
            'first_name' => 'Legal', 'last_name' => 'Applicant', 'gender' => 'female',
            'birthdate' => '2007-01-01', 'email' => uniqid('legal-', true) . '@example.test',
            'ack_enrollment' => '1', 'acknowledge_privacy' => '1',
        ], $overrides), fn ($value) => $value !== null);
    }

    private function submit(array $overrides): Application
    {
        $payload = $this->payload($overrides);
        $this->post(route('public.application.store'), $payload)
            ->assertSessionHasNoErrors()->assertRedirect(route('public.application.success'));

        return Application::where('email', $payload['email'])->firstOrFail();
    }

    private function assertRejected(array $overrides, string $field): void
    {
        $payload = $this->payload($overrides);
        $this->from(route('public.application.create'))->post(route('public.application.store'), $payload)
            ->assertSessionHasErrors($field);
        $this->assertDatabaseMissing('applications', ['email' => $payload['email']]);
    }

    private function between(string $html, string $from, string $to): string
    {
        $start = strpos($html, $from);

        return substr($html, $start, strpos($html, $to, $start) - $start);
    }
}
