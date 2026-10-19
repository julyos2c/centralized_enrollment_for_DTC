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
use App\Http\Controllers\Cashier\DashboardController as CashierDashboardController;
use App\Http\Controllers\Cashier\PaymentController as CashierPaymentController;
use App\Http\Controllers\Student\EnrollmentController;
use App\Http\Controllers\Student\AppointmentController as StudentAppointmentController;
use App\Http\Controllers\Alumni\DocumentRequestController as AlumniDocumentController;
use App\Http\Controllers\Registrar\DocumentRequestController as RegistrarDocumentController;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\Admin\ReportController;


    // ── ROOT ──────────────────────────────────────────────────────────────────
    Route::get('/', fn () => redirect()->route('login'));

    // ── AUTH ──────────────────────────────────────────────────────────────────
    Route::get('/', [AuthController::class, 'showLanding'])->name('landing');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
    Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');
   
    // ── NOTIFICATIONS (all roles) ─────────────────────────────────────────────
    Route::middleware(['auth'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    });

    // ── ADMIN ─────────────────────────────────────────────────────────────────
    Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::resource('users', UserController::class)->except(['show']);

    // programs & subject
    Route::get('/programs', [ProgramController::class, 'index'])->name('programs.index');
    Route::post('/programs', [ProgramController::class, 'store'])->name('programs.store');
    Route::delete('/programs/{program}', [ProgramController::class, 'destroy'])->name('programs.destroy');
    Route::get('/programs/{program}/subjects', [ProgramController::class, 'subjects'])->name('programs.subjects');
    Route::post('/programs/{program}/subjects', [ProgramController::class, 'storeSubject'])->name('programs.subjects.store');
    Route::delete('/subjects/{subject}', [ProgramController::class, 'destroySubject'])->name('programs.subjects.destroy');

    Route::get('/reports/enrollment', [ReportController::class, 'enrollmentReport'])->name('reports.enrollment');
    Route::get('/reports/payment',    [ReportController::class, 'paymentReport'])->name('reports.payment');
    });

    // ── REGISTRAR ─────────────────────────────────────────────────────────────
    Route::middleware(['auth', 'role:registrar|admin'])->prefix('registrar')->name('registrar.')->group(function () {
    Route::get('/dashboard', [RegistrarDashboardController::class, 'index'])->name('dashboard');

    // Enrollments
    Route::get('/enrollments', [RegistrarEnrollmentController::class, 'index'])->name('enrollments.index');
    Route::get('/enrollments/{enrollment}', [RegistrarEnrollmentController::class, 'show'])->name('enrollments.show');
    Route::post('/enrollments/{enrollment}/approve', [RegistrarEnrollmentController::class, 'approve'])->name('enrollments.approve');
    Route::post('/enrollments/{enrollment}/reject', [RegistrarEnrollmentController::class, 'reject'])->name('enrollments.reject');

    // Appointments — slots MUST come before {appointment} routes
    Route::get('/appointments/slots', [RegistrarAppointmentController::class, 'slots'])->name('appointments.slots');
    Route::post('/appointments/slots', [RegistrarAppointmentController::class, 'storeSlot'])->name('appointments.slots.store');
    Route::delete('/appointments/slots/{slot}', [RegistrarAppointmentController::class, 'deleteSlot'])->name('appointments.slots.delete');
    Route::get('/appointments', [RegistrarAppointmentController::class, 'index'])->name('appointments.index');
    Route::post('/appointments/{appointment}/confirm', [RegistrarAppointmentController::class, 'confirm'])->name('appointments.confirm');
    Route::post('/appointments/{appointment}/cancel', [RegistrarAppointmentController::class, 'cancel'])->name('appointments.cancel');
    
    // documentrequest
    Route::get('/documents', [RegistrarDocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents/{documentRequest}/status', [RegistrarDocumentController::class, 'updateStatus'])->name('documents.status');

    });

    // ── CASHIER ───────────────────────────────────────────────────────────────
    Route::middleware(['auth', 'role:cashier|admin'])->prefix('cashier')->name('cashier.')->group(function () {
    Route::get('/dashboard', [CashierDashboardController::class, 'index'])->name('dashboard');
    Route::get('/payments', [CashierPaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/{enrollment}', [CashierPaymentController::class, 'show'])->name('payments.show');
    Route::post('/payments/{enrollment}', [CashierPaymentController::class, 'store'])->name('payments.store');
    Route::get('/payments/{enrollment}/receipt', [CashierPaymentController::class, 'receipt'])->name('payments.receipt');
    });

    // ── PORTAL (Student / Alumni / New Applicant) ─────────────────────────────
    Route::middleware(['auth', 'role:student|alumni|new_applicant'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('/dashboard', [PortalDashboardController::class, 'index'])->name('dashboard');

    // Enrollments
    Route::get('/enrollment/create', [EnrollmentController::class, 'create'])->name('enrollment.create');
    Route::post('/enrollment', [EnrollmentController::class, 'store'])->name('enrollment.store');
    Route::get('/enrollment', [EnrollmentController::class, 'index'])->name('enrollment.index');
    Route::get('/enrollment/{enrollment}/payment-info', [EnrollmentController::class, 'paymentInfo'])->name('enrollment.payment-info');
    Route::get('/programs/{program}/subjects', [EnrollmentController::class, 'getSubjects'])->name('enrollment.subjects');

    // Appointments
    Route::get('/appointments/create', [StudentAppointmentController::class, 'create'])->name('appointments.create');
    Route::post('/appointments', [StudentAppointmentController::class, 'store'])->name('appointments.store');
    Route::get('/appointments', [StudentAppointmentController::class, 'index'])->name('appointments.index');
    Route::post('/appointments/{appointment}/cancel', [StudentAppointmentController::class, 'cancel'])->name('appointments.cancel');

    // documentrequest
    Route::get('/documents', [AlumniDocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/create', [AlumniDocumentController::class, 'create'])->name('documents.create');
    Route::post('/documents', [AlumniDocumentController::class, 'store'])->name('documents.store');
    });


