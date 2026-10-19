<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Appointment;
use App\Models\UserNotification;

class DashboardController extends Controller
{
    public function index()
    {
        $pendingEnrollments  = Enrollment::where('status', 'pending')->count();
        $pendingAppointments = Appointment::where('status', 'pending')->count();
        $approvedToday       = Enrollment::where('status', 'approved')
                                ->whereDate('updated_at', today())->count();
        $rejectedRequests    = Enrollment::where('status', 'rejected')->count();

        $recentEnrollments = Enrollment::with('user', 'program')
            ->latest()->take(5)->get();

        $recentNotifications = UserNotification::latest()->take(4)->get();

        return view('registrar.dashboard', compact(
            'pendingEnrollments',
            'pendingAppointments',
            'approvedToday',
            'rejectedRequests',
            'recentEnrollments',
            'recentNotifications'
        ));
    }
}