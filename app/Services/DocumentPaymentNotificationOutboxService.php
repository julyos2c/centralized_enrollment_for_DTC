<?php

namespace App\Services;

use App\Models\DocumentPaymentNotificationOutbox;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DocumentPaymentNotificationOutboxService
{
    /** Process due events; the row lock serializes workers for each event. */
    public function processDue(int $limit = 100): int
    {
        $ids = DocumentPaymentNotificationOutbox::query()
            ->where('status', 'pending')
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->limit(max(1, min($limit, 500)))
            ->pluck('id');

        $delivered = 0;
        foreach ($ids as $id) {
            if ($this->processOne((int) $id)) {
                $delivered++;
            }
        }

        return $delivered;
    }

    public function processOne(int $id): bool
    {
        $attempt = 0;

        try {
            return DB::transaction(function () use ($id, &$attempt) {
                $event = DocumentPaymentNotificationOutbox::whereKey($id)->lockForUpdate()->first();
                if (! $event || $event->status !== 'pending' || $event->available_at->isFuture()) {
                    return false;
                }

                $attempt = (int) $event->attempts + 1;
                $event->attempts = $attempt;
                $event->save();

                foreach ($event->recipient_ids ?: [] as $recipientId) {
                    $recipient = User::find($recipientId);
                    if ($recipient) {
                        NotificationService::sendForEvent(
                            $recipient,
                            $event->event_key,
                            $event->title,
                            $event->message,
                            $event->type,
                            $event->link
                        );
                    }
                }

                $event->status = 'delivered';
                $event->delivered_at = now();
                $event->last_error = null;
                $event->save();

                return true;
            });
        } catch (\Throwable $exception) {
            $this->recordFailure($id, $attempt, $exception);
            return false;
        }
    }

    private function recordFailure(int $id, int $attempt, \Throwable $exception): void
    {
        DB::transaction(function () use ($id, $attempt, $exception) {
            $event = DocumentPaymentNotificationOutbox::whereKey($id)->lockForUpdate()->first();
            if (! $event || $event->status !== 'pending') {
                return;
            }

            $attempt = max($attempt, (int) $event->attempts + 1);
            $event->attempts = $attempt;
            $event->last_error = get_class($exception);

            if ($attempt >= (int) $event->max_attempts) {
                $event->status = 'failed';
            } else {
                $delay = min(3600, 30 * (2 ** max(0, $attempt - 1)));
                $event->available_at = now()->addSeconds($delay);
            }

            $event->save();
        });

        Log::warning('Document payment notification outbox delivery failed.', [
            'outbox_id' => $id,
            'attempt' => $attempt,
            'exception' => get_class($exception),
        ]);
    }
}
