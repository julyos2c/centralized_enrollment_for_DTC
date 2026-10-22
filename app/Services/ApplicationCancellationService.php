<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationCancellationRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplicationCancellationService
{
    private const REQUESTABLE_STATUSES = ['submitted', 'under_review', 'revision_required'];

    /** Submit a staff-reviewed request using the applicant's existing confirmation capability. */
    public function requestUsingConfirmationToken(string $token, string $reason): array
    {
        return DB::transaction(function () use ($token, $reason) {
            $application = Application::where('confirmation_token_hash', hash('sha256', $token))
                ->where('confirmation_token_expires_at', '>', now())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($application->status, self::REQUESTABLE_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'cancellation' => 'This application is not eligible for a cancellation request.',
                ]);
            }

            // An account link means approval or identity linkage has occurred; the
            // applicant cancellation capability must not bypass that workflow.
            if ($application->user_id !== null) {
                throw ValidationException::withMessages([
                    'cancellation' => 'This application is linked to a portal account and requires staff assistance.',
                ]);
            }

            $existing = ApplicationCancellationRequest::where('application_id', $application->id)
                ->where('status', 'pending')
                ->latest('id')
                ->first();

            if ($existing) {
                return ['application' => $application, 'request' => $existing, 'created' => false];
            }

            $cancellation = ApplicationCancellationRequest::create([
                'application_id' => $application->id,
                'previous_status' => $application->status,
                'status' => 'pending',
                'reason' => $reason,
            ]);

            AuditLog::create([
                'action' => 'application.cancellation_requested',
                'description' => 'Applicant requested cancellation of '.$application->reference_no.'.',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'metadata' => [
                    'application_id' => $application->id,
                    'reference_no' => $application->reference_no,
                    'applicant_user_id' => $application->user_id,
                    'actor_role' => 'applicant_confirmation_token',
                    'request_id' => $cancellation->id,
                    'previous_status' => $application->status,
                    'request_status' => 'pending',
                    'reason' => $reason,
                ],
            ]);

            foreach (User::role(['registrar', 'admin'])->get() as $staff) {
                NotificationService::sendForEvent(
                    $staff,
                    'application-cancellation-request:'.$cancellation->id,
                    'Application Cancellation Requested',
                    'A cancellation request is awaiting review for application '.$application->reference_no.'.',
                    'warning',
                    route('registrar.applications.show', $application)
                );
            }

            return ['application' => $application, 'request' => $cancellation, 'created' => true];
        });
    }

    /** Resolve one pending request while holding the application row lock used by review transitions. */
    public function resolve(int $requestId, User $actor, bool $approve, ?string $remarks): array
    {
        $requestSnapshot = ApplicationCancellationRequest::findOrFail($requestId);

        return DB::transaction(function () use ($requestSnapshot, $requestId, $actor, $approve, $remarks) {
            $application = Application::whereKey($requestSnapshot->application_id)->lockForUpdate()->firstOrFail();
            $cancellation = ApplicationCancellationRequest::whereKey($requestId)
                ->where('application_id', $application->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($cancellation->status !== 'pending') {
                return ['application' => $application, 'request' => $cancellation, 'changed' => false];
            }

            if ($approve) {
                if (! in_array($application->status, self::REQUESTABLE_STATUSES, true)
                    || $application->user_id !== null) {
                    throw ValidationException::withMessages([
                        'cancellation' => 'The application is no longer eligible for cancellation.',
                    ]);
                }

                $application->update(['status' => 'cancelled']);
            }

            $cancellation->update([
                'status' => $approve ? 'approved' : 'rejected',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_remarks' => $remarks,
            ]);

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => $approve ? 'application.cancellation_approved' : 'application.cancellation_rejected',
                'description' => ($approve ? 'Approved' : 'Rejected').' cancellation request for '.$application->reference_no.'.',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'metadata' => [
                    'application_id' => $application->id,
                    'reference_no' => $application->reference_no,
                    'applicant_user_id' => $application->user_id,
                    'request_id' => $cancellation->id,
                    'actor_role' => $actor->getRoleNames()->implode(','),
                    'previous_status' => $cancellation->previous_status,
                    'resulting_status' => $application->status,
                    'request_status' => $cancellation->status,
                    'reason' => $cancellation->reason,
                    'review_remarks' => $remarks,
                ],
            ]);

            return ['application' => $application->fresh(['user']), 'request' => $cancellation, 'changed' => true];
        });
    }
}
