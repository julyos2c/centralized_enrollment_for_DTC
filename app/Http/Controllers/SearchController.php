<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Enrollment;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $query   = trim($request->get('q', ''));
        $results = [];

        if (strlen($query) < 2) {
            return response()->json(['results' => [], 'query' => $query]);
        }

        $user = Auth::user();

        // ── Pages (always shown, role-based) ──────────────────────────
        $pages = $this->getPages($user);
        foreach ($pages as $page) {
            if (str_contains(strtolower($page['title']), strtolower($query))) {
                $results[] = [
                    'type'  => 'page',
                    'icon'  => $page['icon'],
                    'title' => $page['title'],
                    'desc'  => $page['desc'],
                    'url'   => $page['url'],
                ];
            }
        }

        // ── Users (Admin + Registrar only) ────────────────────────────
        if ($user->hasAnyRole(['admin', 'registrar'])) {
            $users = User::where('name', 'LIKE', "%{$query}%")
                ->orWhere('email', 'LIKE', "%{$query}%")
                ->take(4)->get();

            foreach ($users as $u) {
                $results[] = [
                    'type'   => 'user',
                    'icon'   => 'user',
                    'title'  => $u->name,
                    'desc'   => $u->email . ' — ' . ucfirst(str_replace('_', ' ', $u->roles->first()?->name ?? 'No role')),
                    'url'    => $user->hasRole('admin') ? route('admin.users.edit', $u) : '#',
                    'avatar' => strtoupper(substr($u->name, 0, 1)),
                ];
            }
        }

        // ── Enrollments ────────────────────────────────────────────────
        if ($user->hasAnyRole(['admin', 'registrar', 'cashier'])) {
            $enrollments = Enrollment::with('user', 'program')
                ->whereHas('user', fn($q) => $q->where('name', 'LIKE', "%{$query}%"))
                ->take(3)->get();

            foreach ($enrollments as $e) {
                $results[] = [
                    'type'  => 'enrollment',
                    'icon'  => 'file-text',
                    'title' => $e->user->name . ' — Enrollment',
                    'desc'  => $e->program->name . ' · Year ' . $e->year_level . ' Sem ' . $e->semester . ' · ' . ucfirst($e->status),
                    'url'   => $user->hasRole('registrar') || $user->hasRole('admin')
                        ? route('registrar.enrollments.show', $e)
                        : route('cashier.payments.index'),
                ];
            }
        }

        // ── Appointments ───────────────────────────────────────────────
        if ($user->hasAnyRole(['admin', 'registrar'])) {
            $appointments = Appointment::with('user', 'slot')
                ->whereHas('user', fn($q) => $q->where('name', 'LIKE', "%{$query}%"))
                ->take(3)->get();

            foreach ($appointments as $a) {
                $results[] = [
                    'type'  => 'appointment',
                    'icon'  => 'calendar-check',
                    'title' => $a->user->name . ' — Appointment',
                    'desc'  => ucfirst($a->document_type) . ' · ' . \Carbon\Carbon::parse($a->slot->date)->format('M d, Y') . ' · ' . ucfirst($a->status),
                    'url'   => route('registrar.appointments.index'),
                ];
            }
        }

        return response()->json([
            'results' => array_slice($results, 0, 8),
            'query'   => $query,
        ]);
    }

    private function getPages(User $user): array
    {
        $pages = [];

        if ($user->hasRole('admin')) {
            $pages = [
                ['title'=>'Dashboard',          'desc'=>'Admin overview',              'icon'=>'layout-dashboard',  'url'=>route('admin.dashboard')],
                ['title'=>'Manage Users',        'desc'=>'Create and manage accounts', 'icon'=>'users',             'url'=>route('admin.users.index')],
                ['title'=>'Programs & Subjects', 'desc'=>'Manage academic programs',   'icon'=>'graduation-cap',    'url'=>route('admin.programs.index')],
                ['title'=>'Review Enrollments',  'desc'=>'Registrar — Approve/reject', 'icon'=>'file-text',         'url'=>route('registrar.enrollments.index')],
                ['title'=>'Appointment Requests','desc'=>'Registrar — Manage bookings','icon'=>'calendar-check',    'url'=>route('registrar.appointments.index')],
                ['title'=>'Manage Slots',        'desc'=>'Set appointment slots',      'icon'=>'clock',             'url'=>route('registrar.appointments.slots')],
                ['title'=>'Process Payments',    'desc'=>'Cashier — Collect fees',     'icon'=>'banknote',          'url'=>route('cashier.payments.index')],
                ['title'=>'Notifications',       'desc'=>'View all notifications',     'icon'=>'bell',              'url'=>route('notifications.index')],
                ['title'=>'Settings',            'desc'=>'System configuration',       'icon'=>'settings',          'url'=>route('admin.settings.index')],
                ['title'=>'Backup & Restore',    'desc'=>'Database backup',            'icon'=>'database',          'url'=>route('admin.settings.backup')],
                ['title'=>'Audit Logs',          'desc'=>'View system activities',     'icon'=>'scroll-text',       'url'=>route('admin.settings.audit')],
            ];
        } elseif ($user->hasRole('registrar')) {
            $pages = [
                ['title'=>'Dashboard',           'desc'=>'Registrar overview',         'icon'=>'layout-dashboard',  'url'=>route('registrar.dashboard')],
                ['title'=>'Review Enrollments',  'desc'=>'Approve/reject enrollments', 'icon'=>'file-text',         'url'=>route('registrar.enrollments.index')],
                ['title'=>'Appointment Requests','desc'=>'Confirm/cancel bookings',    'icon'=>'calendar-check',    'url'=>route('registrar.appointments.index')],
                ['title'=>'Manage Slots',        'desc'=>'Set available time slots',   'icon'=>'clock',             'url'=>route('registrar.appointments.slots')],
                ['title'=>'Document Requests',   'desc'=>'Alumni document requests',   'icon'=>'folder-open',       'url'=>route('registrar.documents.index')],
                ['title'=>'Notifications',       'desc'=>'View all notifications',     'icon'=>'bell',              'url'=>route('notifications.index')],
            ];
        } elseif ($user->hasRole('cashier')) {
            $pages = [
                ['title'=>'Dashboard',           'desc'=>'Cashier overview',           'icon'=>'layout-dashboard',  'url'=>route('cashier.dashboard')],
                ['title'=>'Process Payments',    'desc'=>'Collect enrollment fees',    'icon'=>'banknote',          'url'=>route('cashier.payments.index')],
                ['title'=>'Notifications',       'desc'=>'View all notifications',     'icon'=>'bell',              'url'=>route('notifications.index')],
            ];
        } else {
            // Student / Alumni / Applicant
            $pages = [
                ['title'=>'Dashboard',           'desc'=>'My portal overview',         'icon'=>'layout-dashboard',  'url'=>route('portal.dashboard')],
                ['title'=>'Enroll Now',          'desc'=>'Submit enrollment form',     'icon'=>'file-text',         'url'=>route('portal.enrollment.create')],
                ['title'=>'My Enrollment Status','desc'=>'Track enrollment progress',  'icon'=>'list',              'url'=>route('portal.enrollment.index')],
                ['title'=>'Book Appointment',    'desc'=>'Schedule document pickup',   'icon'=>'calendar-plus',     'url'=>route('portal.appointments.create')],
                ['title'=>'My Appointments',     'desc'=>'View my bookings',           'icon'=>'calendar',          'url'=>route('portal.appointments.index')],
                ['title'=>'Notifications',       'desc'=>'View all notifications',     'icon'=>'bell',              'url'=>route('notifications.index')],
            ];
        }

        return $pages;
    }
}
