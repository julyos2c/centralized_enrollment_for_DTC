<?php

namespace App\Http\Controllers;

use App\Services\TransactionHistory;
use Illuminate\Http\Request;

class TransactionHistoryController extends Controller
{
    public function index(Request $request, TransactionHistory $history)
    {
        $role = $this->scopeRole($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'staff' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'in:enrollment,document,appointment'],
            'method' => ['nullable', 'in:cash,walk_in,gcash,gcash_qr'],
            'status' => ['nullable', 'in:pending,pending_verification,unpaid,verified,rejected'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $transactions = $history->query($request->user()->id, $role, $filters)->paginate($filters['per_page'] ?? 15)->withQueryString();
        return view('transactions.index', compact('transactions', 'role'));
    }

    public function show(Request $request, TransactionHistory $history, string $transaction)
    {
        $role = $this->scopeRole($request);
        $record = $history->find($request->user()->id, $role, $transaction);
        abort_unless($record, 404);
        return view('transactions.show', ['transaction' => $record, 'role' => $role]);
    }

    /** The route group defines the scope; a user's role ordering must never widen portal access. */
    private function scopeRole(Request $request): string
    {
        $name = (string) $request->route()->getName();
        if (str_starts_with($name, 'portal.')) {
            $user = $request->user();
            return $user->hasRole('alumni') ? 'alumni' : ($user->hasRole('student') ? 'student' : 'new_applicant');
        }
        if (str_starts_with($name, 'admin.')) return 'admin';
        if (str_starts_with($name, 'cashier.')) return 'cashier';
        return 'registrar';
    }
}
