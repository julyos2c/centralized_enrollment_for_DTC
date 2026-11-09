<?php

namespace App\Services;

use App\Mail\AdmissionUpdate;
use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Delivers admission workflow updates. Applicants are reached by email
 * (most have no account until approval) and, once they have an account,
 * also in the portal. Staff receive in-app notifications. Every method
 * is safe to call after a committed change: failures are logged only.
 */
class AdmissionNotifier
{
    public function applicant(Application $application, string $title, string $message, string $tone = 'info'): bool
    {
        $emailSent = false;
        if (filter_var($application->email, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::to($application->email)->send(new AdmissionUpdate(
                    trim($application->first_name) ?: 'Applicant',
                    $title,
                    $message . ' (Reference: ' . $application->reference_no . ')',
                    route('public.admission.portal')
                ));
                $emailSent = true;
            } catch (\Throwable $exception) {
                Log::warning('Admission update email could not be sent.', [
                    'application_id' => $application->id,
                    'title' => $title,
                    'exception' => get_class($exception),
                ]);
            }
        }

        if ($application->user) {
            NotificationService::sendSafely($application->user, $title, $message, $tone, route('portal.application.show'));
        }

        return $emailSent;
    }

    public function staff(Application $application, string $title, string $message, string $tone = 'info', ?string $link = null): void
    {
        try {
            foreach (User::role(['registrar', 'admin'])->get() as $staff) {
                NotificationService::sendSafely(
                    $staff,
                    $title,
                    $message,
                    $tone,
                    $link ?? route('registrar.applications.show', $application)
                );
            }
        } catch (\Throwable $exception) {
            Log::warning('Admission staff notifications could not be sent.', [
                'application_id' => $application->id,
                'exception' => get_class($exception),
            ]);
        }
    }
}
