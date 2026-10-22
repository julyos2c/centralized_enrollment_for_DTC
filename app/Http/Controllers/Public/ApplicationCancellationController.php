<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\ApplicationCancellationRequested;
use App\Services\ApplicationCancellationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ApplicationCancellationController extends Controller
{
    public function request(Request $request, ApplicationCancellationService $service)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $token = $request->session()->get('application_confirmation_token');
        abort_unless(is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token), 404);

        $reason = trim($validated['reason']);
        if ($reason === '') {
            return back()->withInput()->withErrors(['reason' => 'Please provide a reason for the request.']);
        }

        $result = $service->requestUsingConfirmationToken($token, $reason);

        if ($result['created'] && filter_var($result['application']->email, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::to($result['application']->email)->send(new ApplicationCancellationRequested($result['application']));
            } catch (\Throwable $exception) {
                Log::warning('Application cancellation request email delivery failed.', [
                    'application_id' => $result['application']->id,
                    'exception' => get_class($exception),
                ]);
            }
        }

        return redirect()->route('public.application.success')
            ->with('success', $result['created']
                ? 'Your cancellation request was sent to the Registrar for review.'
                : 'Your cancellation request is already awaiting Registrar review.');
    }
}
