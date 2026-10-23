<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class StudentWelcome
{
    /** Called inside the payment transaction, only after a real applicant role swap. */
    public static function record(User $user): void
    {
        DB::table('student_welcomes')->insertOrIgnore(['user_id' => $user->id, 'promoted_at' => now()]);
        NotificationService::sendForEvent($user, 'student-promotion:'.$user->id,
            "You're officially a student!",
            'Your enrollment is complete. Your student portal is ready.',
            'success', route('portal.dashboard'));
    }

    public static function pending(User $user, ?string $token = null): ?array
    {
        if (!$user->hasRole('student')) return null;
        // Keep the visible tab's display lease alive through the existing poll.
        // This never acknowledges the welcome or extends the login idle deadline.
        if ($token) {
            DB::table('student_welcomes')->where('user_id', $user->id)->whereNull('acknowledged_at')
                ->where('claim_token', $token)->update(['claimed_until' => now()->addSeconds(30)]);
        }
        $event = DB::table('student_welcomes')->where('user_id', $user->id)->whereNull('acknowledged_at')->first();
        return $event ? ['id' => $event->id, 'url' => route('portal.dashboard'),
            'available' => !$event->claimed_until || now()->gte($event->claimed_until),
            'lease_owned' => $token !== null && $event->claim_token === $token] : null;
    }

    public static function update(User $user, string $action, string $token): bool
    {
        if (!$user->hasRole('student')) return false;
        return DB::transaction(function () use ($user, $action, $token) {
            $query = DB::table('student_welcomes')->where('user_id', $user->id);
            $event = (clone $query)->lockForUpdate()->first();
            if (!$event || $event->acknowledged_at) return false;
            if ($action === 'claim') {
                if ($event->claim_token !== $token && $event->claimed_until && now()->lt($event->claimed_until)) return false;
                $query->update(['claim_token' => $token, 'claimed_until' => now()->addSeconds(30)]);
            } elseif ($action === 'release') {
                if ($event->claim_token !== $token) return false;
                $query->update(['claim_token' => null, 'claimed_until' => null]);
            } else {
                if ($event->claim_token !== $token) return false;
                $query->update(['acknowledged_at' => now(), 'claim_token' => null, 'claimed_until' => null]);
            }
            return true;
        });
    }
}
