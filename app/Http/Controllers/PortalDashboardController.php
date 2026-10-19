<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Appointment;
use App\Models\CourseSubject;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Auth;

class PortalDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $latestEnrollment = Enrollment::where('user_id', $user->id)
            ->latest()->first();

        $totalEnrollments = Enrollment::where('user_id', $user->id)->count();
        $approvedCount    = Enrollment::where('user_id', $user->id)
            ->where('status', 'approved')->count();
        $paidCount        = Enrollment::where('user_id', $user->id)
            ->where('is_paid', true)->count();
        $unreadCount      = $user->unreadNotificationsCount();

        // Subjects
        $subjects = collect();
        if ($latestEnrollment) {
            $subjects = CourseSubject::whereIn('id', $latestEnrollment->subject_ids ?? [])->get();
        }

        // Next appointment
        $nextAppointment = Appointment::with('slot')
            ->where('user_id', $user->id)
            ->where('status', 'confirmed')
            ->whereHas('slot', fn($q) => $q->whereDate('date', '>=', today()))
            ->latest()->first();

        // Enrollment timeline steps
        $timeline = [];
        $completedSteps = 0;
        if ($latestEnrollment) {
            $timeline = [
                [
                    'label'    => 'Submit Application',
                    'sublabel' => 'Fill enrollment form',
                    'done'     => true,
                    'active'   => false,
                ],
                [
                    'label'    => 'Registrar Review',
                    'sublabel' => 'Await approval',
                    'done'     => in_array($latestEnrollment->status, ['approved', 'rejected']),
                    'active'   => $latestEnrollment->status === 'pending',
                ],
                [
                    'label'    => 'Approval',
                    'sublabel' => 'Get confirmed',
                    'done'     => $latestEnrollment->status === 'approved' || $latestEnrollment->is_paid,
                    'active'   => false,
                ],
                [
                    'label'    => 'Pay Fee',
                    'sublabel' => '₱500.00',
                    'done'     => $latestEnrollment->is_paid,
                    'active'   => $latestEnrollment->status === 'approved' && !$latestEnrollment->is_paid,
                ],
                [
                    'label'    => 'Enrolled',
                    'sublabel' => 'Official student',
                    'done'     => $latestEnrollment->is_paid,
                    'active'   => false,
                ],
            ];
            $completedSteps = collect($timeline)->where('done', true)->count();
        }

        // Recent notifications (as announcements)
        $announcements = UserNotification::where('user_id', $user->id)
            ->latest()->take(3)->get();

        // Next steps based on status
        $nextSteps = $this->getNextSteps($latestEnrollment);

        return view('portal.dashboard', compact(
            'latestEnrollment', 'totalEnrollments', 'approvedCount',
            'paidCount', 'unreadCount', 'subjects', 'nextAppointment',
            'timeline', 'completedSteps', 'announcements', 'nextSteps'
        ));
    }

    private function getNextSteps($enrollment): array
    {
        if (!$enrollment) {
            return [
                ['icon' => 'fa-file-alt',      'label' => 'Submit Application',  'desc' => 'Fill out and submit your enrollment form.',        'url' => route('portal.enrollment.create'), 'active' => true],
                ['icon' => 'fa-calendar-check', 'label' => 'Book Appointment',    'desc' => 'Schedule a pickup appointment.',                   'url' => route('portal.appointments.create'), 'active' => false],
            ];
        }

        if ($enrollment->status === 'pending') {
            return [
                ['icon' => 'fa-hourglass-half', 'label' => 'Await Registrar Review', 'desc' => 'Your enrollment is being reviewed.',           'url' => route('portal.enrollment.index'), 'active' => true],
                ['icon' => 'fa-bell',            'label' => 'Check Notifications',    'desc' => 'You will be notified once approved.',          'url' => route('notifications.index'), 'active' => false],
            ];
        }

        if ($enrollment->status === 'approved' && !$enrollment->is_paid) {
            return [
                ['icon' => 'fa-money-bill-wave', 'label' => 'Pay Enrollment Fee', 'desc' => 'Proceed to Cashier and pay ₱500.00.',              'url' => route('portal.enrollment.payment-info', $enrollment), 'active' => true],
                ['icon' => 'fa-receipt',         'label' => 'Get Your Receipt',    'desc' => 'Official receipt will be issued after payment.',  'url' => route('portal.enrollment.index'), 'active' => false],
            ];
        }

        if ($enrollment->is_paid) {
            return [
                ['icon' => 'fa-calendar-plus',  'label' => 'Book Appointment',    'desc' => 'Request documents or book a meeting.',             'url' => route('portal.appointments.create'), 'active' => true],
                ['icon' => 'fa-check-circle',   'label' => 'You Are Enrolled!',   'desc' => 'Congratulations! Your enrollment is complete.',    'url' => route('portal.enrollment.index'), 'active' => false],
            ];
        }

        return [];
    }
}