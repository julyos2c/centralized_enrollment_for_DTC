@if($latestEnrollment && $latestEnrollment->status === 'approved' && $latestEnrollment->is_paid)
                                        <span class="dtc-status-badge is-success" style="font-size:10.5px;">
                                            <i data-lucide="check" style="width:10px;height:10px;"></i> Enrolled
                                        </span>
                                    @elseif($latestEnrollment && $latestEnrollment->status === 'pending')
                                        <span class="dtc-status-badge is-warning" style="font-size:10.5px;">
                                            <i data-lucide="clock" style="width:10px;height:10px;"></i> Pending
                                        </span>
                                    @elseif($latestEnrollment && $latestEnrollment->status === 'approved' && !$latestEnrollment->is_paid && $latestEnrollment->payment && $latestEnrollment->payment->status === 'pending')
                                        <span class="dtc-status-badge is-warning" style="font-size:10.5px;">Payment Verification</span>
                                    @elseif($latestEnrollment && $latestEnrollment->status === 'approved' && !$latestEnrollment->is_paid && $latestEnrollment->payment && $latestEnrollment->payment->status === 'rejected')
                                        <span class="dtc-status-badge is-danger" style="font-size:10.5px;">Payment Rejected</span>
                                    @elseif($latestEnrollment && $latestEnrollment->status === 'approved' && !$latestEnrollment->is_paid)
                                        <span class="dtc-status-badge is-warning" style="font-size:10.5px;">Awaiting Payment</span>
                                    @elseif($latestEnrollment && $latestEnrollment->status === 'rejected')
                                        <span class="dtc-status-badge is-danger" style="font-size:10.5px;">Rejected</span>
                                    @else
                                        <span class="dtc-status-badge is-neutral" style="font-size:10.5px;">—</span>
                                    @endif