{{-- Per-requirement document review. Expects $application, $requirements, $requirementSummary. --}}
@php
    $stateChips = [
        'missing' => ['Not uploaded', ''],
        'submitted' => ['Awaiting review', 'is-info'],
        'approved' => ['Approved', 'is-success'],
        'rejected' => ['Rejected', 'is-danger'],
    ];
    $reviewable = !in_array($application->status, ['rejected', 'cancelled'], true);
@endphp
<div class="card" id="requirements">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <span class="font-weight-bold">Admission Requirements</span>
            <div class="u-text-secondary-sm">
                {{ $requirementSummary['uploaded'] }} of {{ $requirementSummary['total'] }} uploaded ·
                {{ $requirementSummary['approved'] }} approved ·
                {{ $requirementSummary['awaiting_review'] }} awaiting review ·
                {{ $requirementSummary['rejected'] }} rejected
                @if($requirementSummary['required'])
                    · required approved {{ $requirementSummary['required_approved'] }}/{{ $requirementSummary['required'] }}
                @endif
            </div>
        </div>
        @if($reviewable && $requirementSummary['awaiting_review'] > 0)
            <form method="POST" action="{{ route('registrar.applications.documents.approve-pending', $application) }}" id="approve-pending-docs">
                @csrf
                <button type="button" class="adm-btn adm-btn-sm adm-btn-success"
                        data-dtc-confirm data-dtc-confirm-tone="success"
                        data-dtc-confirm-title="Approve all pending documents?"
                        data-dtc-confirm-message="{subject} document(s) awaiting review will be approved. Check each file before approving."
                        data-dtc-confirm-subject="{{ $requirementSummary['awaiting_review'] }}"
                        data-dtc-confirm-ok="Approve all" data-dtc-confirm-form="#approve-pending-docs">
                    <i data-lucide="check-check" aria-hidden="true"></i> Approve all pending
                </button>
            </form>
        @endif
    </div>
    <div class="card-body">
        @if($requirements->isEmpty())
            <div class="adm-empty">No upload requirements apply to this applicant type. Configure them under Admissions → Requirements.</div>
        @else
            <ul class="adm-req-list">
                @foreach($requirements as $item)
                    @php
                        $requirement = $item['requirement'];
                        $document = $item['document'];
                        [$chipLabel, $chipTone] = $stateChips[$item['state']] ?? [ucfirst($item['state']), ''];
                    @endphp
                    <li class="adm-req is-{{ $item['state'] }}">
                        <div class="adm-req-head">
                            <h3 class="adm-req-title">
                                {{ $requirement->name }}
                                @if(!$requirement->is_active)<small>Requirement is no longer active; file kept on record.</small>@endif
                            </h3>
                            <div class="adm-req-tags">
                                <span class="adm-chip {{ $requirement->is_required ? 'is-warning' : '' }}">{{ $requirement->is_required ? 'Required' : 'Optional' }}</span>
                                <span class="adm-chip {{ $chipTone }}">{{ $chipLabel }}</span>
                            </div>
                        </div>

                        @if($document)
                            <div class="adm-req-file">
                                <i data-lucide="file-text" aria-hidden="true"></i>
                                <span class="adm-req-file-name">{{ $document->original_name }}</span>
                                <span class="adm-req-file-meta">Uploaded {{ $document->created_at->format('M d, Y · h:i A') }}@if($document->size_label) · {{ $document->size_label }}@endif</span>
                                <a href="{{ route('registrar.applications.documents.view', [$application, $document]) }}" class="adm-btn adm-btn-sm" target="_blank" rel="noopener noreferrer" aria-label="View {{ $requirement->name }} (opens in a new tab)"><i data-lucide="eye" aria-hidden="true"></i> View</a>
                                <a href="{{ route('registrar.applications.documents.show', [$application, $document]) }}" class="adm-btn adm-btn-sm" aria-label="Download {{ $requirement->name }}"><i data-lucide="download" aria-hidden="true"></i> Download</a>
                            </div>

                            @if($document->reviewed_at)
                                <div class="adm-req-hint">{{ ucfirst($document->status) }} by {{ $document->reviewer->name ?? 'a former staff account' }} on {{ $document->reviewed_at->format('M d, Y h:i A') }}</div>
                            @endif
                            @if($item['state'] === 'rejected' && $document->review_remarks)
                                <div class="adm-req-reason"><strong>Reason sent to applicant:</strong> {{ $document->review_remarks }}</div>
                            @endif

                            @if($reviewable)
                                <div class="adm-req-actions">
                                    @if($item['state'] !== 'approved')
                                        <form method="POST" action="{{ route('registrar.applications.documents.review', [$application, $document]) }}">
                                            @csrf
                                            <input type="hidden" name="decision" value="approved">
                                            <button type="submit" class="adm-btn adm-btn-sm adm-btn-success"><i data-lucide="check" aria-hidden="true"></i> Approve</button>
                                        </form>
                                    @endif
                                    @if($item['state'] !== 'rejected')
                                        <form method="POST" action="{{ route('registrar.applications.documents.review', [$application, $document]) }}" class="adm-req-reject">
                                            @csrf
                                            <input type="hidden" name="decision" value="rejected">
                                            <label class="sr-only" for="reject-reason-{{ $document->id }}">Rejection reason for {{ $requirement->name }}</label>
                                            <input type="text" id="reject-reason-{{ $document->id }}" name="remarks" class="form-control form-control-sm" maxlength="500" required placeholder="Reason, e.g. blurry or incomplete copy">
                                            <button type="submit" class="adm-btn adm-btn-sm adm-btn-danger"><i data-lucide="x" aria-hidden="true"></i> Reject</button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        @else
                            <div class="adm-req-hint">The applicant has not uploaded this yet{{ $requirement->is_required ? ' — it is required.' : '.' }}</div>
                        @endif

                        @if($item['history']->isNotEmpty())
                            <details class="adm-req-history">
                                <summary>{{ $item['history']->count() }} earlier upload(s)</summary>
                                <ul>
                                    @foreach($item['history'] as $old)
                                        <li>
                                            <a href="{{ route('registrar.applications.documents.view', [$application, $old]) }}" target="_blank" rel="noopener noreferrer">{{ $old->original_name }}</a>
                                            — {{ $old->created_at->format('M d, Y') }}, {{ $old->status }}@if($old->review_remarks) ({{ $old->review_remarks }})@endif
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
