<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        View::composer('*', function ($view) {
            if (! Auth::check()) return;

            $user = Auth::user();

            // ← compute ONCE, reuse below
            $unreadCount = $user->unreadNotificationsCount();

            $notifItem = [
                'text'        => 'Notifications',
                'url'         => route('notifications.index'),
                'icon'        => 'fas fa-fw fa-bell',
                'label'       => $unreadCount ?: null,
                'label_color' => 'danger',
            ];

            $menu = [];

            if ($user->hasRole('admin')) {
                $menu = [
                    ['header' => 'ADMIN PANEL'],
                    ['text' => 'Dashboard',    'url' => route('admin.dashboard'),    'icon' => 'fas fa-fw fa-tachometer-alt'],
                    ['text' => 'Manage Users', 'url' => route('admin.users.index'),  'icon' => 'fas fa-fw fa-users'],
                    ['text' => 'Reports', 'icon' => 'fas fa-fw fa-chart-bar', 'submenu' => [
                    ['text' => 'Enrollment Report', 'url' => route('admin.reports.enrollment'), 'icon' => 'fas fa-fw fa-file-pdf'],
                    ['text' => 'Payment Report',    'url' => route('admin.reports.payment'),    'icon' => 'fas fa-fw fa-file-pdf'],
                    ]],
                    
                    ['header' => 'ACADEMIC'],
                    [
                    'text' => 'Programs & Subjects',
                    'url'  => route('admin.programs.index'),
                    'icon' => 'fas fa-fw fa-graduation-cap',
                    ],

                    ['header' => 'REGISTRAR'],
                    ['text' => 'Registrar Dashboard',  'url' => route('registrar.dashboard'),           'icon' => 'fas fa-fw fa-tachometer-alt'],
                    ['text' => 'Review Enrollments',   'url' => route('registrar.enrollments.index'),   'icon' => 'fas fa-fw fa-file-alt'],
                    ['text' => 'Appointment Requests', 'url' => route('registrar.appointments.index'),  'icon' => 'fas fa-fw fa-calendar-check'],
                    ['text' => 'Manage Slots',         'url' => route('registrar.appointments.slots'),  'icon' => 'fas fa-fw fa-clock'],
                    ['text' => 'Document Requests', 'url' => route('registrar.documents.index'), 'icon' => 'fas fa-fw fa-folder-open'],
                    
                    ['header' => 'CASHIER'],
                    ['text' => 'Cashier Dashboard', 'url' => route('cashier.dashboard'),      'icon' => 'fas fa-fw fa-tachometer-alt'],
                    ['text' => 'Process Payments',  'url' => route('cashier.payments.index'), 'icon' => 'fas fa-fw fa-money-bill-wave'],

                    ['header' => 'SYSTEM'],
                    $notifItem,
                ];
            }

            elseif ($user->hasRole('registrar')) {
                $menu = [
                    ['header' => 'REGISTRAR PANEL'],
                    ['text' => 'Dashboard',            'url' => route('registrar.dashboard'),          'icon' => 'fas fa-fw fa-tachometer-alt'],
                    ['text' => 'Review Enrollments',   'url' => route('registrar.enrollments.index'),  'icon' => 'fas fa-fw fa-file-alt'],
                    ['text' => 'Appointment Requests', 'url' => route('registrar.appointments.index'), 'icon' => 'fas fa-fw fa-calendar-check'],
                    ['text' => 'Manage Slots',         'url' => route('registrar.appointments.slots'), 'icon' => 'fas fa-fw fa-clock'],
                    ['text' => 'Document Requests', 'url' => route('registrar.documents.index'), 'icon' => 'fas fa-fw fa-folder-open'],
                    ['header' => 'SYSTEM'],
                    $notifItem,
                ];
            }

            elseif ($user->hasRole('cashier')) {
                $menu = [
                    ['header' => 'CASHIER PANEL'],
                    ['text' => 'Dashboard',        'url' => route('cashier.dashboard'),      'icon' => 'fas fa-fw fa-tachometer-alt'],
                    ['text' => 'Process Payments', 'url' => route('cashier.payments.index'), 'icon' => 'fas fa-fw fa-money-bill-wave'],

                    ['header' => 'SYSTEM'],
                    $notifItem,
                ];
            }

        elseif ($user->hasAnyRole(['student', 'alumni', 'new_applicant'])) {
            $menu = [
                ['header' => 'MY PORTAL'],
                ['text' => 'Dashboard', 'url' => route('portal.dashboard'), 'icon' => 'fas fa-fw fa-tachometer-alt'],
                [
                    'text' => 'Enrollment',
                    'icon' => 'fas fa-fw fa-file-alt',
                    'submenu' => [
                        ['text' => 'Enroll Now',           'url' => route('portal.enrollment.create'), 'icon' => 'fas fa-fw fa-plus'],
                        ['text' => 'My Enrollment Status', 'url' => route('portal.enrollment.index'),  'icon' => 'fas fa-fw fa-list'],
                    ],
                ],
                [
                    'text' => 'Appointments',
                    'icon' => 'fas fa-fw fa-calendar',
                    'submenu' => [
                        ['text' => 'Book Appointment', 'url' => route('portal.appointments.create'), 'icon' => 'fas fa-fw fa-plus'],
                        ['text' => 'My Appointments',  'url' => route('portal.appointments.index'),  'icon' => 'fas fa-fw fa-list'],
                    ],
                ],

                // ── Alumni only ──────────────────────────────
                ...($user->hasRole('alumni') ? [[
                    'text' => 'Document Requests',
                    'icon' => 'fas fa-fw fa-folder-open',
                    'submenu' => [
                        ['text' => 'Request Document', 'url' => route('portal.documents.create'), 'icon' => 'fas fa-fw fa-plus'],
                        ['text' => 'My Requests',      'url' => route('portal.documents.index'),  'icon' => 'fas fa-fw fa-list'],
                    ],
                ]] : []),

                ['header' => 'SYSTEM'],
                $notifItem,
            ];
        }
            config(['adminlte.menu' => $menu]);
        });
    }
}