<?php

namespace Tests\Feature;

use App\Mail\WorkflowUpdate;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WorkflowEmailNotificationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_failed_enqueue_can_retry_without_duplicate_notification_or_email(): void
    {
        $user = $this->user('retry@example.test');
        $pending = \Mockery::mock(\Illuminate\Mail\PendingMail::class);
        Mail::shouldReceive('to')->once()->with($user->email)->andReturn($pending);
        $pending->shouldReceive('queue')->once()->andThrow(new \RuntimeException('Queue unavailable'));
        $this->assertFalse(NotificationService::sendUserUpdateSafely($user, 'retry:1', 'Received', 'Your request was received.'));
        $notification = UserNotification::where('event_key', 'retry:1')->firstOrFail();
        $this->assertSame('enqueue_failed', $notification->email_status);

        Mail::fake();
        $this->assertTrue(NotificationService::sendUserUpdateSafely($user, 'retry:1', 'Received', 'Your request was received.'));
        $this->assertSame('queued', $notification->fresh()->email_status);
        $this->assertTrue(NotificationService::sendUserUpdateSafely($user, 'retry:1', 'Received', 'Your request was received.'));
        $this->assertSame(1, UserNotification::where('event_key', 'retry:1')->count());
        Mail::assertQueued(WorkflowUpdate::class, 1);
    }

    public function test_queued_mailable_retries_execution_and_skips_already_sent_duplicates(): void
    {
        Mail::fake();
        $user = $this->user('worker@example.test');
        NotificationService::sendUserUpdateSafely($user, 'worker:1', 'Received', 'Received.');
        $notification = UserNotification::where('event_key', 'worker:1')->firstOrFail();
        $mail = (new WorkflowUpdate($user->name, 'Received', 'Received.', null, 'View', $notification->id))->to($user->email);
        $mailer = \Mockery::mock(\Illuminate\Contracts\Mail\Mailer::class);
        $mailer->shouldReceive('send')->once()->andThrow(new \RuntimeException('Transport unavailable'));
        try {
            $mail->send($mailer);
            $this->fail('Transport failure must propagate for native queue retries.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Transport unavailable', $exception->getMessage());
        }
        $this->assertSame('job_retrying', $notification->fresh()->email_status);
        $mail->failed(new \RuntimeException('Attempts exhausted'));
        $this->assertSame('job_failed', $notification->fresh()->email_status);

        $transport = (new \Illuminate\Mail\MailManager(app()))->mailer('array');
        $mail->send($transport);
        $this->assertSame('sent', $notification->fresh()->email_status);
        $this->assertNotNull($notification->fresh()->email_sent_at);
        // A replay must never contact the transport again.
        $mail->send($mailer);
        $mail->failed(new \RuntimeException('Old duplicate failed'));
        $this->assertSame('sent', $notification->fresh()->email_status);
    }

    public function test_rolled_back_workflow_does_not_enqueue_mail_or_keep_notification(): void
    {
        Mail::fake();
        $user = $this->user('rollback@example.test');
        \Illuminate\Support\Facades\DB::beginTransaction();
        NotificationService::sendUserUpdateSafely($user, 'rollback:1', 'Received', 'Received.');
        Mail::assertNothingQueued();
        \Illuminate\Support\Facades\DB::rollBack();
        $this->assertSame(0, UserNotification::where('event_key', 'rollback:1')->count());
        Mail::assertNothingQueued();
    }

    public function test_user_workflow_update_creates_in_app_notification_and_queues_email(): void
    {
        Mail::fake();
        $user = $this->user('student@example.test');

        $sent = NotificationService::sendUserUpdateSafely(
            $user,
            'enrollment-approved:42',
            'Enrollment Approved',
            'Your enrollment is ready for payment.',
            'success',
            'https://example.test/portal/enrollment/42',
            'View payment options'
        );

        $this->assertTrue($sent);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'event_key' => 'enrollment-approved:42',
            'title' => 'Enrollment Approved',
        ]);
        Mail::assertQueued(WorkflowUpdate::class, fn (WorkflowUpdate $mail) =>
            $mail->hasTo('student@example.test')
            && $mail->eventTitle === 'Enrollment Approved'
            && $mail->actionLabel === 'View payment options'
        );
    }

    public function test_duplicate_event_does_not_queue_duplicate_email(): void
    {
        Mail::fake();
        $user = $this->user('duplicate@example.test');

        foreach (range(1, 2) as $attempt) {
            NotificationService::sendUserUpdateSafely(
                $user,
                'appointment-confirmed:7',
                'Appointment Confirmed',
                'Your appointment is confirmed.',
                'success',
                'https://example.test/portal/appointments'
            );
        }

        $this->assertSame(1, UserNotification::where('event_key', 'appointment-confirmed:7')->count());
        Mail::assertQueued(WorkflowUpdate::class, 1);
    }

    public function test_operator_can_retry_failed_enqueue_but_not_already_queued_mail(): void
    {
        Mail::fake();
        $user = $this->user('operator-retry@example.test');
        NotificationService::sendUserUpdateSafely($user, 'operator:1', 'Received', 'Received.');
        $notification = UserNotification::where('event_key', 'operator:1')->firstOrFail();
        $notification->forceFill(['email_status' => 'enqueue_failed'])->save();
        Mail::fake();
        $this->artisan('workflow-emails:retry', ['notification' => $notification->id])->assertExitCode(0);
        $this->artisan('workflow-emails:retry', ['notification' => $notification->id])->assertExitCode(1);
        Mail::assertQueued(WorkflowUpdate::class, 1);
        $this->assertSame(1, UserNotification::where('event_key', 'operator:1')->count());
    }

    public function test_missing_email_keeps_in_app_notification_without_queueing_mail(): void
    {
        Mail::fake();
        $user = $this->user('');

        $this->assertTrue(NotificationService::sendUserUpdateSafely(
            $user,
            'document-ready:9',
            'Document Ready',
            'Your document is ready for pickup.',
            'success'
        ));

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'event_key' => 'document-ready:9',
        ]);
        Mail::assertNothingQueued();
    }

    private function user(?string $email): User
    {
        return User::create([
            'name' => 'Test Student',
            'email' => $email,
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
    }
}
