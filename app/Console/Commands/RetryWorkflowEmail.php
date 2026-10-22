<?php

namespace App\Console\Commands;

use App\Models\UserNotification;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class RetryWorkflowEmail extends Command
{
    protected $signature = 'workflow-emails:retry {notification : Notification ID with a failed enqueue}';
    protected $description = 'Retry a workflow email that was never successfully enqueued';

    public function handle(): int
    {
        $notification = UserNotification::with('user')->find($this->argument('notification'));
        if (!$notification || !$notification->user?->email || !$notification->event_key
            || !in_array($notification->email_status, ['pending', 'enqueue_failed'], true)) {
            $this->error('Only pending or enqueue-failed workflow emails with an existing recipient can be retried here. Use queue:retry for failed jobs.');
            return self::FAILURE;
        }
        $queued = NotificationService::sendUserUpdateSafely(
            $notification->user, $notification->event_key, $notification->title,
            $notification->message, $notification->type, $notification->link
        );
        if (!$queued) {
            $this->error('Email retry failed; inspect the notification state and application log.');
            return self::FAILURE;
        }
        $this->info('Email retry accepted. This is not confirmation of inbox delivery.');
        return self::SUCCESS;
    }
}
