<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class HelpCenterController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'category' => ['nullable', 'string', 'max:40']]);
        $query = trim($validated['q'] ?? '');
        $category = $validated['category'] ?? '';
        $user = $request->user();
        $staff = $user->hasAnyRole(['admin', 'registrar', 'cashier']);
        $topics = [];
        $add = function ($category, $title, $answer, $route, $label) use (&$topics) {
            $topics[] = compact('category', 'title', 'answer', 'route', 'label');
        };

        $add('Account', 'How do I update my account?', 'Open Profile Settings to update your details or password. Changing your email requires verification of the new address before normal account access resumes.', $staff ? 'profile.show' : 'portal.profile.show', 'Profile Settings');
        $add('Account', 'Where can I find updates?', 'Notifications contain updates about your account and requests. Open the relevant enrollment, document or payment page to check its current status.', 'notifications.index', 'Notifications');

        // Filter content and destinations on the server, before searching or rendering.
        if ($user->hasAnyRole(['student', 'new_applicant']) && !$staff) {
            $add('Enrollment', 'Does application approval complete enrollment?', 'Application approval allows you to begin enrollment. Submit your subject selection, wait for the Registrar’s review, then follow the payment instructions shown in your account.', 'portal.enrollment.index', 'My enrollment');
            $add('Enrollment', 'Should I choose regular or irregular subjects?', 'Regular subjects follow the selected year and semester. Irregular selection allows subjects across year levels or semesters. This choice does not change your admission classification. Ask the Registrar about transfer credits before selecting subjects.', 'portal.enrollment.create', 'Enrollment');
            $add('Payments', 'Where do I check enrollment payments?', 'Check your enrollment status and payment history. A submitted GCash proof is not a verified payment. If proof is rejected, read the reason before submitting a correction.', 'portal.transactions.index', 'Payment history');
        }
        if ($user->hasAnyRole(['student', 'alumni']) && !$staff) {
            $add('Documents', 'How do I request documents?', 'Choose your documents and quantities, select an available pickup time, and follow the payment instructions. Fees are shown in the request summary. The Registrar confirms the booking; confirmation does not mean the documents have been released.', 'portal.appointments.create', 'Request documents');
            $add('Appointments', 'What if my pickup time has passed?', 'For a pending booking, use Edit booking to select a future time. For a confirmed booking, contact the Registrar to arrange the next pickup step. Passing the scheduled time does not automatically cancel your documents or remove payment records.', 'portal.appointments.index', 'My appointments');
            $add('Payments', 'What does No Payment Required mean?', 'A zero-fee request does not need payment proof or Cashier verification. The Registrar still prepares and releases the documents. A bundle with payable documents charges only its payable items.', 'portal.appointments.index', 'My appointments');
            $add('Payments', 'How do I pay with GCash?', 'Open Payment details for your request. Use the configured QR code or account details, copy the displayed amount, and follow the proof instructions. Wait for Cashier verification. If a proof is rejected, read the reason before resubmitting.', 'portal.appointments.index', 'Payment details');
        }
        if ($user->hasAnyRole(['admin', 'registrar'])) {
            $add('Registrar', 'When can documents be released?', 'Follow the document status sequence from submitted to processing, ready, and released. Release requires verified payment or a legitimately recorded zero fee. Check each document in a multi-document booking.', 'registrar.documents.index', 'Document requests');
            $add('Registrar', 'How do I handle a passed booking?', 'A past slot cannot be newly confirmed. Pending users can select an available future slot. Coordinate the next pickup step for confirmed bookings while preserving document and payment records.', 'registrar.appointments.index', 'Appointment requests');
        }
        if ($user->hasAnyRole(['admin', 'cashier'])) {
            $add('Cashier', 'How should bundled document payments be verified?', 'Verify the payable bundle with its exact recorded amount. Free documents do not need collection or verification. Review rejected proofs and preserve existing transaction records.', 'cashier.document-payments.index', 'Document fees');
        }
        if ($user->hasRole('admin')) {
            $add('Administration', 'Where do I maintain registrar contact details?', 'Set the official registrar email, phone, office location and office hours in General Settings. Leave unavailable details blank. Users see these details when they need help arranging pickup.', 'admin.settings.index', 'General Settings');
        }

        $categories = collect($topics)->pluck('category')->unique()->values();
        $topics = collect($topics)->filter(fn ($topic) => ($category === '' || $topic['category'] === $category) && ($query === '' || mb_stripos(implode(' ', [$topic['category'], $topic['title'], $topic['answer']]), $query) !== false))->values();
        $contact = Setting::whereIn('key', ['registrar_email', 'registrar_phone', 'registrar_office', 'registrar_hours'])->pluck('value', 'key');
        $email = filter_var($contact->get('registrar_email'), FILTER_VALIDATE_EMAIL) ?: null;

        return view('help.index', compact('topics', 'query', 'contact', 'email', 'categories', 'category'));
    }
}
