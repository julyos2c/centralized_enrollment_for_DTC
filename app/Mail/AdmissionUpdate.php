<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Applicant-facing admission email (document review, exam schedule,
 * results). Sent synchronously after the related change has committed,
 * like the other application emails; a delivery failure is logged and
 * never undoes the saved change.
 */
class AdmissionUpdate extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $eventTitle,
        public string $eventMessage,
        public ?string $actionUrl = null,
        public string $actionLabel = 'Open your admission portal'
    ) {
    }

    public function build(): self
    {
        return $this->subject('DTC Nexus Admission: ' . $this->eventTitle)
            ->view('emails.workflow-update');
    }
}
