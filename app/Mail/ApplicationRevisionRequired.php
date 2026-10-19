<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ApplicationRevisionRequired extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The application record requiring revision.
     */
    public Application $application;

    public function __construct(Application $application)
    {
        $this->application = $application;
    }

    public function build()
    {
        return $this
            ->subject('Action Needed: Your DTC Nexus Application Requires Revision')
            ->view('emails.application-revision-required');
    }
}