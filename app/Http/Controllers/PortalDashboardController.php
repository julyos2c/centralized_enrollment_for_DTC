<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Appointment;
use App\Models\CourseSubject;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PortalDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->hasRole('new_applicant')) {
            return $this->newApplicantDashboard($user);
        }

        if ($user->hasRole('alumni')) {
            return $this->alumniDashboard($user);
        }

        return $this->studentDashboard($user);
    }

    private function newApplicantDashboard($user)
    {
        $application = Application::where('user_id', $user->id)
            ->with(['program', 'reviewer'])
            ->withCount('documents')
            ->latest()
            ->first();

        $enrollment = Enrollment::where('user_id', $user->id)
            ->with('payment')
            ->latest()
            ->first();

        $unreadCount = $user->unreadNotificationsCount();

        $announcements = UserNotification::where('user_id', $user->id)
            ->latest()->take(3)->get();

        $fee = Setting::get('enrollment_fee', 500);
        $nextStep = $this->getNewApplicantNextStep($application, $enrollment, $fee);

        return view('portal.new-applicant.dashboard', compact(
            'application', 'enrollment', 'unreadCount', 'announcements', 'nextStep'
        ));
    }

    private function getNewApplicantNextStep($application, $enrollment, $fee): array
    {
        if (! $application) {
            return [
                'label' => 'No Approved Application Found',
                'desc'  => 'Please contact the Registrar\'s Office if you believe this is an error.',
                'url'   => null,
                'icon'  => 'alert-triangle',
            ];
        }

        if ($application->status !== 'approved') {
            if ($application->status === 'revision_required') {
                return [
                    'label' => 'Review Required Corrections',
                    'desc'  => 'Review the Registrar\'s remarks in your application and follow the provided guidance.',
                    'url'   => route('portal.application.show'),
                    'icon'  => 'alert-triangle',
                ];
            }

            if ($application->status === 'rejected') {
                return [
                    'label' => 'Review Application Decision',
                    'desc'  => 'Read the Registrar\'s decision and remarks. Contact the Registrar\'s Office if you need guidance.',
                    'url'   => route('portal.application.show'),
                    'icon'  => 'file-text',
                ];
            }

            return [
                'label' => 'Await Registrar Review',
                'desc'  => 'Your pre-enrollment application is awaiting a decision. Check its status for updates.',
                'url'   => route('portal.application.show'),
                'icon'  => 'hourglass',
            ];
        }

        if (! $enrollment) {
            return [
                'label' => 'Proceed to Official Enrollment',
                'desc'  => 'Your pre-enrollment application is approved. Complete your official enrollment to continue.',
                'url'   => route('portal.enrollment.create'),
                'icon'  => 'arrow-right',
            ];
        }

        if ($enrollment->status === 'pending') {
            return [
                'label' => 'Wait for Registrar Approval',
                'desc'  => 'Your official enrollment has been submitted and is under review.',
                'url'   => route('portal.enrollment.index'),
                'icon'  => 'hourglass',
            ];
        }

        if ($enrollment->status === 'approved' && ! $enrollment->is_paid) {
            if ($enrollment->payment && $enrollment->payment->status === 'pending') {
                return [
                    'label' => 'Wait for Payment Verification',
                    'desc'  => 'The Cashier is reviewing your submitted payment proof.',
                    'url'   => route('portal.enrollment.index'),
                    'icon'  => 'hourglass',
                ];
            }

            if ($enrollment->payment && $enrollment->payment->status === 'rejected') {
                return [
                    'label'     => 'Submit Corrected Payment Proof',
                    'desc'      => 'Review the Cashier\'s feedback and upload a corrected proof.',
                    'url'       => route('portal.enrollment.payment-info', $enrollment),
                    'icon'      => 'rotate-ccw',
                    'isPayment' => true,
                ];
            }

            return [
                'label'     => 'Proceed to Payment',
                'desc'      => 'Your official enrollment is approved. Pay the ₱' . number_format($fee, 2) . ' fee at the Cashier\'s Office.',
                'url'       => route('portal.enrollment.payment-info', $enrollment),
                'icon'      => 'banknote',
                'isPayment' => true,
            ];
        }

        if ($enrollment->is_paid) {
            return [
                'label' => 'Enrollment Completed',
                'desc'  => 'Congratulations! Your official enrollment is complete and paid.',
                'url'   => route('portal.enrollment.index'),
                'icon'  => 'check-circle',
            ];
        }

        return [
            'label' => 'View Enrollment Status',
            'desc'  => 'Check the current status of your official enrollment.',
            'url'   => route('portal.enrollment.index'),
            'icon'  => 'list',
        ];
    }

    private function alumniDashboard($user)
    {
        $requests = DocumentRequest::where('user_id', $user->id)
            ->latest()->get();

        $totalRequests = $requests->count();
        $readyRequests = $requests->filter(fn ($item) => $item->status === 'ready' && $item->is_paid && $item->payment_status === 'verified')->count();
        $releasedDocs  = $requests->where('status', 'released')->count();
        $totalFees     = $requests->sum('fee');

        $recentRequests = $requests->take(5);

        $nextAppointment = Appointment::with('slot')
            ->where('user_id', $user->id)
            ->where('status', 'confirmed')
            ->whereHas('slot', fn($q) => $q->whereDate('date', '>=', today()))
            ->latest()->first();

        $unreadCount = $user->unreadNotificationsCount();

        $announcements = UserNotification::where('user_id', $user->id)
            ->latest()->take(3)->get();

        // ── New: load the student profile so the view can show Batch YYYY ──
        $studentProfile = $user->studentProfile;
        $latestRequest = $recentRequests->first();

        return view('portal.alumni.dashboard', compact(
            'totalRequests', 'readyRequests', 'releasedDocs', 'totalFees',
            'recentRequests', 'nextAppointment', 'unreadCount', 'announcements',
            'studentProfile', 'latestRequest'
        ));
    }

    private function studentDashboard($user)
    {
        $latestEnrollment = Enrollment::where('user_id', $user->id)
            ->with('payment')
            ->latest()->first();

        $totalEnrollments = Enrollment::where('user_id', $user->id)->count();
        $approvedCount    = Enrollment::where('user_id', $user->id)
            ->where('status', 'approved')->count();
        $paidCount        = Enrollment::where('user_id', $user->id)
            ->where('is_paid', true)->count();
        $unreadCount      = $user->unreadNotificationsCount();

        $subjects = collect();
        if ($latestEnrollment) {
            $subjects = CourseSubject::whereIn('id', $latestEnrollment->subject_ids ?? [])->get();
        }

        $nextAppointment = Appointment::with('slot')
            ->where('user_id', $user->id)
            ->where('status', 'confirmed')
            ->whereHas('slot', fn($q) => $q->whereDate('date', '>=', today()))
            ->latest()->first();

        $fee = Setting::get('enrollment_fee', 500);
        $currentSchoolYear = Setting::get('current_school_year');
        $currentSemester = Setting::get('current_semester');
        $semesterNumbers = ['1st' => 1, '2nd' => 2, 'Summer' => 3];
        $timezone = Setting::get('default_timezone', config('app.timezone', 'Asia/Manila'));
        $semesterStartsOn = Setting::get('semester_starts_on');
        $semesterEndsOn = Setting::get('semester_ends_on');
        $termStatus = 'dates_missing';

        if ($semesterStartsOn && $semesterEndsOn) {
            $today = Carbon::today($timezone);
            if ($today->lt(Carbon::parse($semesterStartsOn, $timezone)->startOfDay())) {
                $termStatus = 'upcoming';
            } elseif ($today->gt(Carbon::parse($semesterEndsOn, $timezone)->endOfDay())) {
                $termStatus = 'ended';
            } else {
                $termStatus = 'in_progress';
            }
        }

        $currentTermEnrollment = null;
        if ($currentSchoolYear && isset($semesterNumbers[$currentSemester])) {
            $currentTermEnrollment = Enrollment::where('user_id', $user->id)
                ->where('school_year', $currentSchoolYear)
                ->where('semester', $semesterNumbers[$currentSemester])
                ->with('payment')
                ->latest()
                ->first();
        }
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
                    'sublabel' => $latestEnrollment->status === 'rejected' ? 'Decision: rejected' : 'Await approval',
                    'done'     => $latestEnrollment->status === 'approved',
                    'active'   => $latestEnrollment->status === 'pending',
                    'rejected' => $latestEnrollment->status === 'rejected',
                ],
                [
                    'label'    => 'Approval',
                    'sublabel' => 'Get confirmed',
                    'done'     => $latestEnrollment->status === 'approved',
                    'active'   => false,
                ],
                [
                    'label'    => 'Pay Fee',
                    'sublabel' => $latestEnrollment->payment && $latestEnrollment->payment->status === 'pending'
                        ? 'Payment proof awaiting verification'
                        : ($latestEnrollment->payment && $latestEnrollment->payment->status === 'rejected'
                            ? 'Payment proof rejected — submit a corrected proof'
                            : '₱' . number_format($fee, 2)),
                    'done'     => $latestEnrollment->status === 'approved' && $latestEnrollment->is_paid,
                    'active'   => $latestEnrollment->status === 'approved' && !$latestEnrollment->is_paid,
                ],
                [
                    'label'    => 'Enrolled',
                    'sublabel' => 'Official student',
                    'done'     => $latestEnrollment->status === 'approved' && $latestEnrollment->is_paid,
                    'active'   => false,
                ],
            ];
            $completedSteps = collect($timeline)->where('done', true)->count();
        } else {
            $timeline = [
                ['label' => 'Submit Application', 'sublabel' => 'Application not started', 'done' => false, 'active' => true],
                ['label' => 'Registrar Review', 'sublabel' => 'Await submission', 'done' => false, 'active' => false],
                ['label' => 'Approval', 'sublabel' => 'Await review', 'done' => false, 'active' => false],
                ['label' => 'Pay Fee', 'sublabel' => 'After approval', 'done' => false, 'active' => false],
                ['label' => 'Enrolled', 'sublabel' => 'After payment confirmation', 'done' => false, 'active' => false],
            ];
        }

        $announcements = UserNotification::where('user_id', $user->id)
            ->latest()->take(3)->get();

        $nextSteps = $this->getNextSteps($latestEnrollment, $fee);

        // ── New: load the student profile so the view can show Enrolled AY ──
        $studentProfile = $user->studentProfile;

        return view('portal.student.dashboard', compact(
            'latestEnrollment', 'totalEnrollments', 'approvedCount',
            'paidCount', 'unreadCount', 'subjects', 'nextAppointment',
            'timeline', 'completedSteps', 'announcements', 'nextSteps', 'fee',
            'studentProfile', 'currentSchoolYear', 'currentSemester', 'semesterStartsOn',
            'semesterEndsOn', 'termStatus', 'currentTermEnrollment'
        ));
    }

    private function getNextSteps($enrollment, $fee): array
    {
        if (!$enrollment) {
            return [
                ['icon' => 'file-text',      'label' => 'Submit Application',  'desc' => 'Fill out and submit your enrollment form.',        'url' => route('portal.enrollment.create'), 'active' => true],
                ['icon' => 'calendar-check', 'label' => 'Book Appointment',    'desc' => 'Schedule a pickup appointment.',                   'url' => route('portal.appointments.create'), 'active' => false],
            ];
        }

        if ($enrollment->status === 'pending') {
            return [
                ['icon' => 'hourglass',  'label' => 'Await Registrar Review', 'desc' => 'Your enrollment is being reviewed.',           'url' => route('portal.enrollment.index'), 'active' => true],
                ['icon' => 'bell',       'label' => 'Check Notifications',    'desc' => 'You will be notified once approved.',          'url' => route('notifications.index'), 'active' => false],
            ];
        }

        if ($enrollment->status === 'rejected') {
            return [
                ['icon' => 'rotate-ccw', 'label' => 'Correct and Resubmit', 'desc' => 'Review the Registrar’s feedback, update your enrollment, and submit it again.', 'url' => route('portal.enrollment.create'), 'active' => true],
                ['icon' => 'file-text', 'label' => 'Review Enrollment History', 'desc' => 'See the decision and feedback for this submission.', 'url' => route('portal.enrollment.index'), 'active' => false],
            ];
        }

        if ($enrollment->status === 'approved' && !$enrollment->is_paid) {
            $paymentRejected = $enrollment->payment && $enrollment->payment->status === 'rejected';

            return [
                ['icon' => $paymentRejected ? 'rotate-ccw' : 'banknote', 'label' => $paymentRejected ? 'Resubmit Payment Proof' : 'Pay Enrollment Fee', 'desc' => $paymentRejected ? 'Review the rejection feedback and submit a corrected payment proof.' : 'Proceed to Cashier and pay ₱' . number_format($fee, 2) . '.', 'url' => route('portal.enrollment.payment-info', $enrollment), 'active' => true, 'isPayment' => true],
                ['icon' => 'receipt',  'label' => 'Get Your Receipt',    'desc' => 'Official receipt will be issued after payment.',  'url' => route('portal.enrollment.index'), 'active' => false],
            ];
        }

        if ($enrollment->is_paid) {
            return [
                ['icon' => 'calendar-plus',  'label' => 'Book Appointment',    'desc' => 'Request documents or book a meeting.',             'url' => route('portal.appointments.create'), 'active' => true],
                ['icon' => 'check-circle',   'label' => 'You Are Enrolled!',   'desc' => 'Congratulations! Your enrollment is complete.',    'url' => route('portal.enrollment.index'), 'active' => false],
            ];
        }

        return [];
    }
}
