<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class DtcResetPasswordNotification extends Notification
{
    use Queueable;

    public string $token;
    public int $expiresInMinutes;

    public function __construct(string $token, int $expiresInMinutes = 60)
    {
        $this->token            = $token;
        $this->expiresInMinutes = $expiresInMinutes;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Reset Your DTC Nexus Password')
            ->view('emails.reset-password', [
                'resetUrl'         => $resetUrl,
                'email'            => $notifiable->getEmailForPasswordReset(),
                'expiresInMinutes' => $this->expiresInMinutes,
            ]);
    }
}
