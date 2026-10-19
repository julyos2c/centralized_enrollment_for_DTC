<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Roles that staff may manually create through this interface.
     * Students enter via Pre-Enrollment (Registrar approval flow) or
     * Bulk Import (future). Alumni are transitioned from student accounts.
     * new_applicant is system-assigned only — never manually created.
     */
    private const STAFF_ROLES = ['admin', 'registrar', 'cashier'];

    // ── index ────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        // Single grouped query — no N+6 Blade queries
        $roleCounts = DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->select('roles.name', DB::raw('count(*) as total'))
            ->where('model_has_roles.model_type', User::class)
            ->groupBy('roles.name')
            ->pluck('total', 'name')
            ->toArray();

        // Search + filter — all server-side
        $query = User::with('roles')
            ->when($request->filled('search'), fn($q) =>
                $q->where(fn($q2) =>
                    $q2->where('name',  'like', '%' . $request->search . '%')
                       ->orWhere('email', 'like', '%' . $request->search . '%')
                )
            )
            ->when($request->filled('role'), fn($q) =>
                $q->role($request->role)
            )
            ->when($request->filled('status'), fn($q) =>
                $q->where('status', $request->status)
            )
            ->latest();

        // withQueryString() ensures pagination links preserve ?search=&role=&status=
        $users = $query->paginate(15)->withQueryString();
        $roles = Role::all();

        return view('admin.users.index', compact('users', 'roles', 'roleCounts'));
    }

    // ── store ────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            // Only staff roles may be manually created
            'role'     => ['required', Rule::in(self::STAFF_ROLES)],
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status'   => 'active',
        ]);

        $user->assignRole($validated['role']);

        return redirect()->route('admin.users.index')
            ->with('success', 'Staff account created successfully.');
    }

    // ── edit ────────────────────────────────────────────────────────────────

    /**
     * AJAX (modal): returns JSON payload the JS uses to populate the edit modal.
     * Direct browser visit (e.g. after resetPassword redirect): returns the
     * index view so the user lands back on the users list, not a JSON blob.
     */
    public function edit(Request $request, User $user)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'id'     => $user->id,
                'name'   => $user->name,
                'email'  => $user->email,
                'status' => $user->status,
                'role'   => $user->roles->first()?->name ?? '',
            ]);
        }

        // Non-AJAX fallback — redirect to the index (modal lives there)
        return redirect()->route('admin.users.index');
    }

    // ── update ───────────────────────────────────────────────────────────────

    public function update(Request $request, User $user)
    {
        $currentRole    = $user->roles->first()?->name;
        $isStaffAccount = in_array($currentRole, self::STAFF_ROLES);

        $validated = $request->validate([
            'name'   => ['required', 'string', 'max:255'],
            'email'  => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            // Role may only be changed for staff accounts.
            // Students, alumni, and new_applicants are managed through
            // their own flows — the admin UI must not bypass them.
            'role'   => $isStaffAccount
                ? ['required', Rule::in(self::STAFF_ROLES)]
                : ['nullable'],
            'status' => ['required', 'in:active,pending,locked'],
        ]);

        $user->update([
            'name'   => $validated['name'],
            'email'  => $validated['email'],
            'status' => $validated['status'],
        ]);

        // Only sync role when the account is a staff account.
        if ($isStaffAccount && !empty($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    // ── resetPassword ────────────────────────────────────────────────────────

    /**
     * Reset a user's password to an admin-supplied temporary value.
     *
     * Accepts `password` + `password_confirmation` (standard Laravel pair).
     * Password is hashed before storage and never logged or returned in plain text.
     * The admin is expected to communicate the temporary password to the user
     * out-of-band; the user should change it on their next login.
     *
     * Route: PUT /admin/users/{user}/reset-password
     * Name:  admin.users.resetPassword
     */
    public function resetPassword(Request $request, User $user)
    {
        $request->validate([
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Password for {$user->name} has been reset successfully.");
    }

    // ── destroy ──────────────────────────────────────────────────────────────

    public function destroy(User $user)
    {
        // Server-side self-delete guard — never rely on the UI hiding the button.
        // A crafted DELETE request must also be blocked here.
        if ($user->id === auth()->id()) {
            abort(403, 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User removed.');
    }
}