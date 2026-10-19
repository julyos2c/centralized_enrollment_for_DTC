<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ApplicationRejected extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The rejected application record.
     */
    public Application $application;

    public function __construct(Application $application)
    {
        $this->application = $application;
    }

    public function build()
    {
        return $this
            ->subject('DTC Nexus Application Update — ' . $this->application->reference_no)
            ->view('emails.application-rejected');
    }
}