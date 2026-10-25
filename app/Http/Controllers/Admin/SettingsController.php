<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Setting;

class SettingsController extends Controller
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function systemInfo(): array
    {
        try {
            $dbVersion = 'MySQL ' . DB::selectOne('SELECT VERSION() as v')->v;
        } catch (\Exception $e) {
            $dbVersion = 'MySQL';
        }

        return [
            'version'      => config('app.version', 'v1.0.0'),
            'last_updated' => now()->format('M d, Y h:i A'),
            'database'     => $dbVersion,
            'php'          => PHP_VERSION,
            'server'       => $_SERVER['SERVER_SOFTWARE'] ?? 'Apache',
        ];
    }

    private function settings(): object
    {
        $rows = Setting::all()->pluck('value', 'key');
        return (object) $rows->toArray();
    }

    // ── General Settings ──────────────────────────────────────────────────────

    /** GET /admin/settings */
    public function index()
    {
        return view('admin.settings.general', [
            'settings'   => $this->settings(),
            'systemInfo' => $this->systemInfo(),
            'activeTab'  => 'general',
        ]);
    }

    /** PUT /admin/settings */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'institution_name' => ['required', 'string', 'max:255'],
            'system_name'      => ['required', 'string', 'max:255'],
            'tagline'          => ['nullable', 'string', 'max:255'],
            'address'          => ['nullable', 'string', 'max:500'],
            'default_language' => ['required', 'in:en,fil'],
            'default_timezone' => ['required', 'string'],
            'date_format'      => ['required', 'in:m/d/Y,d/m/Y,Y-m-d'],
            'time_format'      => ['required', 'in:12,24'],
            'items_per_page'   => ['required', 'in:10,25,50,100'],
            'maintenance_mode' => ['nullable'],
            'logo'             => ['nullable', 'image', 'mimes:png', 'max:2048'],
            'favicon'          => ['nullable', 'image', 'mimes:png', 'max:512'],
        ]);

        $fields = collect($validated)->except(['logo', 'favicon', 'maintenance_mode']);
        $fields['maintenance_mode'] = $request->boolean('maintenance_mode') ? '1' : '0';

        foreach ($fields as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        if ($request->hasFile('logo')) {
            $request->file('logo')->storeAs('public/images', 'DTC-LOGO.webp');
            Setting::updateOrCreate(['key' => 'logo_url'], ['value' => asset('storage/images/DTC-LOGO.webp')]);
        }

        if ($request->hasFile('favicon')) {
            $request->file('favicon')->storeAs('public/images', 'favicon.png');
            Setting::updateOrCreate(['key' => 'favicon_url'], ['value' => asset('storage/images/favicon.png')]);
        }

        if ($request->boolean('maintenance_mode')) {
            Artisan::call('down');
        } else {
            Artisan::call('up');
        }

        \App\Models\AuditLog::create([
            'user_id'     => auth()->id(),
            'action'      => 'settings.general.updated',
            'description' => 'Updated general settings (institution info, logo, preferences).',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

        return redirect()->route('admin.settings.index')
            ->with('status', 'General settings saved successfully.');
    }

    /** DELETE /admin/settings/logo */
    public function removeLogo()
    {
        if (Storage::disk('public')->exists('images/DTC-LOGO.webp')) {
            Storage::disk('public')->delete('images/DTC-LOGO.webp');
        }

        Setting::where('key', 'logo_url')->delete();

        return redirect()->route('admin.settings.index')
            ->with('status', 'Logo removed. Reverted to the default logo.');
    }

    /** DELETE /admin/settings/favicon */
    public function removeFavicon()
    {
        if (Storage::disk('public')->exists('images/favicon.png')) {
            Storage::disk('public')->delete('images/favicon.png');
        }

        Setting::where('key', 'favicon_url')->delete();

        return redirect()->route('admin.settings.index')
            ->with('status', 'Favicon removed. Reverted to the default favicon.');
    }

    // ── Academic Settings ─────────────────────────────────────────────────────

    /** GET /admin/settings/academic */
    public function academic()
    {
        return view('admin.settings.academic', [
            'settings'      => $this->settings(),
            'activeTab'     => 'academic',
            'programsCount' => \App\Models\Program::count(),
            'subjectsCount' => \App\Models\CourseSubject::count(),
        ]);
    }

    /** PUT /admin/settings/academic */
    public function updateAcademic(Request $request)
    {
        $request->validate([
            'current_school_year' => ['required', 'string', 'max:20'],
            'current_semester'    => ['required', 'in:1st,2nd,Summer'],
            'semester_starts_on'  => ['nullable', 'required_with:semester_ends_on', 'date'],
            'semester_ends_on'    => ['nullable', 'required_with:semester_starts_on', 'date', 'after_or_equal:semester_starts_on'],
            'enrollment_open'     => ['nullable'],
            'registrar_name'      => ['nullable', 'string', 'max:255'],
        ]);

        Setting::updateOrCreate(['key' => 'current_school_year'], ['value' => $request->current_school_year]);
        Setting::updateOrCreate(['key' => 'current_semester'],    ['value' => $request->current_semester]);
        Setting::updateOrCreate(['key' => 'semester_starts_on'],  ['value' => $request->input('semester_starts_on')]);
        Setting::updateOrCreate(['key' => 'semester_ends_on'],    ['value' => $request->input('semester_ends_on')]);
        Setting::updateOrCreate(['key' => 'enrollment_open'],     ['value' => $request->boolean('enrollment_open') ? '1' : '0']);
        Setting::updateOrCreate(['key' => 'registrar_name'],      ['value' => $request->registrar_name]);

        \App\Models\AuditLog::create([
            'user_id'     => auth()->id(),
            'action'      => 'settings.academic.updated',
            'description' => "Updated academic settings — SY {$request->current_school_year}, {$request->current_semester} semester, enrollment " . ($request->boolean('enrollment_open') ? 'open' : 'closed') . '.',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

        return redirect()->route('admin.settings.academic')
            ->with('status', 'Academic settings saved successfully.');
    }

    // ── Payment Settings ──────────────────────────────────────────────────────

    /** GET /admin/settings/payment */
    public function payment()
    {
        return view('admin.settings.payment', [
            'settings'  => $this->settings(),
            'activeTab' => 'payment',
        ]);
    }

    /** PUT /admin/settings/payment */
    public function updatePayment(Request $request)
    {
        $request->validate([
            'enrollment_fee'   => ['required', 'numeric', 'min:0'],
            'document_fee_tor' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'document_fee_diploma' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'document_fee_certification' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'document_fee_true_copy' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'gcash_number'     => ['nullable', 'string', 'max:11'],
            'gcash_name'       => ['nullable', 'string', 'max:255'],
            'payment_deadline' => ['nullable', 'integer', 'min:1'],
        ]);

        foreach ([
            'enrollment_fee', 'gcash_number', 'gcash_name', 'payment_deadline',
            'document_fee_tor', 'document_fee_diploma',
            'document_fee_certification', 'document_fee_true_copy',
        ] as $key) {
            Setting::updateOrCreate(['key' => $key], ['value' => $request->input($key, '')]);
        }

        \App\Models\AuditLog::create([
            'user_id'     => auth()->id(),
            'action'      => 'settings.payment.updated',
            'description' => 'Updated payment settings (enrollment fee, document fees, GCash details, payment deadline).',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

        return redirect()->route('admin.settings.payment')
            ->with('status', 'Payment settings saved successfully.');
    }

    /** POST /admin/settings/payment/qr */
    public function uploadQr(Request $request)
    {
        $request->validate([
            'gcash_qr' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        // Delete old QR if one exists
        $existing = Setting::where('key', 'gcash_qr_path')->value('value');
        if ($existing && Storage::disk('local')->exists($existing)) {
            Storage::disk('local')->delete($existing);
        }

        $path = $request->file('gcash_qr')->store('gcash', 'local');

        Setting::updateOrCreate(['key' => 'gcash_qr_path'], ['value' => $path]);

        return redirect()->route('admin.settings.payment')
            ->with('status', 'GCash QR code updated successfully.');
    }

    // ── Notification Settings ─────────────────────────────────────────────────

    /** GET /admin/settings/notifications */
    public function notifications()
    {
        return view('admin.settings.notifications', [
            'settings'  => $this->settings(),
            'activeTab' => 'notifications',
        ]);
    }

    /** PUT /admin/settings/notifications */
    public function updateNotifications(Request $request)
    {
        foreach (['email_notifications', 'sms_notifications', 'app_notifications'] as $key) {
            Setting::updateOrCreate(['key' => $key], ['value' => $request->boolean($key) ? '1' : '0']);
        }

        \App\Models\AuditLog::create([
            'user_id'     => auth()->id(),
            'action'      => 'settings.notifications.updated',
            'description' => 'Updated notification channel settings.',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

        return redirect()->route('admin.settings.notifications')
            ->with('status', 'Notification settings saved successfully.');
    }

    // ── System Security ───────────────────────────────────────────────────────

    /** GET /admin/settings/security */
    public function security()
    {
        return view('admin.settings.security', [
            'settings'  => $this->settings(),
            'activeTab' => 'security',
        ]);
    }

    /** PUT /admin/settings/security */
    public function updateSecurity(Request $request)
    {
        $request->validate([
            'min_password_length'   => ['required', 'integer', 'min:8', 'max:32'],
            'require_special_chars' => ['nullable', 'boolean'],
            'session_timeout'       => ['required', 'integer', 'min:5', 'max:1440'],
            'max_login_attempts'    => ['required', 'integer', 'min:3', 'max:20'],
        ]);

        Setting::updateOrCreate(['key' => 'min_password_length'],   ['value' => $request->min_password_length]);
        Setting::updateOrCreate(['key' => 'require_special_chars'], ['value' => $request->boolean('require_special_chars') ? '1' : '0']);
        Setting::updateOrCreate(['key' => 'session_timeout'],       ['value' => $request->session_timeout]);
        Setting::updateOrCreate(['key' => 'max_login_attempts'],    ['value' => $request->max_login_attempts]);

        \App\Models\AuditLog::create([
            'user_id'     => auth()->id(),
            'action'      => 'settings.security.updated',
            'description' => 'Updated system security settings (password policy, session timeout, login attempts).',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

        return redirect()->route('admin.settings.security')
            ->with('status', 'Security settings saved successfully.');
    }

    // ── Backup & Restore ──────────────────────────────────────────────────────

    /** GET /admin/settings/backup */
    public function backup()
    {
        $backups = collect(Storage::files('backups'))
            ->map(fn ($file) => [
                'name' => basename($file),
                'size' => Storage::size($file),
                'date' => Storage::lastModified($file),
            ])
            ->sortByDesc('date')
            ->values();

        return view('admin.settings.backup', [
            'settings'  => $this->settings(),
            'activeTab' => 'backup',
            'backups'   => $backups,
        ]);
    }

    /** POST /admin/settings/backup/run */
    public function runBackup()
    {
        try {
            Artisan::call('backup:run');
        } catch (\Exception $e) {
            return redirect()->route('admin.settings.backup')
                ->with('error', 'Backup failed: ' . $e->getMessage());
        }

        return redirect()->route('admin.settings.backup')
            ->with('status', 'Backup completed successfully.');
    }

    /** POST /admin/settings/restore */
    public function restore(Request $request)
    {
        return redirect()->route('admin.settings.backup')
            ->with('error', 'Uploaded backup files are not accepted. Use the approved offline recovery procedure.');
    }

    /** DELETE /admin/settings/backup/delete */
    public function deleteBackup(Request $request)
    {
        $request->validate(['filename' => ['required', 'string']]);
        $path = 'backups/' . $request->filename;

        if (Storage::exists($path)) {
            Storage::delete($path);
            return redirect()->route('admin.settings.backup')
                ->with('status', 'Backup deleted.');
        }

        return redirect()->route('admin.settings.backup')
            ->with('error', 'File not found.');
    }

    /** GET /admin/settings/backup/download/{filename} */
    public function downloadBackup(string $filename)
    {
        $path = 'backups/' . $filename;
        abort_unless(Storage::exists($path), 404);
        return Storage::download($path);
    }

    /** GET /admin/settings/backup/download-latest */
    public function downloadLatestBackup()
    {
        $files = collect(Storage::files('backups'))
            ->sortByDesc(fn ($f) => Storage::lastModified($f));

        abort_if($files->isEmpty(), 404, 'No backups found.');

        return Storage::download($files->first());
    }

    // ── Audit Logs ────────────────────────────────────────────────────────────

    /** GET /admin/settings/audit */
    /** GET /admin/settings/audit-logs */
    public function auditLogs(Request $request)
    {
        $logs = \App\Models\AuditLog::with('user')
            ->when($request->filled('search'), fn ($q) =>
                $q->where(function ($q) use ($request) {
                    $q->where('description', 'like', '%' . $request->search . '%')
                      ->orWhere('action', 'like', '%' . $request->search . '%');
                })
            )
            ->when($request->filled('action'), fn ($q) =>
                $q->where('action', $request->action)
            )
            ->latest()
            ->paginate(\App\Support\TablePagination::perPage(25))->withQueryString()
            ->withQueryString();

        return view('admin.settings.audit', [
            'settings'    => $this->settings(),
            'activeTab'   => 'audit',
            'logs'        => $logs,
            'actionTypes' => \App\Models\AuditLog::select('action')->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    /** GET /admin/settings/audit/export */
    public function exportAudit()
    {
        $logs = \Spatie\Activitylog\Models\Activity::with('causer')->latest()->get();

        $csv = "ID,Description,Causer,Date\n";
        foreach ($logs as $log) {
            $csv .= implode(',', [
                $log->id,
                '"' . str_replace('"', '""', $log->description) . '"',
                '"' . optional($log->causer)->name . '"',
                $log->created_at->format('Y-m-d H:i:s'),
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="audit-logs-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }

    // ── Mail Test ─────────────────────────────────────────────────────────────

    /** POST /admin/settings/mail/test */
    public function testMail(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        try {
            \Illuminate\Support\Facades\Mail::raw(
                'This is a test email from DTC Nexus.',
                fn ($msg) => $msg->to($request->email)->subject('DTC Nexus — Mail Test')
            );
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'Test email sent successfully.']);
    }

    // ── General page alias ────────────────────────────────────────────────────

    /** GET /admin/settings/general */
    public function general()
    {
        return view('admin.settings.general', [
            'settings'   => $this->settings(),
            'systemInfo' => $this->systemInfo(),
            'activeTab'  => 'general',
        ]);
    }
}
