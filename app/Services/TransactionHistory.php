<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Read-only normalized view of the existing enrollment and document payment records. */
class TransactionHistory
{
    public function query(int $userId, string $role, array $filters = []): Builder
    {
        $staff = in_array($role, ['admin', 'cashier', 'registrar'], true);
        $includeEnrollments = $staff || in_array($role, ['student', 'new_applicant'], true);
        $includeDocuments = $staff || in_array($role, ['alumni', 'student', 'new_applicant'], true);
        $includeAppointments = $staff || in_array($role, ['alumni', 'student', 'new_applicant'], true);
        $parts = [];

        if ($includeEnrollments) {
            $parts[] = DB::table('payments as p')
                ->join('enrollments as e', 'e.id', '=', 'p.enrollment_id')
                ->join('users as u', 'u.id', '=', 'e.user_id')
                ->leftJoin('users as processor', 'processor.id', '=', 'p.processed_by')
                ->leftJoin('users as verifier', 'verifier.id', '=', 'p.verified_by')
                ->where(function ($q) use ($staff, $userId) { if (! $staff) $q->where('e.user_id', $userId); })
                ->selectRaw("CONCAT('E-', p.id) as transaction_key, p.id as source_id, 'enrollment' as category, u.name as payer, u.email as email, CONCAT('Enrollment #', e.id) as related, p.amount as amount, p.payment_method as method, p.status as status, p.created_at as occurred_at, p.verified_at as verified_at, COALESCE(verifier.name, processor.name) as staff_name, p.reference_number as reference, e.id as enrollment_id, NULL as document_request_id, p.rejection_reason as rejection_reason");
        }
        if ($includeDocuments) {
            $parts[] = DB::table('document_requests as d')
                ->join('users as u', 'u.id', '=', 'd.user_id')
                ->leftJoin('users as verifier', 'verifier.id', '=', 'd.verified_by')
                ->leftJoin('users as rejecter', 'rejecter.id', '=', 'd.rejected_by')
                ->where('d.status', '<>', 'cancelled')
                ->where(function ($q) use ($staff, $userId) { if (! $staff) $q->where('d.user_id', $userId); })
                ->selectRaw("CONCAT('D-', d.id) as transaction_key, d.id as source_id, 'document' as category, u.name as payer, u.email as email, CONCAT('Document request #', d.id, ' — ', d.document_type) as related, COALESCE(d.amount_received, d.fee) as amount, d.payment_method as method, d.payment_status as status, COALESCE(d.payment_submitted_at, d.created_at) as occurred_at, d.verified_at as verified_at, COALESCE(verifier.name, rejecter.name) as staff_name, d.reference_number as reference, NULL as enrollment_id, d.id as document_request_id, d.payment_rejection_reason as rejection_reason");
        }
        if ($includeAppointments) {
            $parts[] = DB::table('appointment_payments as ap')
                ->join('appointments as a', 'a.id', '=', 'ap.appointment_id')
                ->join('users as u', 'u.id', '=', 'a.user_id')
                ->leftJoin('users as verifier', 'verifier.id', '=', 'ap.verified_by')
                ->where(function ($q) use ($staff, $userId) { if (! $staff) $q->where('a.user_id', $userId); })
                ->selectRaw("CONCAT('A-', ap.id) as transaction_key, ap.id as source_id, 'appointment' as category, u.name as payer, u.email as email, CONCAT('Appointment #', a.id, ' — ', a.document_type) as related, ap.amount as amount, ap.payment_method as method, ap.status as status, ap.created_at as occurred_at, ap.verified_at as verified_at, verifier.name as staff_name, ap.reference_number as reference, NULL as enrollment_id, NULL as document_request_id, ap.rejection_reason as rejection_reason");
        }

        $union = array_shift($parts);
        foreach ($parts as $part) $union->unionAll($part);
        $query = DB::query()->fromSub($union, 'transactions');
        foreach (['category', 'method', 'status'] as $filter) {
            if (! empty($filters[$filter])) $query->where($filter, $filters[$filter]);
        }
        if ($staff && ! empty($filters['staff'])) $query->where('staff_name', 'like', '%' . addcslashes($filters['staff'], '%_\\') . '%');
        if (! empty($filters['search'])) {
            $term = '%' . addcslashes($filters['search'], '%_\\') . '%';
            $query->where(function ($q) use ($term, $staff) {
                $q->where('transaction_key', 'like', $term)->orWhere('reference', 'like', $term)->orWhere('related', 'like', $term);
                if ($staff) $q->orWhere('payer', 'like', $term)->orWhere('email', 'like', $term)->orWhere('staff_name', 'like', $term);
            });
        }
        if (! empty($filters['from'])) $query->where('occurred_at', '>=', $filters['from'] . ' 00:00:00');
        if (! empty($filters['to'])) $query->where('occurred_at', '<', date('Y-m-d', strtotime($filters['to'] . ' +1 day')) . ' 00:00:00');
        return $query->orderByDesc('occurred_at')->orderByDesc('transaction_key');
    }

    public function find(int $userId, string $role, string $key): ?object
    {
        if (! preg_match('/^[EDA]-[1-9][0-9]*$/', $key)) return null;
        return $this->query($userId, $role)->where('transaction_key', $key)->first();
    }
}
