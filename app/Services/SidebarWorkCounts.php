<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Appointment;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\User;

class SidebarWorkCounts
{
    public const LABELS = [
        'registrar.applications.index' => 'applications awaiting review',
        'registrar.enrollments.index' => 'enrollments awaiting review',
        'registrar.appointments.index' => 'appointments awaiting confirmation',
        'registrar.documents.index' => 'document requests awaiting processing',
        'cashier.payments.index' => 'enrollments awaiting payment collection or verification',
        'cashier.document-payments.index' => 'document fees awaiting collection or verification',
    ];

    public function forUser(User $user): array
    {
        // The view composer runs for many partials. Cache only within this request.
        $key = 'sidebar_work_counts.'.$user->id;
        if (request()->attributes->has($key)) return request()->attributes->get($key);
        $counts = [];
        // Queues are shared by authorized staff, not assigned per staff account.
        if ($user->hasAnyRole(['admin', 'registrar'])) {
            $counts['registrar.applications.index'] = Application::where('status', 'submitted')->count();
            $counts['registrar.enrollments.index'] = Enrollment::where('status', 'pending')->count();
            $counts['registrar.appointments.index'] = Appointment::where('status', 'pending')->count();
            $counts['registrar.documents.index'] = DocumentRequest::where('status', 'submitted')->count();
        }
        if ($user->hasAnyRole(['admin', 'cashier'])) {
            $counts['cashier.payments.index'] = Enrollment::where('status', 'approved')->where('is_paid', false)
                ->whereDoesntHave('payment', fn ($query) => $query->where('status', 'verified'))->count();
            $counts['cashier.document-payments.index'] = DocumentRequest::whereNotIn('status', ['cancelled', 'released'])
                ->whereIn('payment_status', ['unpaid', 'pending_verification'])->count();
        }
        request()->attributes->set($key, $counts);
        return $counts;
    }
}
