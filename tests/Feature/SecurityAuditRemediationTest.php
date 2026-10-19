<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\BackupLog;
use App\Models\Program;
use App\Models\User;
use App\Models\UserNotification;
use App\Mail\ApplicationSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\SeedsRoles;
use Tests\TestCase;

class SecurityAuditRemediationTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_confirmation_requires_the_correct_unexpired_secret_token(): void
    {
        $first = $this->makeApplication(['first_name' => 'First Applicant']);
        $second = $this->makeApplication(['first_name' => 'Second Applicant']);
        $token = str_repeat('a', 64);
        $first->update([
            'confirmation_token_hash' => hash('sha256', $token),
            'confirmation_token_expires_at' => now()->addDay(),
        ]);

        $this->withSession(['application_confirmation_token' => $token])
            ->get(route('public.application.success'))
            ->assertOk()
            ->assertSee('First Applicant')
            ->assertDontSee('Second Applicant');

        $this->withSession(['application_confirmation_token' => str_repeat('b', 64)])
            ->get(route('public.application.success'))
            ->assertNotFound()
            ->assertDontSee('First Applicant');

        $first->update(['confirmation_token_expires_at' => now()->subSecond()]);
        $this->withSession(['application_confirmation_token' => $token])
            ->get(route('public.application.success'))
            ->assertNotFound();
        $this->assertNotSame($first->id, $second->id);
    }

    public function test_confirmation_survives_refresh_and_reference_is_sent_for_session_recovery(): void
    {
        Mail::fake();
        $application = $this->makeApplication();
        $token = str_repeat('c', 64);
        $application->update([
            'confirmation_token_hash' => hash('sha256', $token),
            'confirmation_token_expires_at' => now()->addDay(),
        ]);

        $this->withSession(['application_confirmation_token' => $token])
            ->get(route('public.application.success'))
            ->assertOk()
            ->assertSee($application->reference_no);
        $this->get(route('public.application.success'))
            ->assertOk()
            ->assertSee($application->reference_no);

        Mail::to($application->email)->send(new ApplicationSubmitted($application));
        Mail::assertSent(ApplicationSubmitted::class, function (ApplicationSubmitted $mail) use ($application) {
            $html = view('emails.application-submitted', [
                'referenceNo' => $mail->referenceNo,
            ])->render();
            $this->assertStringContainsString($application->reference_no, $html);

            return $mail->referenceNo === $application->reference_no;
        });
    }

    public function test_application_submission_redirects_to_a_working_secret_confirmation(): void
    {
        Mail::fake();
        $program = Program::factory()->create();
        $this->get(route('public.application.create'))->assertOk();
        $response = $this->post(route('public.application.store'), [
            'academic_status' => 'new_student',
            'program_id' => $program->id,
            'submission_token' => $this->app['session.store']->get('public_application_submission_token'),
            'first_name' => 'New',
            'last_name' => 'Applicant',
            'gender' => 'female',
            'birthdate' => '2005-01-01',
            'email' => 'new-applicant@example.test',
            'ack_enrollment' => 'on',
        ]);

        $application = Application::where('email', 'new-applicant@example.test')->firstOrFail();
        $this->assertNotNull($application->confirmation_token_hash);
        $response->assertRedirect(route('public.application.success'));
        $response->assertSessionHas('application_confirmation_token');
        $token = $response->getSession()->get('application_confirmation_token');
        $this->assertIsString($token);
        $this->assertSame(64, strlen($token));
        $this->assertSame(hash('sha256', $token), $application->confirmation_token_hash);
        $this->get(route('public.application.success'))
            ->assertOk()
            ->assertSee('New')
            ->assertSee('Applicant');
        Mail::assertSent(ApplicationSubmitted::class, function (ApplicationSubmitted $mail) use ($application) {
            $html = view('emails.application-submitted', [
                'referenceNo' => $mail->referenceNo,
            ])->render();
            $this->assertStringContainsString($application->reference_no, $html);

            return $mail->referenceNo === $application->reference_no;
        });
    }

    public function test_applicant_can_request_activation_without_having_the_original_link(): void
    {
        Mail::fake();
        $application = $this->makeApplication([
            'status' => 'approved',
            'email' => 'activation-request@example.test',
        ]);
        $user = User::factory()->create([
            'email' => 'activation-request@example.test',
            'email_verified_at' => null,
        ]);
        $application->update(['user_id' => $user->id]);

        $this->get(route('password.activation.request'))
            ->assertOk()
            ->assertSee(route('password.activation.resend'));

        $this->post(route('password.activation.resend'), ['email' => $user->email])
            ->assertRedirect(route('password.activation.request'))
            ->assertSessionHas('success');

        Mail::assertSent(\App\Mail\ApplicationApproved::class, function ($mail) use ($application) {
            return $mail->application->is($application);
        });
    }

    public function test_status_lookup_is_throttled_by_ip_and_resumes_after_window(): void
    {
        config(['services.application_status_lookup.per_identity' => 2]);
        $data = ['reference_no' => 'APP-TEST-123', 'email' => 'lookup@example.test'];

        $this->post(route('public.application.status'), $data)->assertRedirect();
        $this->post(route('public.application.status'), $data)->assertRedirect();
        $this->post(route('public.application.status'), $data)->assertTooManyRequests();

        $this->travel(61)->seconds();
        $this->post(route('public.application.status'), $data)->assertRedirect();
    }

    public function test_status_lookup_ip_limit_cannot_be_evaded_by_changing_reference(): void
    {
        config(['services.application_status_lookup.per_ip' => 2]);
        foreach (['APP-ONE', 'APP-TWO'] as $reference) {
            $this->post(route('public.application.status'), [
                'reference_no' => $reference,
                'email' => 'same@example.test',
            ])->assertRedirect();
        }

        $this->post(route('public.application.status'), [
            'reference_no' => 'APP-THREE',
            'email' => 'same@example.test',
        ])->assertTooManyRequests();
    }

    public function test_status_limiter_keys_do_not_contain_lookup_identity(): void
    {
        $request = Request::create('/apply/status', 'POST', [
            'reference_no' => 'APP-PRIVATE-REFERENCE',
            'email' => 'private@example.test',
        ]);
        $limits = RateLimiter::limiter('application-status-lookup')($request);

        foreach ($limits as $limit) {
            $this->assertStringNotContainsString('private@example.test', $limit->key);
            $this->assertStringNotContainsString('APP-PRIVATE-REFERENCE', $limit->key);
        }
    }

    public function test_untrusted_backup_upload_is_rejected_without_executing_sql(): void
    {
        $admin = $this->makeUserWithRole('admin');
        DB::shouldReceive('unprepared')->never();

        $this->actingAs($admin)
            ->post(route('admin.settings.backup.restore'), [
                'sql_file' => UploadedFile::fake()->createWithContent('restore.sql', 'DROP TABLE users;'),
            ])
            ->assertRedirect(route('admin.settings.backup'))
            ->assertSessionHas('error');
    }

    public function test_backup_creation_and_download_remain_available_to_admins(): void
    {
        $admin = $this->makeUserWithRole('admin');
        $this->actingAs($admin);

        try {
            $this->post(route('admin.settings.backup.create'))->assertDownload();
            $backup = BackupLog::where('created_by', $admin->id)->where('status', 'completed')->latest()->firstOrFail();

            $this->get(route('admin.settings.backup.download', $backup))->assertDownload($backup->filename);
        } finally {
            foreach (BackupLog::where('created_by', $admin->id)->get() as $backup) {
                $path = storage_path('app/backups/' . $backup->filename);
                if (is_file($path)) {
                    unlink($path);
                }
                $backup->delete();
            }
        }
    }

    public function test_backup_restore_requires_admin_authorization(): void
    {
        $this->post(route('admin.settings.backup.restore'))
            ->assertRedirect(route('login'));
    }

    public function test_approval_creates_account_notifies_student_and_repeated_approval_has_no_side_effects(): void
    {
        Mail::fake();
        $registrar = $this->makeUserWithRole('registrar');
        $application = $this->makeApplication([
            'status' => 'under_review',
            'email' => 'approved@example.test',
        ]);

        $this->actingAs($registrar)
            ->post(route('registrar.applications.approve', $application))
            ->assertRedirect(route('registrar.applications.index'));

        $application->refresh();
        $this->assertSame('approved', $application->status);
        $this->assertNotNull($application->user_id);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $application->user_id,
            'title' => 'Application Approved',
        ]);
        $this->assertSame(1, User::where('email', 'approved@example.test')->count());

        $notificationCount = UserNotification::count();
        $this->post(route('registrar.applications.approve', $application))
            ->assertRedirect(route('registrar.applications.show', $application));
        $this->assertSame(1, User::where('email', 'approved@example.test')->count());
        $this->assertSame($notificationCount, UserNotification::count());
        Mail::assertSent(\App\Mail\ApplicationApproved::class, 1);
    }

    public function test_approval_notification_failure_does_not_undo_committed_approval(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('mail unavailable'));
        UserNotification::creating(function () {
            throw new \RuntimeException('notification unavailable');
        });

        $registrar = $this->makeUserWithRole('registrar');
        $application = $this->makeApplication([
            'status' => 'under_review',
            'email' => 'notification-failure@example.test',
        ]);

        $this->actingAs($registrar)
            ->post(route('registrar.applications.approve', $application))
            ->assertRedirect(route('registrar.applications.index'));

        $this->assertSame('approved', $application->fresh()->status);
        $this->assertDatabaseHas('users', ['email' => 'notification-failure@example.test']);
    }

    public function test_one_admin_notification_failure_does_not_block_other_admins_or_applicant(): void
    {
        Mail::fake();
        $registrar = $this->makeUserWithRole('registrar');
        $firstAdmin = $this->makeUserWithRole('admin');
        $secondAdmin = $this->makeUserWithRole('admin');
        $application = $this->makeApplication([
            'status' => 'under_review',
            'email' => 'independent-notifications@example.test',
        ]);

        UserNotification::creating(function (UserNotification $notification) use ($firstAdmin) {
            if ((int) $notification->user_id === (int) $firstAdmin->id) {
                throw new \RuntimeException('notification transport detail');
            }
        });

        $response = $this->actingAs($registrar)
            ->post(route('registrar.applications.approve', $application));

        $response->assertRedirect(route('registrar.applications.index'))
            ->assertSessionHas('success')
            ->assertDontSee('notification transport detail');

        $approved = $application->fresh();
        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $firstAdmin->id,
            'title' => 'Application Approved',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $secondAdmin->id,
            'title' => 'Application Approved',
            'message' => 'Registrar approved the pre-enrollment application for Test Applicant.',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $approved->user_id,
            'title' => 'Application Approved',
        ]);
    }

    public function test_database_failure_during_approval_keeps_application_unapproved(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $application = $this->makeApplication(['status' => 'under_review']);
        Application::updated(function (Application $updated) use ($application) {
            if ($updated->is($application) && $updated->status === 'approved') {
                throw new \RuntimeException('database write failed');
            }
        });

        $this->actingAs($registrar)
            ->post(route('registrar.applications.approve', $application))
            ->assertRedirect(route('registrar.applications.index'));

        $this->assertSame('under_review', $application->fresh()->status);
        $this->assertNull($application->fresh()->user_id);
        $this->assertDatabaseMissing('users', ['email' => $application->email]);
    }

    private function makeApplication(array $attributes = []): Application
    {
        $program = Program::factory()->create();

        return Application::create(array_merge([
            'reference_no' => 'APP-' . uniqid(),
            'program_id' => $program->id,
            'academic_status' => 'new_student',
            'first_name' => 'Test',
            'last_name' => 'Applicant',
            'gender' => 'female',
            'birthdate' => '2005-01-01',
            'email' => 'applicant-' . uniqid() . '@example.test',
            'status' => 'submitted',
        ], $attributes));
    }
}
