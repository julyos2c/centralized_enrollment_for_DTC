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

            $unreadCount = $user->unreadNotificationsCount();

            $notifItem = [
                'text'        => 'Notifications',
                'url'         => route('notifications.index'),
                'icon'        => 'bell',
                'label'       => $unreadCount ?: null,
                'label_color' => 'danger',
                'is_notification' => true,
            ];

            $sidebar = [];

            // ── ADMIN ─────────────────────────────────────────────────────────
            if ($user->hasRole('admin')) {

                // If we're anywhere inside Settings, swap the whole sidebar for a
                // dedicated "Settings" panel (ngrok-style context switch) instead
                // of the normal admin navigation.
                if (request()->routeIs('admin.settings.*')) {
                    $sidebar = [
                        [
                            'text'    => 'Settings',
                            'url'     => route('admin.dashboard'),
                            'icon'    => 'arrow-left',
                            'classes' => 'sidebar-context-back',
                        ],

                        ['header' => 'ACCOUNT'],
                        ['text' => 'General',          'url' => route('admin.settings.index'),         'icon' => 'settings'],
                        ['text' => 'Academic',         'url' => route('admin.settings.academic'),       'icon' => 'graduation-cap'],
                        ['text' => 'Payment',          'url' => route('admin.settings.payment'),        'icon' => 'credit-card'],
                        ['text' => 'Notifications',    'url' => route('admin.settings.notifications'),  'icon' => 'bell'],

                        ['header' => 'SYSTEM'],
                        ['text' => 'Security',         'url' => route('admin.settings.security'),       'icon' => 'shield'],
                        ['text' => 'Backup & Restore', 'url' => route('admin.settings.backup'),         'icon' => 'cloud-upload'],
                        ['text' => 'Audit Logs',       'url' => route('admin.settings.audit'),          'icon' => 'file-text'],
                    ];
                } else {
                    $sidebar = [
                        ['header' => 'ADMIN PANEL'],
                        ['text' => 'Dashboard',    'url' => route('admin.dashboard'),   'icon' => 'gauge'],
                        ['text' => 'Manage Users', 'url' => route('admin.users.index'), 'icon' => 'users'],
                        ['text' => 'Reports', 'icon' => 'chart-bar', 'submenu' => [
                            ['text' => 'Enrollment Report', 'url' => route('admin.reports.enrollment'), 'icon' => 'file-type'],
                            ['text' => 'Payment Report',    'url' => route('admin.reports.payment'),    'icon' => 'file-type'],
                        ]],

                        ['header' => 'ACADEMIC'],
                        ['text' => 'Programs & Subjects', 'url' => route('admin.programs.index'), 'icon' => 'graduation-cap'],

                        ['header' => 'REGISTRAR'],
                        ['text' => 'Registrar Dashboard',  'url' => route('registrar.dashboard'),          'icon' => 'gauge'],
                        ['text' => 'Applications',         'url' => route('registrar.applications.index'), 'icon' => 'user-plus'],
                        ['text' => 'Review Enrollments',   'url' => route('registrar.enrollments.index'),  'icon' => 'file-text'],
                        ['text' => 'Students & Alumni',    'url' => route('registrar.students.index'),     'icon' => 'users'],
                        ['text' => 'Academic Import Center','url' => route('registrar.imports.index'),      'icon' => 'upload'],
                        ['text' => 'Appointment Requests', 'url' => route('registrar.appointments.index'), 'icon' => 'calendar-check'],
                        ['text' => 'Manage Slots',         'url' => route('registrar.appointments.slots'), 'icon' => 'clock'],
                        ['text' => 'Document Requests',    'url' => route('registrar.documents.index'),    'icon' => 'folder-open'],

                        ['header' => 'CASHIER'],
                        ['text' => 'Cashier Dashboard', 'url' => route('cashier.dashboard'),      'icon' => 'gauge'],
                        ['text' => 'Process Payments',  'url' => route('cashier.payments.index'), 'icon' => 'banknote'],
                        ['text' => 'Document Fees',    'url' => route('cashier.document-payments.index'), 'icon' => 'file-check'],
                        ['text' => 'Payment History', 'url' => route('admin.transactions.index'), 'icon' => 'receipt', 'active' => ['regex:#^admin/transactions(?:/|$)#']],

                        ['header' => 'SYSTEM'],
                        $notifItem,
                        ['text' => 'Settings', 'url' => route('admin.settings.index'), 'icon' => 'settings'],
                    ];
                }
            }

            // ── REGISTRAR ─────────────────────────────────────────────────────
            elseif ($user->hasRole('registrar')) {
                $sidebar = [
                    ['header' => 'REGISTRAR PANEL'],
                    ['text' => 'Dashboard',            'url' => route('registrar.dashboard'),          'icon' => 'gauge'],
                    ['text' => 'Applications',         'url' => route('registrar.applications.index'), 'icon' => 'user-plus'],
                    ['text' => 'Review Enrollments',   'url' => route('registrar.enrollments.index'),  'icon' => 'file-text'],
                    ['text' => 'Students & Alumni',    'url' => route('registrar.students.index'),     'icon' => 'users'],
                    ['text' => 'Academic Import Center','url' => route('registrar.imports.index'),      'icon' => 'upload'],
                    ['text' => 'Appointment Requests', 'url' => route('registrar.appointments.index'), 'icon' => 'calendar-check'],
                    ['text' => 'Manage Slots',         'url' => route('registrar.appointments.slots'), 'icon' => 'clock'],
                    ['text' => 'Document Requests',    'url' => route('registrar.documents.index'),    'icon' => 'folder-open'],
                    ['text' => 'Payment History',      'url' => route('registrar.transactions.index'), 'icon' => 'receipt', 'active' => ['regex:#^registrar/transactions(?:/|$)#']],
                    ['header' => 'SYSTEM'],
                    $notifItem,
                ];
            }

            // ── CASHIER ───────────────────────────────────────────────────────
            elseif ($user->hasRole('cashier')) {
                $sidebar = [
                    ['header' => 'CASHIER PANEL'],
                    ['text' => 'Dashboard',       'url' => route('cashier.dashboard'),      'icon' => 'gauge'],
                    ['text' => 'Process Payments','url' => route('cashier.payments.index'), 'icon' => 'banknote'],
                    ['text' => 'Payment History','url' => route('cashier.transactions.index'), 'icon' => 'receipt', 'active' => ['regex:#^cashier/transactions(?:/|$)#']],
                    ['text' => 'Document Fees',  'url' => route('cashier.document-payments.index'), 'icon' => 'file-check'],
                    ['header' => 'SYSTEM'],
                    $notifItem,
                ];
            }

            // ── STUDENT — full portal access ──────────────────────────────────
            elseif ($user->hasRole('student')) {
                $sidebar = [
                    ['header' => 'MY PORTAL'],
                    ['text' => 'Dashboard', 'url' => route('portal.dashboard'), 'icon' => 'gauge'],
                    ['text' => 'Enrollment', 'icon' => 'file-text', 'submenu' => [
                        ['text' => 'Enroll Now',           'url' => route('portal.enrollment.create'), 'icon' => 'plus'],
                        ['text' => 'My Enrollment Status', 'url' => route('portal.enrollment.index'),  'icon' => 'list'],
                    ]],
                    ['text' => 'Payment History', 'url' => route('portal.transactions.index'), 'icon' => 'receipt', 'active' => ['regex:#^portal/transactions(?:/|$)#']],

                    ['text' => 'Appointments', 'icon' => 'calendar', 'submenu' => [
                        ['text' => 'Book Appointment', 'url' => route('portal.appointments.create'), 'icon' => 'plus'],
                        ['text' => 'My Appointments',  'url' => route('portal.appointments.index'),  'icon' => 'list'],
                    ]],
                    ['text' => 'Certificate of Registration', 'url' => route('portal.cor.show'), 'icon' => 'file-badge'],
                    ['header' => 'SYSTEM'],
                    $notifItem,
                ];
            }

            // ── NEW APPLICANT — enrollment only ───────────────────────────────
            elseif ($user->hasRole('new_applicant')) {
                $sidebar = [
                    ['header' => 'MY PORTAL'],
                    ['text' => 'Dashboard',      'url' => route('portal.dashboard'),          'icon' => 'gauge'],
                    ['text' => 'My Application', 'url' => route('portal.application.show'),   'icon' => 'file-text'],
                    ['text' => 'Enrollment', 'icon' => 'file-text', 'submenu' => [
                        ['text' => 'Enroll Now',           'url' => route('portal.enrollment.create'), 'icon' => 'plus'],
                        ['text' => 'My Enrollment Status', 'url' => route('portal.enrollment.index'),  'icon' => 'list'],
                    ]],
                    ['text' => 'Payment History', 'url' => route('portal.transactions.index'), 'icon' => 'receipt', 'active' => ['regex:#^portal/transactions(?:/|$)#']],
                    ['header' => 'SYSTEM'],
                    $notifItem,
                ];
            }

            // ── ALUMNI — document requests only ───────────────────────────────
            elseif ($user->hasRole('alumni')) {
                $sidebar = [
                    ['header' => 'MY PORTAL'],
                    ['text' => 'Dashboard', 'url' => route('portal.dashboard'), 'icon' => 'gauge'],
                    ['text' => 'Document Requests', 'icon' => 'folder-open', 'submenu' => [
                        ['text' => 'Request Document', 'url' => route('portal.documents.create'), 'icon' => 'plus'],
                        ['text' => 'My Requests',      'url' => route('portal.documents.index'),  'icon' => 'list'],
                    ]],
                    ['text' => 'Payment History', 'url' => route('portal.transactions.index'), 'icon' => 'receipt', 'active' => ['regex:#^portal/transactions(?:/|$)#']],
                    ['header' => 'SYSTEM'],
                    $notifItem,
                ];
            }

            if ($sidebar !== []) {
                $sidebar[] = ['text' => 'Help Center', 'url' => route('help.index'), 'icon' => 'question-circle'];
            }

            $workCounts = app(\App\Services\SidebarWorkCounts::class)->forUser($user);
            foreach ($sidebar as &$menuItem) {
                foreach ($workCounts as $route => $count) {
                    if (($menuItem['url'] ?? null) === route($route)) {
                        $menuItem['work_key'] = $route;
                        $menuItem['work_count'] = $count;
                        $menuItem['work_description'] = \App\Services\SidebarWorkCounts::LABELS[$route];
                    }
                }
            }
            unset($menuItem);
            config(['adminlte.menu' => $sidebar]);
        });
    }
}
