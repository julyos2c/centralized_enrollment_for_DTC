<?php

namespace Tests\Feature;

use App\Mail\PortalAccountInvitationMail;
use App\Models\AuditLog;
use App\Models\PortalAccountInvitation;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\PortalAccountProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\SeedsRoles;
use Tests\TestCase;

class PortalAccountProvisioningTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        config(['portal_accounts.invitation_expire_minutes' => 60]);
        Mail::fake();
    }

    private function record(string $status = 'active', array $attributes = []): StudentProfile
    {
        return StudentProfile::create(array_merge([
            'student_number' => 'DTC-'.mt_rand(10000, 99999),
            'first_name' => 'Mara', 'last_name' => 'Santos', 'email' => 'mara'.mt_rand(10000, 99999).'@example.test',
            'academic_status' => $status, 'admission_type' => 'imported',
        ], $attributes));
    }

    public function test_registrar_can_provision_student_with_pending_account_and_single_use_invitation(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $record = $this->record();

        $this->actingAs($registrar)->get(route('registrar.students.provision', $record))->assertOk();
        $this->actingAs($registrar)->post(route('registrar.students.provision.store', $record), ['confirm' => '1'])
            ->assertRedirect(route('registrar.students.show', $record))->assertSessionHas('success');

        $record->refresh();
        $account = $record->user;
        $invitation = $record->portalInvitation;
        $this->assertNotNull($account);
        $this->assertSame('pending', $account->status);
        $this->assertNull($account->email_verified_at);
        $this->assertTrue($account->hasRole('student'));
        $this->assertFalse($account->hasRole('admin'));
        $this->assertSame('pending', $invitation->status);
        $this->assertNotNull($invitation->delivered_at);
        $this->assertSame(64, strlen($invitation->token_hash));
        Mail::assertSent(PortalAccountInvitationMail::class, 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'portal_account.provisioned', 'user_id' => $registrar->id]);

        Mail::assertSent(PortalAccountInvitationMail::class, function ($mail) use ($account, $invitation) {
            $token = basename(parse_url($mail->activationUrl, PHP_URL_PATH));
            $this->assertSame($account->email, $mail->profile->email);
            $this->assertSame(rtrim((string) config('app.url'), '/').route('portal.activation.show', ['token' => $token], false), $mail->activationUrl);
            $this->assertSame(hash('sha256', $token), $invitation->token_hash);
            return true;
        });
    }

    public function test_alumni_record_receives_only_alumni_role(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $record = $this->record('alumni');
        $this->actingAs($registrar)->post(route('registrar.students.provision.store', $record), ['confirm' => '1'])->assertRedirect();
        $this->assertTrue($record->fresh()->user->hasRole('alumni'));
        $this->assertFalse($record->fresh()->user->hasRole('student'));
    }

    public function test_admin_can_provision_and_student_cannot_access_registrar_academic_records(): void
    {
        $admin = $this->makeUserWithRole('admin');
        $record = $this->record();

        $this->actingAs($admin)->post(route('registrar.students.provision.store', $record), ['confirm' => '1'])
            ->assertRedirect(route('registrar.students.show', $record));
        $this->assertTrue($record->fresh()->user->hasRole('student'));

        $student = $this->makeUserWithRole('student');
        $this->actingAs($student)->get(route('registrar.students.show', $record))->assertForbidden();
    }

    public function test_ineligible_records_and_existing_email_conflicts_are_blocked_without_linking(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $missingEmail = $this->record('active', ['email' => null]);
        $this->actingAs($registrar)->post(route('registrar.students.provision.store', $missingEmail), ['confirm' => '1'])
            ->assertSessionHasErrors('account');
        $this->assertNull($missingEmail->fresh()->user_id);

        $conflict = $this->record();
        User::factory()->create(['email' => $conflict->email]);
        $this->actingAs($registrar)->post(route('registrar.students.provision.store', $conflict), ['confirm' => '1'])
            ->assertSessionHasErrors('account');
        $this->assertNull($conflict->fresh()->user_id);
    }

    public function test_non_staff_roles_cannot_open_or_submit_provisioning(): void
    {
        $record = $this->record();
        foreach (['student', 'alumni', 'cashier'] as $role) {
            $this->actingAs($this->makeUserWithRole($role))
                ->get(route('registrar.students.provision', $record))->assertForbidden();
            $this->actingAs($this->makeUserWithRole($role))
                ->post(route('registrar.students.provision.store', $record), ['confirm' => '1'])->assertForbidden();
        }
        $this->assertNull($record->fresh()->user_id);
    }

    public function test_provisioning_service_enforces_staff_roles_without_route_middleware(): void
    {
        $student = $this->makeUserWithRole('student');
        $record = $this->record();

        try {
            app(PortalAccountProvisioningService::class)->provision($record, $student);
            $this->fail('A student must not provision accounts through direct service access.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertNull($record->fresh()->user_id);
        $this->assertDatabaseCount('portal_account_invitations', 0);
    }

    public function test_concurrent_resend_cannot_replace_a_token_while_delivery_is_in_progress(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $record = $this->record();
        $service = app(PortalAccountProvisioningService::class);
        $invitation = $service->provision($record, $registrar);
        $mailer = \Mockery::mock();
        $mailer->shouldReceive('send')->once()->andReturnUsing(function () use ($service, $registrar, $invitation) {
            $this->assertSame('sending', $invitation->fresh()->status);
            try {
                $service->resend($invitation, $registrar);
                $this->fail('A second send must not claim an invitation already being delivered.');
            } catch (\Illuminate\Validation\ValidationException $exception) {
                $this->assertArrayHasKey('account', $exception->errors());
            }
        });
        Mail::shouldReceive('to')->once()->with($record->email)->andReturn($mailer);

        $sent = $service->resend($invitation, $registrar);

        $this->assertSame('pending', $sent->status);
        $this->assertNotNull($sent->delivered_at);
        $this->assertNotNull($sent->token_hash);
    }

    public function test_stale_sending_lease_can_be_recovered_and_old_token_stays_invalid(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $record = $this->record();
        $service = app(PortalAccountProvisioningService::class);
        $invitation = $service->provision($record, $registrar);
        $oldToken = basename(parse_url(Mail::sent(PortalAccountInvitationMail::class)->first()->activationUrl, PHP_URL_PATH));
        \Illuminate\Support\Facades\DB::table('portal_account_invitations')->where('id', $invitation->id)->update([
            'status' => 'sending', 'updated_at' => now()->subMinutes(16),
        ]);

        $newToken = null;
        $mailer = \Mockery::mock();
        $mailer->shouldReceive('send')->once()->andReturnUsing(function ($mail) use (&$newToken) {
            $newToken = basename(parse_url($mail->activationUrl, PHP_URL_PATH));
        });
        Mail::shouldReceive('to')->once()->with($record->email)->andReturn($mailer);

        $recovered = $service->resend($invitation, $registrar);

        $this->assertSame('pending', $recovered->status);
        $this->assertNotNull($newToken);
        $this->assertSame(hash('sha256', $newToken), $recovered->token_hash);
        $this->assertNotSame($oldToken, $newToken);
        $this->assertFalse($service->activate($oldToken, 'A-long-strong-pass-123'));
    }

    public function test_revocation_during_delivery_invalidates_that_attempt_and_is_not_undone(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $record = $this->record();
        $service = app(PortalAccountProvisioningService::class);
        $invitation = $service->provision($record, $registrar);
        $newToken = null;
        $mailer = \Mockery::mock();
        $mailer->shouldReceive('send')->once()->andReturnUsing(function ($mail) use ($service, $registrar, $invitation, &$newToken) {
            $newToken = basename(parse_url($mail->activationUrl, PHP_URL_PATH));
            $service->revoke($invitation, $registrar);
        });
        Mail::shouldReceive('to')->once()->with($record->email)->andReturn($mailer);

        $result = $service->resend($invitation, $registrar);

        $this->assertSame('revoked', $result->status);
        $this->assertNull($result->token_hash);
        $this->assertNotNull($result->revoked_at);
        $this->assertFalse($service->activate($newToken, 'A-long-strong-pass-123'));
        $this->assertSame('pending', $record->fresh()->user->status);
    }

    public function test_client_cannot_select_a_privileged_role(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $record = $this->record();
        $this->actingAs($registrar)->post(route('registrar.students.provision.store', $record), ['confirm' => '1', 'role' => 'admin'])
            ->assertSessionHasErrors('role');
        $this->assertNull($record->fresh()->user_id);
    }

    public function test_activation_verifies_email_hashes_password_and_consumes_token_once(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $record = $this->record();
        app(PortalAccountProvisioningService::class)->provision($record, $registrar);
        $invitation = $record->fresh()->portalInvitation;
        $token = Mail::sent(PortalAccountInvitationMail::class)->first()->activationUrl;
        $rawToken = basename(parse_url($token, PHP_URL_PATH));

        $this->get(route('portal.activation.show', $rawToken))->assertOk()->assertSee('Choose your password');
        $this->post(route('portal.activation.store'), [
            'token' => $rawToken, 'password' => 'A-long-strong-pass-123', 'password_confirmation' => 'A-long-strong-pass-123',
        ])->assertRedirect(route('login'));

        $user = $record->fresh()->user;
        $this->assertSame('active', $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('A-long-strong-pass-123', $user->password));
        $this->assertSame('consumed', $invitation->fresh()->status);
        $this->assertNull($invitation->fresh()->token_hash);
        $this->assertDatabaseHas('audit_logs', ['action' => 'portal_account.activated', 'user_id' => $user->id]);
        $this->post(route('portal.activation.store'), [
            'token' => $rawToken, 'password' => 'A-long-strong-pass-123', 'password_confirmation' => 'A-long-strong-pass-123',
        ])->assertSessionHasErrors('token');
    }

    public function test_expired_revoked_and_failed_delivery_tokens_cannot_activate(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $record = $this->record();
        app(PortalAccountProvisioningService::class)->provision($record, $registrar);
        $invitation = $record->fresh()->portalInvitation;
        $rawToken = basename(parse_url(Mail::sent(PortalAccountInvitationMail::class)->first()->activationUrl, PHP_URL_PATH));
        $invitation->update(['expires_at' => now()->subMinute()]);
        $this->post(route('portal.activation.store'), ['token' => $rawToken, 'password' => 'A-long-strong-pass-123', 'password_confirmation' => 'A-long-strong-pass-123'])->assertSessionHasErrors('token');
        $this->assertSame('expired', $invitation->fresh()->status);
        $this->assertSame('pending', $record->fresh()->user->status);
    }

    public function test_revoke_invalidates_invitation_and_keeps_account_pending(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $record = $this->record();
        app(PortalAccountProvisioningService::class)->provision($record, $registrar);
        $invitation = $record->fresh()->portalInvitation;
        $rawToken = basename(parse_url(Mail::sent(PortalAccountInvitationMail::class)->first()->activationUrl, PHP_URL_PATH));
        $this->actingAs($registrar)->post(route('registrar.students.invitation.revoke', $record), ['confirm' => '1'])->assertRedirect();
        $this->assertSame('revoked', $invitation->fresh()->status);
        $this->assertSame('pending', $record->fresh()->user->status);
        $this->post(route('portal.activation.store'), ['token' => $rawToken, 'password' => 'A-long-strong-pass-123', 'password_confirmation' => 'A-long-strong-pass-123'])->assertSessionHasErrors('token');
    }

    public function test_mail_failure_is_recorded_without_claiming_delivery_or_leaving_a_valid_token(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $record = $this->record();
        $mailer = \Mockery::mock();
        $mailer->shouldReceive('send')->once()->andThrow(new \RuntimeException('transport unavailable'));
        Mail::shouldReceive('to')->once()->with($record->email)->andReturn($mailer);

        $response = $this->actingAs($registrar)->post(route('registrar.students.provision.store', $record), ['confirm' => '1']);
        $response->assertSessionHas('error', 'Portal account created, but invitation delivery failed. You can safely retry from this record.');
        $invitation = $record->fresh()->portalInvitation;
        $this->assertSame('delivery_failed', $invitation->status);
        $this->assertNull($invitation->delivered_at);
        $this->assertNull($invitation->token_hash);
        $this->assertSame('pending', $record->fresh()->user->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'portal_account.invitation_delivery_failed']);
    }

    public function test_activation_blocks_if_the_pending_account_gains_an_unrequested_staff_role(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $record = $this->record();
        app(PortalAccountProvisioningService::class)->provision($record, $registrar);
        $user = $record->fresh()->user;
        $invitation = $record->fresh()->portalInvitation;
        $token = basename(parse_url(Mail::sent(PortalAccountInvitationMail::class)->first()->activationUrl, PHP_URL_PATH));
        $user->assignRole('admin');

        $this->post(route('portal.activation.store'), ['token' => $token, 'password' => 'A-long-strong-pass-123', 'password_confirmation' => 'A-long-strong-pass-123'])
            ->assertSessionHasErrors('token');
        $this->assertSame('pending', $user->fresh()->status);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertSame('blocked', $invitation->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'portal_account.activation_blocked']);
    }

    public function test_public_activation_submission_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson(route('portal.activation.store'), [])->assertUnprocessable();
        }
        $this->postJson(route('portal.activation.store'), [])->assertStatus(429);
    }

    public function test_imports_remain_academic_only_and_do_not_provision_accounts(): void
    {
        $this->assertDatabaseMissing('portal_account_invitations', ['id' => 1]);
        $this->assertDatabaseCount('users', 0);
        $this->assertSame(0, AuditLog::where('action', 'portal_account.provisioned')->count());
    }

    public function test_completed_import_offers_bounded_separate_bulk_invitation_and_retry_is_idempotent(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $csv = "Student Number,First Name,Middle Name,Last Name,Suffix,Email,Program,Year Level,School Year\nDTC-BULK-1,Iris,,Lopez,,iris.bulk@example.test,,,\n";
        $this->actingAs($registrar)->post(route('registrar.imports.upload'), [
            'import_type' => 'students',
            'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ])->assertRedirect();
        $batch = \App\Models\AcademicImportBatch::firstOrFail();
        $this->actingAs($registrar)->post(route('registrar.imports.commit', $batch), ['confirm' => '1'])->assertRedirect();
        $record = $batch->fresh()->studentProfiles()->firstOrFail();
        $this->assertNull($record->user_id);
        $this->actingAs($registrar)->get(route('registrar.imports.show', $batch))->assertOk()->assertSee('Portal account invitations');

        $payload = ['confirm' => '1', 'student_profile_ids' => [$record->id]];
        $this->actingAs($registrar)->post(route('registrar.imports.provision-selected', $batch), $payload)
            ->assertRedirect(route('registrar.imports.show', $batch))->assertSessionHas('provisioning_results', ['sent' => 1, 'failed' => 0, 'skipped' => 0]);
        $this->assertSame(1, PortalAccountInvitation::count());
        $this->assertSame('pending', $record->fresh()->user->status);

        $this->actingAs($registrar)->post(route('registrar.imports.provision-selected', $batch), $payload)
            ->assertSessionHas('provisioning_results', ['sent' => 0, 'failed' => 0, 'skipped' => 1]);
        $this->assertSame(1, PortalAccountInvitation::count());
        Mail::assertSent(PortalAccountInvitationMail::class, 1);
    }
}
