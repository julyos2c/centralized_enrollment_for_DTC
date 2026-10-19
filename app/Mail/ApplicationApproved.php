<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ApplicationApproved extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The approved application record.
     */
    public Application $application;

    /**
     * One-time account-activation ("set your password") URL.
     */
    public string $setupUrl;

    /**
     * How long the activation link remains valid, in minutes.
     *
     * Mirrors config('auth.passwords.users.expire'), passed in explicitly
     * so this Mailable has no hidden dependency on how the token was minted.
     */
    public int $expiresInMinutes;

    public function __construct(Application $application, string $setupUrl, int $expiresInMinutes = 60)
    {
        $this->application = $application;
        $this->setupUrl = $setupUrl;
        $this->expiresInMinutes = $expiresInMinutes;
    }

    public function build()
    {
        return $this
            ->subject('Your DTC Nexus Application Has Been Approved')
            ->view('emails.application-approved');
    }
}