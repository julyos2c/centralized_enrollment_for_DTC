<?php

namespace App\Mail;

use App\Models\StudentProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PortalAccountInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public StudentProfile $profile,
        public string $activationUrl,
        public int $expiresInMinutes
    ) {}

    public function build()
    {
        return $this->subject('Set up your DTC Nexus portal account')
            ->view('emails.portal-account-invitation');
    }
}
