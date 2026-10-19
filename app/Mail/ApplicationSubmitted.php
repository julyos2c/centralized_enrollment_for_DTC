<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ApplicationSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public string $referenceNo;

    public function __construct(Application $application)
    {
        $this->referenceNo = $application->reference_no;
    }

    public function build()
    {
        return $this
            ->subject('Your DTC Nexus Application Reference')
            ->view('emails.application-submitted');
    }
}
