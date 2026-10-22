<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WorkflowUpdate extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $recipientName,
        public string $eventTitle,
        public string $eventMessage,
        public ?string $actionUrl = null,
        public string $actionLabel = 'View in DTC EMS',
        public ?int $notificationId = null
    ) {
        $this->afterCommit();
    }

    public function send($mailer)
    {
        if (!$this->notificationId) return parent::send($mailer);

        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($mailer) {
                $notification = \App\Models\UserNotification::whereKey($this->notificationId)->lockForUpdate()->first();
                if (!$notification || in_array($notification->email_status, ['sent', 'suppressed'], true)) return null;
                // Serialize duplicate jobs, including a retry after a lost worker acknowledgement.
                $result = parent::send($mailer);
                $notification->forceFill(['email_status' => $result ? 'sent' : 'suppressed', 'email_sent_at' => $result ? now() : null])->save();
                return $result;
            });
        } catch (\Throwable $exception) {
            \App\Models\UserNotification::whereKey($this->notificationId)->where('email_status', '!=', 'sent')->update(['email_status' => 'job_retrying']);
            throw $exception;
        }
    }

    public function failed(\Throwable $exception): void
    {
        if ($this->notificationId) {
            \App\Models\UserNotification::whereKey($this->notificationId)->where('email_status', '!=', 'sent')->update(['email_status' => 'job_failed']);
        }
    }

    public function build(): self
    {
        return $this->subject('DTC EMS: '.$this->eventTitle)
            ->view('emails.workflow-update');
    }
}
