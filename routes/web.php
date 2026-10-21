<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PortalDashboardController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Registrar\DashboardController as RegistrarDashboardController;
use App\Http\Controllers\Registrar\EnrollmentController as RegistrarEnrollmentController;
use App\Http\Controllers\Registrar\AppointmentController as RegistrarAppointmentController;
use App\Http\Controllers\Registrar\AppointmentScheduleController;
use App\Http\Controllers\Cashier\DashboardController as CashierDashboardController;
use App\Http\Controllers\Cashier\PaymentController as CashierPaymentController;
use App\Http\Controllers\Cashier\DocumentPaymentController as CashierDocumentPaymentController;
use App\Http\Controllers\Student\EnrollmentController;
use App\Http\Controllers\Student\AppointmentController as StudentAppointmentController;
use App\Http\Controllers\Alumni\DocumentRequestController as AlumniDocumentController;
use App\Http\Controllers\Registrar\DocumentRequestController as RegistrarDocumentController;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Public\ApplicationController;
use App\Http\Controllers\PasswordSetupController;
use App\Http\Controllers\Registrar\ApplicationController as RegistrarApplicationController;
use App\Http\Controllers\Registrar\AcademicRecordController;
use App\Http\Controllers\Registrar\AcademicImportController;
use App\Http\Controllers\Portal\ApplicationController as PortalApplicationController;
use App\Http\Controllers\Portal\CorController as PortalCorController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Portal\ProfileController;
use App\Http\Controllers\TransactionHistoryController;

    // ── AUTH ──────────────────────────────────────────────────────────────────────
    Route::get('/', [AuthController::class, 'showLanding'])->name('landing');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
    Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');
    Route::middleware('guest')->group(function () {

        // ── Forgot password (request form + send email) ──────────
        Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
        Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');

        // ── Reset password (form + handle reset) ─────────────────
        Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
        Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');

    });

    // ── ACCOUNT ACTIVATION / PASSWORD SETUP ─────────────────────────────────────
    // Reuses the stock Laravel password broker (password_resets table).
    // The token is minted elsewhere (Registrar approval flow) — this only
    // redeems it.
    //
    // Deliberately NOT using this project's custom 'guest' middleware here:
    // that middleware (RedirectIfAuthenticated) redirects any already-
    // authenticated session straight to their own role dashboard before the
    // controller runs at all. Since an applicant may click this link from a
    // browser that still has an unrelated Registrar/Admin session active,
    // these routes must work independently of any auth session state.
    Route::get('/set-password/{token}', [PasswordSetupController::class, 'showForm'])->name('password.setup.show');
    Route::post('/set-password', [PasswordSetupController::class, 'store'])->name('password.setup.store');
    Route::get('/request-activation', [PasswordSetupController::class, 'requestForm'])->name('password.activation.request');

    // Resend a fresh activation link when the original has expired.
    // Throttled per-IP via Laravel's built-in 'throttle' middleware — this
    // is independent from config('auth.passwords.users.throttle') (the
    // password broker's own per-email token-creation cooldown, which is
    // NOT automatically enforced here since resend() calls
    // Password::broker()->createToken() directly rather than
    // sendResetLink()) and independent from config('auth.passwords.users.expire')
    // (how long a minted token stays valid). 5 requests per minute per IP is
    // a reasonable anti-abuse ceiling for a "resend my link" form.
    Route::post('/resend-activation', [PasswordSetupController::class, 'resend'])->name('password.activation.resend')->middleware('throttle:5,1');

    // ── PUBLIC APPLICATION / PRE-ENROLLMENT ─────────────────────────────────────
    Route::prefix('apply')->name('public.application.')->group(function () {
        Route::get('/', [ApplicationController::class, 'create'])->name('create');
        Route::get('/locations/regions', [\App\Http\Controllers\Public\ApplicationLocationController::class, 'regions'])->name('locations.regions');
        Route::get('/locations/regions/{region}/areas', [\App\Http\Controllers\Public\ApplicationLocationController::class, 'areas'])->whereNumber('region')->name('locations.areas');
        Route::get('/locations/provinces/{province}/cities', [\App\Http\Controllers\Public\ApplicationLocationController::class, 'cities'])->whereNumber('province')->name('locations.cities');
        Route::post('/', [ApplicationController::class, 'store'])->middleware('throttle:application-submission')->name('store');
        Route::get('/success', [ApplicationController::class, 'success'])->name('success');
        Route::get('/status', [ApplicationController::class, 'statusForm'])->name('status.form');
        Route::post('/status', [ApplicationController::class, 'status'])->middleware('throttle:application-status-lookup')->name('status');
    });
    
    // ── NOTIFICATIONS & SEARCH (all roles) ───────────────────────────────────────
    Route::middleware(['auth'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('/search', [SearchController::class, 'search'])->name('search');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.markAllRead');
    Route::delete('/notifications/read',        [NotificationController::class, 'destroyRead'])->name('notifications.destroyRead');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // Staff account settings. Student-facing accounts keep the /portal/profile
    // routes below so their portal URLs remain unchanged.
    Route::middleware('role:admin|registrar|cashier')->group(function () {
        Route::get('/profile',           [ProfileController::class, 'show'])->name('profile.show');
        Route::post('/profile',          [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');
    });
    });


    // ── ADMIN ─────────────────────────────────────────────────────────────────────
    Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/transactions', [TransactionHistoryController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/{transaction}', [TransactionHistoryController::class, 'show'])->where('transaction', '[EDA]-[0-9]+')->name('transactions.show');
    Route::resource('users', UserController::class)->except(['show']);
    Route::put('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.resetPassword');
    Route::get('/settings/payment/qr-preview', [EnrollmentController::class, 'showGcashQr'])->name('settings.payment.qr.preview');

    // ── SETTINGS ──────────────
    Route::get('/settings',              [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings',              [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/settings/general',      [SettingsController::class, 'general'])->name('settings.general');
    Route::delete('/settings/logo',      [SettingsController::class, 'removeLogo'])->name('settings.logo.remove');
    Route::delete('/settings/favicon',   [SettingsController::class, 'removeFavicon'])->name('settings.favicon.remove');
    Route::get('/settings/academic',     [SettingsController::class, 'academic'])->name('settings.academic');
    Route::put('/settings/academic',     [SettingsController::class, 'updateAcademic'])->name('settings.academic.update');
    Route::get('/settings/payment',      [SettingsController::class, 'payment'])->name('settings.payment');
    Route::put('/settings/payment',      [SettingsController::class, 'updatePayment'])->name('settings.payment.update');
    Route::post('/settings/payment/qr',  [SettingsController::class, 'uploadQr'])->name('settings.payment.qr');
    Route::get('/settings/notifications',[SettingsController::class, 'notifications'])->name('settings.notifications');
    Route::put('/settings/notifications',[SettingsController::class, 'updateNotifications'])->name('settings.notifications.update');
    Route::get('/settings/security',     [SettingsController::class, 'security'])->name('settings.security');
    Route::put('/settings/security',     [SettingsController::class, 'updateSecurity'])->name('settings.security.update');
    Route::get('/settings/audit-logs',   [SettingsController::class, 'auditLogs'])->name('settings.audit');
    Route::get('/settings/backup',               [BackupController::class, 'index'])->name('settings.backup');
    Route::post('/settings/backup/create',       [BackupController::class, 'backup'])->name('settings.backup.create');
    Route::post('/settings/backup/restore',      [BackupController::class, 'restore'])->name('settings.backup.restore');
    Route::get('/settings/backup/{backup}/download', [BackupController::class, 'download'])->name('settings.backup.download');
    Route::delete('/settings/backup/{backup}',   [BackupController::class, 'deleteBackup'])->name('settings.backup.delete');

    // ── PROGRAMS & SUBJECTS ────────────────────────────────────────────────────
    Route::get('/programs', [ProgramController::class, 'index'])->name('programs.index');
    Route::post('/programs', [ProgramController::class, 'store'])->name('programs.store');
    Route::delete('/programs/{program}', [ProgramController::class, 'destroy'])->name('programs.destroy');
    Route::get('/programs/{program}/subjects', [ProgramController::class, 'subjects'])->name('programs.subjects');
    Route::post('/programs/{program}/subjects', [ProgramController::class, 'storeSubject'])->name('programs.subjects.store');
    Route::delete('/subjects/{subject}', [ProgramController::class, 'destroySubject'])->name('programs.subjects.destroy');
    Route::put('/programs/{program}', [ProgramController::class, 'update'])->name('programs.update');
    Route::put('/subjects/{subject}', [ProgramController::class, 'updateSubject'])->name('programs.subjects.update');
    Route::post('/subjects/{subject}/schedule', [ProgramController::class, 'storeSchedule'])->name('programs.subjects.schedule.store');
    Route::delete('/schedules/{schedule}', [ProgramController::class, 'destroySchedule'])->name('programs.subjects.schedule.destroy');
    
    // ── REPORTS ───────────────────────────────────────────────────────────────
    Route::get('/reports/enrollment', [ReportController::class, 'enrollmentReport'])->name('reports.enrollment');
    Route::get('/reports/payment',    [ReportController::class, 'paymentReport'])->name('reports.payment');
    });

    // ── REGISTRAR ─────────────────────────────────────────────────────────────────
    Route::middleware(['auth', 'role:registrar|admin'])->prefix('registrar')->name('registrar.')->group(function () {
    Route::get('/dashboard', [RegistrarDashboardController::class, 'index'])->name('dashboard');
    Route::get('/transactions', [TransactionHistoryController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/{transaction}', [TransactionHistoryController::class, 'show'])->where('transaction', '[EDA]-[0-9]+')->name('transactions.show');

    Route::get('/students', [AcademicRecordController::class, 'index'])->name('students.index');
    Route::get('/students/create', [AcademicRecordController::class, 'create'])->name('students.create');
    Route::post('/students', [AcademicRecordController::class, 'store'])->name('students.store');
    Route::get('/students/{student}', [AcademicRecordController::class, 'show'])->name('students.show');
    Route::get('/students/{student}/edit', [AcademicRecordController::class, 'edit'])->name('students.edit');
    Route::put('/students/{student}', [AcademicRecordController::class, 'update'])->name('students.update');
    Route::get('/imports', [AcademicImportController::class, 'index'])->name('imports.index');
    Route::get('/imports/template/{type}', [AcademicImportController::class, 'template'])->name('imports.template');
    Route::post('/imports', [AcademicImportController::class, 'upload'])->middleware('throttle:10,1')->name('imports.upload');
    Route::get('/imports/{batch}', [AcademicImportController::class, 'show'])->name('imports.show');
    Route::post('/imports/{batch}/commit', [AcademicImportController::class, 'commit'])->middleware('throttle:10,1')->name('imports.commit');
    Route::post('/imports/{batch}/cancel', [AcademicImportController::class, 'cancel'])->name('imports.cancel');
    Route::get('/imports/{batch}/report', [AcademicImportController::class, 'report'])->name('imports.report');

    Route::get('/enrollments', [RegistrarEnrollmentController::class, 'index'])->name('enrollments.index');
    Route::get('/enrollments/{enrollment}', [RegistrarEnrollmentController::class, 'show'])->name('enrollments.show');
    Route::post('/enrollments/{enrollment}/approve', [RegistrarEnrollmentController::class, 'approve'])->name('enrollments.approve');
    Route::post('/enrollments/{enrollment}/reject', [RegistrarEnrollmentController::class, 'reject'])->name('enrollments.reject');
    Route::get('/enrollments/{enrollment}/cor', [RegistrarEnrollmentController::class, 'showCor'])->name('enrollments.cor.show');
    Route::get('/enrollments/{enrollment}/cor/download', [RegistrarEnrollmentController::class, 'downloadCor'])->name('enrollments.cor.download');
    Route::post('/enrollments/{enrollment}/credited-subjects', [RegistrarEnrollmentController::class, 'storeCreditedSubject'])->name('enrollments.credited-subjects.store');
    Route::post('/students/{user}/promote-alumni', [RegistrarEnrollmentController::class, 'promoteToAlumni'])->name('students.promote-alumni');
    Route::put('/credited-subjects/{creditedSubject}', [RegistrarEnrollmentController::class, 'updateCreditedSubject'])->name('enrollments.credited-subjects.update');
    Route::delete('/credited-subjects/{creditedSubject}', [RegistrarEnrollmentController::class, 'destroyCreditedSubject'])->name('enrollments.credited-subjects.destroy');

    Route::get('/appointments/slots', [RegistrarAppointmentController::class, 'slots'])->name('appointments.slots');
    Route::post('/appointments/slots', [RegistrarAppointmentController::class, 'storeSlot'])->name('appointments.slots.store');
    Route::put('/appointments/slots/{slot}', [RegistrarAppointmentController::class, 'updateSlot'])->name('appointments.slots.update');
    Route::delete('/appointments/slots/{slot}', [RegistrarAppointmentController::class, 'deleteSlot'])->name('appointments.slots.delete');
    Route::put('/appointments/slots/{slot}/status', [RegistrarAppointmentController::class, 'setSlotStatus'])->name('appointments.slots.status');
    Route::get('/appointments/schedule', [AppointmentScheduleController::class, 'index'])->name('appointments.schedule');
    Route::put('/appointments/schedule', [AppointmentScheduleController::class, 'update'])->name('appointments.schedule.update');
    Route::post('/appointments/schedule/generate', [AppointmentScheduleController::class, 'generate'])->name('appointments.schedule.generate');
    Route::get('/appointments/closures/impact', [AppointmentScheduleController::class, 'previewClosureImpact'])->name('appointments.closures.impact');
    Route::post('/appointments/closures', [AppointmentScheduleController::class, 'storeClosure'])->name('appointments.closures.store');
    Route::post('/appointments/closures/{closure}/deactivate', [AppointmentScheduleController::class, 'deactivate'])->name('appointments.closures.deactivate');
    Route::get('/appointments', [RegistrarAppointmentController::class, 'index'])->name('appointments.index');
    Route::post('/appointments/{appointment}/confirm', [RegistrarAppointmentController::class, 'confirm'])->name('appointments.confirm');
    Route::post('/appointments/{appointment}/cancel', [RegistrarAppointmentController::class, 'cancel'])->name('appointments.cancel');
    Route::post('/appointments/payments/{payment}/verify', [RegistrarAppointmentController::class, 'verifyPayment'])->name('appointments.payments.verify');
    Route::post('/appointments/payments/{payment}/reject', [RegistrarAppointmentController::class, 'rejectPayment'])->name('appointments.payments.reject');
    Route::get('/appointments/payments/{payment}/proof', [RegistrarAppointmentController::class, 'viewProof'])->name('appointments.payments.proof');

    Route::get('/documents', [RegistrarDocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents/{documentRequest}/status', [RegistrarDocumentController::class, 'updateStatus'])->name('documents.status');

    Route::get('/applications', [RegistrarApplicationController::class, 'index'])->name('applications.index');
    Route::get('/applications/{application}/documents/{document}', [RegistrarApplicationController::class, 'downloadDocument'])->name('applications.documents.show');
    Route::get('/applications/{application}', [RegistrarApplicationController::class, 'show'])->name('applications.show');
    Route::post('/applications/{application}/start-review', [RegistrarApplicationController::class, 'startReview'])->name('applications.start-review');
    Route::post('/applications/{application}/approve', [RegistrarApplicationController::class, 'approve'])->name('applications.approve');
    Route::post('/applications/{application}/reject', [RegistrarApplicationController::class, 'reject'])->name('applications.reject');
    Route::post('/applications/{application}/revision', [RegistrarApplicationController::class, 'revision'])->name('applications.revision');
    });

    // ── CASHIER ───────────────────────────────────────────────────────────────────
    Route::middleware(['auth', 'role:cashier|admin'])->prefix('cashier')->name('cashier.')->group(function () {
    Route::get('/dashboard', [CashierDashboardController::class, 'index'])->name('dashboard');
    Route::get('/transactions', [TransactionHistoryController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/{transaction}', [TransactionHistoryController::class, 'show'])->where('transaction', '[EDA]-[0-9]+')->name('transactions.show');
    Route::get('/payments', [CashierPaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/{enrollment}', [CashierPaymentController::class, 'show'])->name('payments.show');
    Route::post('/payments/{enrollment}', [CashierPaymentController::class, 'store'])->name('payments.store');
    Route::get('/payments/{enrollment}/receipt', [CashierPaymentController::class, 'receipt'])->name('payments.receipt');
    Route::get('/payments/{payment}/proof', [CashierPaymentController::class, 'viewProof'])->name('payments.proof'); 

    Route::post('/payments/{payment}/verify', [CashierPaymentController::class, 'verify'])->name('payments.verify');
    Route::post('/payments/{payment}/reject', [CashierPaymentController::class, 'reject'])->name('payments.reject');

    Route::get('/document-payments', [CashierDocumentPaymentController::class, 'index'])->name('document-payments.index');
    Route::get('/document-payments/{documentRequest}', [CashierDocumentPaymentController::class, 'show'])->name('document-payments.show');
    Route::get('/document-payments/{documentRequest}/proof', [CashierDocumentPaymentController::class, 'viewProof'])->name('document-payments.proof');
    Route::post('/document-payments/{documentRequest}/verify-walk-in', [CashierDocumentPaymentController::class, 'verifyWalkIn'])->name('document-payments.verify-walk-in');
    Route::post('/document-payments/{documentRequest}/verify-gcash', [CashierDocumentPaymentController::class, 'verifyGcash'])->name('document-payments.verify-gcash');
    Route::post('/document-payments/{documentRequest}/reject', [CashierDocumentPaymentController::class, 'reject'])->name('document-payments.reject');
    });

    // ── PORTAL (Student / Alumni / New Applicant) ─────────────────────────────────
    Route::middleware(['auth', 'role:student|alumni|new_applicant'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('/dashboard', [PortalDashboardController::class, 'index'])->name('dashboard');
    Route::get('/transactions', [TransactionHistoryController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/{transaction}', [TransactionHistoryController::class, 'show'])->where('transaction', '[EDA]-[0-9]+')->name('transactions.show');
    Route::get('/application', [PortalApplicationController::class, 'show'])->name('application.show');

    Route::get('/enrollment/create', [EnrollmentController::class, 'create'])->name('enrollment.create');
    Route::post('/enrollment', [EnrollmentController::class, 'store'])->name('enrollment.store');
    Route::get('/enrollment', [EnrollmentController::class, 'index'])->name('enrollment.index');
    Route::get('/enrollment/{enrollment}/payment-info', [EnrollmentController::class, 'paymentInfo'])->name('enrollment.payment-info');
    Route::get('/programs/{program}/subjects', [EnrollmentController::class, 'getSubjects'])->name('enrollment.subjects');
    
    Route::post('/enrollment/{enrollment}/payment/gcash', [EnrollmentController::class, 'submitGcash'])->name('enrollment.payment.gcash');
    Route::get('/payment/proof/{payment}', [EnrollmentController::class, 'viewProof'])->name('enrollment.payment.proof');
    Route::get('/payment/gcash-qr', [EnrollmentController::class, 'showGcashQr'])->name('enrollment.gcash.qr');

    Route::get('/appointments/create', [StudentAppointmentController::class, 'create'])->name('appointments.create');
    Route::post('/appointments', [StudentAppointmentController::class, 'store'])->name('appointments.store');
    Route::get('/appointments', [StudentAppointmentController::class, 'index'])->name('appointments.index');
    Route::post('/appointments/{appointment}/cancel', [StudentAppointmentController::class, 'cancel'])->name('appointments.cancel');
    Route::get('/appointments/{appointment}/edit', [StudentAppointmentController::class, 'edit'])->name('appointments.edit');
    Route::put('/appointments/{appointment}', [StudentAppointmentController::class, 'update'])->name('appointments.update');
    Route::post('/appointments/{appointment}/payment/gcash', [StudentAppointmentController::class, 'submitGcash'])->name('appointments.payment.gcash');
    Route::get('/appointments/payment/proof/{payment}', [StudentAppointmentController::class, 'viewProof'])->name('appointments.payment.proof');
    Route::post('/appointments/document-requests/{documentRequest}/payment/gcash', [StudentAppointmentController::class, 'submitDocumentGcash'])->name('appointments.document-payment.gcash');
    Route::get('/appointments/document-requests/{documentRequest}/payment/proof', [StudentAppointmentController::class, 'viewDocumentProof'])->name('appointments.document-payment.proof');
    Route::get('/appointments/payment/gcash-qr', [StudentAppointmentController::class, 'showGcashQr'])->name('appointments.gcash.qr');

    Route::middleware('role:alumni')->group(function () {
        Route::get('/documents', [AlumniDocumentController::class, 'index'])->name('documents.index');
        Route::get('/documents/create', [AlumniDocumentController::class, 'create'])->name('documents.create');
        Route::post('/documents', [AlumniDocumentController::class, 'store'])->name('documents.store');
        Route::post('/documents/{documentRequest}/pickup-appointment', [AlumniDocumentController::class, 'bookPickup'])->name('documents.pickup-appointment');
        Route::post('/documents/{documentRequest}/payment', [AlumniDocumentController::class, 'submitPayment'])->name('documents.payment.submit');
        Route::get('/documents/{documentRequest}/proof', [AlumniDocumentController::class, 'viewProof'])->name('documents.payment.proof');
        Route::get('/documents/gcash-qr', [AlumniDocumentController::class, 'showGcashQr'])->name('documents.gcash.qr');
    });

    Route::get('/cor', [PortalCorController::class, 'show'])->name('cor.show');
    Route::get('/cor/download', [PortalCorController::class, 'download'])->name('cor.download');

    // ── PROFILE SETTINGS (Student / Alumni / New Applicant) ──────────────────
    Route::get('/profile',          [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile',          [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::delete('/profile/avatar',[ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');
});
