<?php

namespace App\Services;

use App\Mail\PortalAccountInvitationMail;
use App\Models\AuditLog;
use App\Models\PortalAccountInvitation;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

class PortalAccountProvisioningService
{
    public function provision(StudentProfile $profile, User $actor): PortalAccountInvitation
    {
        $this->authorizeStaff($actor);

        try {
            $result = DB::transaction(function () use ($profile, $actor) {
                $record = StudentProfile::whereKey($profile->id)->lockForUpdate()->firstOrFail();
                $this->assertEligible($record);

                if ($record->user_id) {
                    $linked = User::find($record->user_id);
                    if ($linked) {
                        throw ValidationException::withMessages(['account' => 'This academic record is already linked to a portal account.']);
                    }
                    throw ValidationException::withMessages(['account' => 'This record has a conflicting account link. Ask an administrator to review it.']);
                }

                if (User::where('email', $record->email)->exists()) {
                    throw ValidationException::withMessages(['account' => 'An account already uses this email. The system will not link accounts by email; resolve the identity conflict first.']);
                }
                if (StudentProfile::where('email', $record->email)->where('id', '!=', $record->id)->exists()) {
                    throw ValidationException::withMessages(['account' => 'Another academic record uses this email. Resolve the identity conflict before provisioning.']);
                }

                $role = $record->academic_status === 'alumni' ? 'alumni' : 'student';
                if (! \Spatie\Permission\Models\Role::where('name', $role)->where('guard_name', 'web')->exists()) {
                    throw ValidationException::withMessages(['account' => 'The required portal role is not configured. Contact the system administrator.']);
                }

                $name = trim(implode(' ', array_filter([$record->first_name, $record->middle_name, $record->last_name, $record->suffix])));
                $user = User::create([
                    'name' => $name,
                    'email' => $record->email,
                    'password' => Hash::make(Str::random(64)),
                    'status' => 'pending',
                ]);
                $user->assignRole($role);
                $record->user()->associate($user);
                $record->save();

                $invitation = PortalAccountInvitation::create([
                    'student_profile_id' => $record->id,
                    'user_id' => $user->id,
                    'invited_by' => $actor->id,
                    'status' => 'pending',
                ]);

                return $invitation;
            });
        } catch (ValidationException $exception) {
            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'portal_account.provisioning_blocked',
                'description' => 'Portal account provisioning was blocked by eligibility or identity checks.',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'metadata' => ['record_id' => $profile->id, 'outcome' => 'blocked'],
            ]);
            throw $exception;
        } catch (QueryException $exception) {
            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'portal_account.provisioning_blocked',
                'description' => 'Portal account provisioning was blocked by a database identity constraint.',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'metadata' => ['record_id' => $profile->id, 'outcome' => 'identity_conflict'],
            ]);
            throw ValidationException::withMessages(['account' => 'A conflicting account or record was detected. Review the identity before retrying.']);
        }

        return $this->send($result, $actor, 'portal_account.provisioned');
    }

    public function resend(PortalAccountInvitation $invitation, User $actor): PortalAccountInvitation
    {
        $this->authorizeStaff($actor);
        return $this->send($invitation, $actor, 'portal_account.invitation_resent');
    }

    private function send(PortalAccountInvitation $invitation, User $actor, string $action): PortalAccountInvitation
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $minutes = max(15, (int) config('portal_accounts.invitation_expire_minutes', 10080));
        $leaseMinutes = max(1, (int) config('portal_accounts.sending_lease_minutes', 15));

        $invitation = DB::transaction(function () use ($invitation, $actor, $tokenHash, $minutes, $leaseMinutes) {
            $locked = PortalAccountInvitation::with(['studentProfile', 'user'])
                ->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            if ($locked->consumed_at || $locked->user->email_verified_at || $locked->user->status !== 'pending') {
                throw ValidationException::withMessages(['account' => 'This portal account no longer needs an invitation.']);
            }
            if (mb_strtolower((string) $locked->studentProfile->email) !== mb_strtolower((string) $locked->user->email)) {
                throw ValidationException::withMessages(['account' => 'The account email no longer matches the academic record. Resolve the contact identity before sending an invitation.']);
            }
            $expectedRole = $locked->studentProfile->academic_status === 'alumni' ? 'alumni' : 'student';
            if ($locked->user->roles()->count() !== 1 || ! $locked->user->hasRole($expectedRole)) {
                throw ValidationException::withMessages(['account' => 'The linked portal role no longer matches this academic record. Ask an administrator to review it.']);
            }
            $recoveringStaleSend = $locked->status === 'sending'
                && (! $locked->updated_at || $locked->updated_at->lte(now()->subMinutes($leaseMinutes)));
            if (! in_array($locked->status, ['pending', 'delivery_failed', 'expired', 'revoked'], true) && ! $recoveringStaleSend) {
                throw ValidationException::withMessages(['account' => 'This invitation cannot be resent in its current state.']);
            }
            $this->assertEligible($locked->studentProfile);
            $locked->fill([
                'invited_by' => $actor->id,
                'token_hash' => $tokenHash,
                // Claim the invitation before releasing the row lock for mail delivery.
                // Concurrent resend/revoke calls must not replace this token mid-send.
                'status' => 'sending',
                'failure_category' => null,
                'expires_at' => now()->addMinutes($minutes),
                'delivered_at' => null,
                'revoked_at' => null,
            ])->save();
            return $locked;
        });

        if (! $this->mailIsConfigured()) {
            if ($this->finishSending($invitation->id, $tokenHash, [
                'status' => 'delivery_failed', 'token_hash' => null, 'failure_category' => 'mail_not_configured',
            ])) {
                $invitation->refresh();
                $this->audit($actor, 'portal_account.invitation_delivery_failed', $invitation, 'mail_not_configured');
            }
            return $invitation->fresh(['studentProfile', 'user']);
        }

        try {
            $url = rtrim((string) config('app.url'), '/').route('portal.activation.show', ['token' => $token], false);
            Mail::to($invitation->user->email)->send(new PortalAccountInvitationMail($invitation->studentProfile, $url, $minutes));
        } catch (\Throwable $exception) {
            if ($this->finishSending($invitation->id, $tokenHash, [
                'status' => 'delivery_failed', 'token_hash' => null, 'failure_category' => 'mail_delivery',
            ])) {
                $invitation->refresh();
                $this->audit($actor, 'portal_account.invitation_delivery_failed', $invitation, 'mail_delivery');
            }
            Log::warning('Portal account invitation delivery failed.');
            return $invitation->fresh(['studentProfile', 'user']);
        }

        // A revoke or stale-lease recovery may have superseded this delivery while
        // the transport was running. Only the attempt that still owns the hash can
        // make the invitation usable.
        if ($this->finishSending($invitation->id, $tokenHash, [
            'status' => 'pending', 'delivered_at' => now(), 'failure_category' => null,
        ])) {
            $invitation->refresh();
            $this->audit($actor, $action, $invitation, 'delivered');
        }

        return $invitation->fresh(['studentProfile', 'user']);
    }

    private function finishSending(int $invitationId, string $tokenHash, array $attributes): bool
    {
        return DB::transaction(function () use ($invitationId, $tokenHash, $attributes) {
            $locked = PortalAccountInvitation::whereKey($invitationId)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'sending'
                || ! hash_equals((string) $locked->token_hash, $tokenHash)) {
                return false;
            }

            $locked->update($attributes);
            return true;
        });
    }

    public function revoke(PortalAccountInvitation $invitation, User $actor): void
    {
        $this->authorizeStaff($actor);

        DB::transaction(function () use ($invitation, $actor) {
            $locked = PortalAccountInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, ['pending', 'sending', 'delivery_failed', 'expired'], true) || $locked->consumed_at) {
                throw ValidationException::withMessages(['account' => 'Only an unused invitation can be revoked.']);
            }
            $locked->update(['status' => 'revoked', 'token_hash' => null, 'revoked_at' => now()]);
            $this->audit($actor, 'portal_account.invitation_revoked', $locked, 'revoked');
        });
    }

    public function activate(string $token, string $password): bool
    {
        $tokenHash = hash('sha256', $token);
        return DB::transaction(function () use ($tokenHash, $password) {
            $invitation = PortalAccountInvitation::where('token_hash', $tokenHash)->lockForUpdate()->first();
            if (! $invitation || ! hash_equals((string) $invitation->token_hash, $tokenHash)
                || $invitation->status !== 'pending' || ! $invitation->delivered_at
                || ! $invitation->expires_at || $invitation->expires_at->isPast()
                || $invitation->consumed_at || $invitation->revoked_at) {
                if ($invitation && $invitation->status === 'pending' && $invitation->expires_at && $invitation->expires_at->isPast()) {
                    $invitation->update(['status' => 'expired', 'token_hash' => null]);
                }
                return false;
            }

            $user = User::whereKey($invitation->user_id)->lockForUpdate()->first();
            $profile = StudentProfile::whereKey($invitation->student_profile_id)->lockForUpdate()->first();
            if (! $user || ! $profile || (int) $profile->user_id !== (int) $user->id
                || $user->status !== 'pending' || $user->email_verified_at
                || ! in_array($profile->academic_status, ['active', 'alumni'], true)
                || mb_strtolower((string) $profile->email) !== mb_strtolower((string) $user->email)
                || $user->roles()->count() !== 1
                || ! $user->hasRole($profile && $profile->academic_status === 'alumni' ? 'alumni' : 'student')) {
                $invitation->update(['status' => 'blocked', 'token_hash' => null]);
                AuditLog::create([
                    'user_id' => $invitation->invited_by,
                    'action' => 'portal_account.activation_blocked',
                    'description' => 'Portal account activation was blocked by a record, status, or role consistency check.',
                    'metadata' => ['record_id' => $invitation->student_profile_id, 'invitation_id' => $invitation->id, 'outcome' => 'blocked'],
                ]);
                return false;
            }

            $user->forceFill(['password' => Hash::make($password), 'email_verified_at' => now(), 'status' => 'active'])->save();
            $invitation->update(['status' => 'consumed', 'token_hash' => null, 'consumed_at' => now()]);
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'portal_account.activated',
                'description' => 'Portal account invitation activated.',
                'metadata' => ['record_id' => $profile->id, 'invitation_id' => $invitation->id, 'invited_by' => $invitation->invited_by, 'outcome' => 'activated'],
            ]);
            return true;
        });
    }

    private function assertEligible(StudentProfile $record): void
    {
        if (! filter_var($record->email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['account' => 'Add a valid contact email to this academic record before provisioning.']);
        }
        if (! $record->student_number || ! $record->first_name || ! $record->last_name) {
            throw ValidationException::withMessages(['account' => 'The academic identity is incomplete. Add the student number and full name before provisioning.']);
        }
        if (! in_array($record->academic_status, ['active', 'alumni'], true)) {
            throw ValidationException::withMessages(['account' => 'Only active student and alumni records are eligible for portal access.']);
        }
    }

    private function authorizeStaff(User $actor): void
    {
        abort_unless($actor->hasAnyRole(['registrar', 'admin']), 403);
    }

    private function mailIsConfigured(): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        $mailer = (string) config('mail.default');
        if (in_array($mailer, ['array', 'log', 'failover'], true)) {
            return false;
        }
        $settings = config('mail.mailers.'.$mailer, []);
        if (($settings['transport'] ?? null) === 'smtp') {
            return ! empty($settings['host']) && ! empty($settings['port']);
        }
        if (($settings['transport'] ?? null) === 'sendmail') {
            return ! empty($settings['path']);
        }

        return in_array($settings['transport'] ?? null, ['ses', 'mailgun', 'postmark'], true);
    }

    private function audit(User $actor, string $action, PortalAccountInvitation $invitation, string $outcome): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => $action,
            'description' => 'Portal account invitation '.$outcome.'.',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => ['record_id' => $invitation->student_profile_id, 'invitation_id' => $invitation->id, 'status' => $invitation->status, 'outcome' => $outcome],
        ]);
    }
}
