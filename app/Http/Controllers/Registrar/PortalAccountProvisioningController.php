<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PortalAccountInvitation;
use App\Models\StudentProfile;
use App\Services\PortalAccountProvisioningService;
use Illuminate\Http\Request;

class PortalAccountProvisioningController extends Controller
{
    public function confirm(StudentProfile $student)
    {
        $student->load('program');
        return view('registrar.students.provision', ['record' => $student]);
    }

    public function store(Request $request, StudentProfile $student, PortalAccountProvisioningService $provisioning)
    {
        $request->validate(['confirm' => ['required', 'accepted'], 'role' => ['prohibited']]);
        $invitation = $provisioning->provision($student, $request->user());
        $message = $invitation->status === 'pending'
            ? 'Portal account created and invitation email sent.'
            : 'Portal account created, but invitation delivery failed. You can safely retry from this record.';
        return redirect()->route('registrar.students.show', $student)->with($invitation->status === 'pending' ? 'success' : 'error', $message);
    }

    public function resend(Request $request, StudentProfile $student, PortalAccountProvisioningService $provisioning)
    {
        $invitation = $student->portalInvitation;
        abort_unless($invitation, 404);
        $invitation = $provisioning->resend($invitation, $request->user());
        return redirect()->route('registrar.students.show', $student)->with($invitation->status === 'pending' ? 'success' : 'error', $invitation->status === 'pending' ? 'A new invitation email was sent.' : 'Invitation delivery failed. Check mail configuration and retry.');
    }

    public function revoke(Request $request, StudentProfile $student, PortalAccountProvisioningService $provisioning)
    {
        $request->validate(['confirm' => ['required', 'accepted']]);
        $invitation = $student->portalInvitation;
        abort_unless($invitation, 404);
        $provisioning->revoke($invitation, $request->user());
        return redirect()->route('registrar.students.show', $student)->with('success', 'The unused invitation was revoked. The pending account remains unable to sign in.');
    }
}
