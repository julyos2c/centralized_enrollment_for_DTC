<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotification;
use App\Models\DocumentRequest;
use App\Models\DocumentPaymentNotificationOutbox;
use App\Mail\DocumentRequestUpdate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;

class NotificationService
{
    /** Persist the notification event inside the payment-verification transaction. */
    public static function enqueueDocumentPaymentVerified(DocumentRequest $documentRequest): DocumentPaymentNotificationOutbox
    {
        $documentRequest->loadMissing('user');
        $method = $documentRequest->payment_method === 'gcash_qr' ? 'GCash' : 'Cash / walk-in';

        return DocumentPaymentNotificationOutbox::create([
            'event_key' => 'document-payment-verified:' . $documentRequest->id,
            'document_request_id' => $documentRequest->id,
            'recipient_ids' => User::role(['registrar', 'admin'])->pluck('users.id')->map(function ($id) { return (int) $id; })->all(),
            'title' => 'Document Payment Verified',
            'message' => $documentRequest->user->name . ' paid PHP ' . number_format((float) $documentRequest->amount_received, 2)
                . ' via ' . $method . ' for ' . $documentRequest->document_label
                . ' (request #' . $documentRequest->id . ').',
            'type' => 'success',
            'link' => route('registrar.documents.index'),
            'status' => 'pending',
            'available_at' => now(),
            'max_attempts' => 8,
        ]);
    }

    public static function send(User $user, string $title, string $message, string $type = 'info', ?string $link = null)
    {
        return UserNotification::create([
            'user_id' => $user->id,
            'title'   => $title,
            'message' => $message,
            'type'    => $type,
            'link'    => $link,
        ]);
    }

    /** Create an event-keyed alert once, even if a worker retries after an uncertain acknowledgement. */
    public static function sendForEvent(User $user, string $eventKey, string $title, string $message, string $type, ?string $link): UserNotification
    {
        try {
            return UserNotification::firstOrCreate(
                ['user_id' => $user->id, 'event_key' => $eventKey],
                ['title' => $title, 'message' => $message, 'type' => $type, 'link' => $link]
            );
        } catch (QueryException $exception) {
            // The unique (user_id, event_key) index is the final deduplication
            // guard if two request retries race to create the same event.
            $existing = UserNotification::where('user_id', $user->id)
                ->where('event_key', $eventKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            throw $exception;
        }
    }

    /** Persist an in-app alert without turning an already-committed action into an error page. */
    public static function sendSafely(User $user, string $title, string $message, string $type = 'info', ?string $link = null): bool
    {
        try {
            self::send($user, $title, $message, $type, $link);
            return true;
        } catch (\Throwable $exception) {
            Log::warning('Could not persist an in-app notification.', [
                'user_id' => $user->id,
                'title' => $title,
                'exception' => get_class($exception),
            ]);
            return false;
        }
    }

    /** Queue applicant email only after the caller's database transaction has committed. */
    public static function emailDocumentRequestSafely(User $user, \App\Models\DocumentRequest $documentRequest, string $message): bool
    {
        if (! $user->email) {
            Log::warning('Could not queue document request email because the applicant has no email address.', [
                'user_id' => $user->id,
                'document_request_id' => $documentRequest->id,
            ]);
            return false;
        }

        try {
            Mail::to($user->email)->queue(new DocumentRequestUpdate($documentRequest, $message));
            return true;
        } catch (\Throwable $exception) {
            Log::warning('Could not queue document request email.', [
                'user_id' => $user->id,
                'document_request_id' => $documentRequest->id,
                'exception' => get_class($exception),
            ]);
            return false;
        }
    }
}
