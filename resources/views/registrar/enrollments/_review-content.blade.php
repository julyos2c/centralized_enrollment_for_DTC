@php
    $initials = collect(preg_split('/\s+/', trim($enrollment->user->name)))
        ->filter()
        ->map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)))
        ->take(2)
        ->implode('');
    $subjectCount = count($subjects);
@endphp

<div id="review-content">

    {{-- ── Header ─────────────────────────────────────────────────────── --}}
    <div class="dtc-review-header">
        <div class="dtc-review-identity">
            <div class="dtc-review-avatar">{{ $initials ?: '?' }}</div>
            <div class="dtc-review-identity-copy">
                <h5 class="dtc-review-name">{{ $enrollment->user->name }}</h5>
                <p class="dtc-review-email"><i data-lucide="mail"></i> {{ $enrollment->user->email }}</p>
            </div>
        </div>
        <div class="dtc-review-header-right">
            @if($enrollment->is_paid)
                <span class="dtc-status-badge is-success"><span class="dtc-status-dot"></span> Enrolled &amp; Paid</span>
            @elseif($enrollment->status === 'approved')
                <span class="dtc-status-badge is-info"><span class="dtc-status-dot"></span> Approved — Awaiting Payment</span>
            @elseif($enrollment->status === 'rejected')
                <span class="dtc-status-badge is-danger"><span class="dtc-status-dot"></span> Rejected</span>
            @else
                <span class="dtc-status-badge is-warning"><span class="dtc-status-dot"></span> Pending Review</span>
            @endif
            @if($enrollment->status === 'approved' && $enrollment->is_paid)
                <a href="{{ route('registrar.enrollments.cor.show', $enrollment) }}" class="btn btn-primary btn-sm">
                    <i data-lucide="file-badge" class="mr-1"></i> View COR
                </a>
            @endif
            <button type="button" class="dtc-review-close" data-dismiss="modal" aria-label="Close">
                <i data-lucide="x"></i>
            </button>
        </div>
    </div>

    {{-- ── Body ───────────────────────────────────────────────────────── --}}
    <div class="dtc-review-body">

        <div class="dtc-review-section">
            <div class="dtc-review-section-title">
                <span><i data-lucide="graduation-cap"></i> Enrollment Details</span>
            </div>
            <div class="dtc-review-info-grid">
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Program</span>
                    <span class="dtc-review-info-value">{{ $enrollment->program->name }}</span>
                </div>
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Year Level</span>
                    <span class="dtc-review-info-value">Year {{ $enrollment->year_level }}</span>
                </div>
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Semester</span>
                    <span class="dtc-review-info-value">Semester {{ $enrollment->semester }}</span>
                </div>
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Submitted</span>
                    <span class="dtc-review-info-value">{{ $enrollment->created_at->format('M d, Y · h:i A') }}</span>
                </div>
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Classification</span>
                    <span class="dtc-review-info-value">
                        @php $classification = $enrollment->resolveClassification(); @endphp
                        @if($classification === 'new_student')
                            <span class="dtc-status-badge is-neutral">Regular</span>
                        @else
                            <span class="dtc-status-badge is-info">{{ ucfirst(str_replace('_', ' ', $classification)) }}</span>
                        @endif
                    </span>
                </div>
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Subject Load</span>
                    <span class="dtc-review-info-value">
                        @if($enrollment->is_irregular)
                            <span class="dtc-status-badge is-warning">Irregular</span>
                        @else
                            <span class="dtc-status-badge is-neutral">Regular</span>
                        @endif
                    </span>
                </div>
            </div>
        </div>

        <div class="dtc-review-section">
            <div class="dtc-review-section-title">
                <span><i data-lucide="book"></i> Selected Subjects</span>
                <span class="dtc-review-subject-count">{{ $subjectCount }} subject{{ $subjectCount === 1 ? '' : 's' }}</span>
            </div>

            <div class="dtc-review-subject-list">
                @forelse ($subjects as $subject)
                    <div class="dtc-review-subject-item">
                        <span class="dtc-review-subject-code">{{ $subject->subject_code }}</span>
                        <span class="dtc-review-subject-name">{{ $subject->subject_name }}</span>
                        @if($enrollment->is_irregular)
                            <span class="dtc-status-badge is-neutral" style="margin-left:auto; font-size:10px;">Y{{ $subject->year_level }} · Sem {{ $subject->semester }}</span>
                        @endif
                    </div>
                @empty
                    <p class="text-muted mb-0" style="font-size:12.5px;">No subjects selected.</p>
                @endforelse
            </div>
        </div>

        {{-- Credited / Waived Subjects — documentation only, does not affect COR or payment --}}
        @if(in_array($classification, ['transferee', 'shiftee', 'cross_enrollee', 'returnee']))
        <div class="dtc-review-section">
            <div class="dtc-review-section-title">
                <span><i data-lucide="badge-check"></i> Credited Subjects</span>
                <span class="dtc-review-subject-count">{{ $enrollment->creditedSubjects->count() }} recorded</span>
            </div>
            <p class="text-muted mb-2" style="font-size:11.5px;">
                Documentation only — recording a credit here does not change the COR or the enrollment fee.
            </p>

            <div class="dtc-review-subject-list mb-3">
                @forelse ($enrollment->creditedSubjects as $credit)
                    <div class="dtc-review-subject-item" style="align-items:flex-start; flex-direction:column; gap:4px;">
                        <div style="display:flex; align-items:center; gap:8px; width:100%;">
                            <span class="dtc-review-subject-code">{{ $credit->courseSubject->subject_code }}</span>
                            <span class="dtc-review-subject-name">{{ $credit->courseSubject->subject_name }}</span>
                            <div style="margin-left:auto; display:flex; gap:6px;">
                                <button type="button" class="dtc-icon-btn js-edit-credit"
                                        title="Edit"
                                        data-url="{{ route('registrar.enrollments.credited-subjects.update', $credit) }}"
                                        data-subject-label="{{ $credit->courseSubject->subject_code }} — {{ $credit->courseSubject->subject_name }}"
                                        data-equivalent-subject="{{ $credit->equivalent_subject }}"
                                        data-equivalent-school="{{ $credit->equivalent_school }}"
                                        data-credited-units="{{ $credit->credited_units }}"
                                        data-remarks="{{ $credit->remarks }}">
                                    <i data-lucide="pencil"></i>
                                </button>
                                <form method="POST" action="{{ route('registrar.enrollments.credited-subjects.destroy', $credit) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="dtc-icon-btn danger" title="Remove"
                                            onclick="return confirm('Remove this credited subject record?')">
                                        <i data-lucide="trash-2"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div style="font-size:11.5px; color:var(--dtc-text-muted); padding-left:2px;">
                            @if($credit->equivalent_subject) Equivalent: {{ $credit->equivalent_subject }} @endif
                            @if($credit->equivalent_school) · {{ $credit->equivalent_school }} @endif
                            @if($credit->credited_units) · {{ $credit->credited_units }} unit(s) @endif
                            @if($credit->remarks) <br>{{ $credit->remarks }} @endif
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0" style="font-size:12.5px;">No credited subjects recorded.</p>
                @endforelse
            </div>

            {{-- Add credited subject --}}
            <form method="POST" action="{{ route('registrar.enrollments.credited-subjects.store', $enrollment) }}"
                  style="border:1px dashed var(--dtc-border); border-radius:10px; padding:12px;">
                @csrf
                <div class="form-row">
                    <div class="form-group col-md-6 mb-2">
                        <label class="dtc-form-label" style="font-size:11px;">DTC Subject</label>
                        <select name="course_subject_id" class="dtc-form-control" required>
                            <option value="">— Select subject —</option>
                            @foreach ($programSubjects as $ps)
                                <option value="{{ $ps->id }}">{{ $ps->subject_code }} — {{ $ps->subject_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-6 mb-2">
                        <label class="dtc-form-label" style="font-size:11px;">Credited Units</label>
                        <input type="number" name="credited_units" class="dtc-form-control" step="0.5" min="0" max="6">
                    </div>
                    <div class="form-group col-md-6 mb-2">
                        <label class="dtc-form-label" style="font-size:11px;">Equivalent Subject</label>
                        <input type="text" name="equivalent_subject" class="dtc-form-control" placeholder="e.g. Mathematics 101">
                    </div>
                    <div class="form-group col-md-6 mb-2">
                        <label class="dtc-form-label" style="font-size:11px;">Previous School</label>
                        <input type="text" name="equivalent_school" class="dtc-form-control" placeholder="e.g. ABC College">
                    </div>
                    <div class="form-group col-12 mb-2">
                        <label class="dtc-form-label" style="font-size:11px;">Remarks</label>
                        <textarea name="remarks" class="dtc-form-control" rows="2" placeholder="Optional notes"></textarea>
                    </div>
                </div>
                <button type="submit" class="dtc-btn dtc-btn-secondary" style="font-size:12px;">
                    <i data-lucide="plus"></i> Add Credited Subject
                </button>
            </form>
        </div>
        @endif

        {{-- ── Promote to Alumni ─────────────────────────────────────────── --}}
        {{-- Only shown when: enrollment approved + paid + user still has student role --}}
        @if($enrollment->status === 'approved' && $enrollment->is_paid && $enrollment->user->hasRole('student'))
        <div class="dtc-review-section" id="promote-alumni-section-{{ $enrollment->id }}">
            <div class="dtc-review-section-title">
                <span><i data-lucide="user-check"></i> Graduate Promotion</span>
            </div>

            {{-- Collapsed trigger row --}}
            <div id="promote-alumni-trigger-{{ $enrollment->id }}">
                <p style="font-size:12.5px; color:var(--dtc-text-secondary); margin-bottom:10px;">
                    Mark this student as a graduate and activate their Alumni portal access.
                </p>
                <button type="button"
                        class="dtc-btn dtc-btn-outline-primary js-promote-trigger"
                        style="font-size:12px;"
                        data-target="promote-alumni-form-{{ $enrollment->id }}">
                    <i data-lucide="graduation-cap"></i> Promote to Alumni…
                </button>
            </div>

            {{-- Inline form — hidden by default --}}
            <div id="promote-alumni-form-{{ $enrollment->id }}"
                 style="display:none; margin-top:14px; border:1px dashed var(--dtc-border); border-radius:10px; padding:14px;">
                <form method="POST"
                      action="{{ route('registrar.students.promote-alumni', $enrollment->user) }}"
                      id="promote-alumni-submit-{{ $enrollment->id }}">
                    @csrf
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-6 mb-2">
                            <label class="dtc-form-label" style="font-size:11px;">
                                Graduation Year <span class="dtc-required">*</span>
                            </label>
                            <select name="graduation_year" class="dtc-form-control" required>
                                <option value="">— Select year —</option>
                                @for($y = date('Y') + 1; $y >= 1990; $y--)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="form-group col-md-6 mb-2 d-flex" style="gap:8px; align-items:flex-end;">
                            <button type="button"
                                    class="dtc-btn dtc-btn-secondary js-promote-cancel"
                                    style="font-size:12px;"
                                    data-target="promote-alumni-form-{{ $enrollment->id }}"
                                    data-trigger="promote-alumni-trigger-{{ $enrollment->id }}">
                                Cancel
                            </button>
                            <button type="button"
                                    class="dtc-btn dtc-btn-primary"
                                    style="font-size:12px;"
                                    data-dtc-confirm
                                    data-dtc-confirm-title="Promote to Alumni?"
                                    data-dtc-confirm-message="This will change {{ $enrollment->user->name }}'s role to Alumni and record their graduation year. This action cannot be undone."
                                    data-dtc-confirm-ok="Promote"
                                    data-dtc-confirm-type="warning"
                                    data-dtc-confirm-form="#promote-alumni-submit-{{ $enrollment->id }}">
                                <i data-lucide="user-check"></i> Confirm Promotion
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @endif

        {{-- Reject reason — hidden until "Reject" is clicked --}}
        <div class="dtc-review-reject-panel" id="rejectPanel-{{ $enrollment->id }}">
            <form method="POST" action="{{ route('registrar.enrollments.reject', $enrollment) }}" id="rejectForm-{{ $enrollment->id }}">
                @csrf
                <label class="dtc-form-label" for="remarks-{{ $enrollment->id }}">
                    Reason / Remarks <span class="dtc-required">*</span>
                </label>
                <textarea name="remarks" id="remarks-{{ $enrollment->id }}" class="dtc-form-control" rows="3" required
                          placeholder="Let the applicant know why this enrollment is being rejected…"></textarea>
                <p class="dtc-form-help">This message will be shared with the applicant.</p>
            </form>
        </div>

    </div>

    {{-- ── Footer ─────────────────────────────────────────────────────── --}}
    <div class="dtc-review-footer">

        <div class="dtc-review-footer-default">
            <button type="button" class="dtc-btn dtc-btn-secondary" data-dismiss="modal">Close</button>

            <div class="dtc-review-footer-right">
                <button type="button" class="dtc-btn dtc-btn-outline-danger dtc-review-reject-trigger"
                        data-target="rejectPanel-{{ $enrollment->id }}">
                    <i data-lucide="x"></i> Reject
                </button>

                <form id="approve-enrollment-{{ $enrollment->id }}"
                      method="POST"
                      action="{{ route('registrar.enrollments.approve', $enrollment) }}">
                    @csrf
                    <button type="button"
                            class="dtc-btn dtc-btn-success"
                            data-dtc-confirm
                            data-dtc-confirm-title="Approve Enrollment?"
                            data-dtc-confirm-message="Are you sure you want to approve this enrollment? The student will be officially enrolled."
                            data-dtc-confirm-ok="Approve"
                            data-dtc-confirm-type="warning"
                            data-dtc-confirm-form="#approve-enrollment-{{ $enrollment->id }}">
                        <i data-lucide="check"></i> Approve
                    </button>
                </form>
            </div>
        </div>

        <div class="dtc-review-footer-reject u-hidden" >
            <button type="button" class="dtc-btn dtc-btn-secondary dtc-review-reject-cancel">Cancel</button>
            <button type="submit" form="rejectForm-{{ $enrollment->id }}" class="dtc-btn dtc-btn-danger">
                <i data-lucide="check"></i> Confirm Rejection
            </button>
        </div>

    </div>
</div>

{{-- Edit Credited Subject modal — shared, repopulated by JS per row --}}
<div class="modal fade" id="editCreditModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST" id="edit-credit-form" action="">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title u-text">
                        <i data-lucide="badge-check" class="mr-2" style="color:var(--dtc-primary);"></i>
                        Edit Credited Subject
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p id="edit-credit-subject-label" class="font-weight-bold u-text mb-3"></p>
                    <div class="form-group">
                        <label>Credited Units</label>
                        <input type="number" name="credited_units" id="edit-credit-units" class="form-control" step="0.5" min="0" max="6">
                    </div>
                    <div class="form-group">
                        <label>Equivalent Subject</label>
                        <input type="text" name="equivalent_subject" id="edit-credit-equiv-subject" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Previous School</label>
                        <input type="text" name="equivalent_school" id="edit-credit-equiv-school" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remarks" id="edit-credit-remarks" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="dtc-btn dtc-btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="dtc-btn dtc-btn-primary">
                        <i data-lucide="save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function initCreditedSubjectScripts() {
    document.querySelectorAll('.js-edit-credit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('edit-credit-form').action = this.dataset.url;
            document.getElementById('edit-credit-subject-label').textContent = this.dataset.subjectLabel;
            document.getElementById('edit-credit-units').value = this.dataset.creditedUnits || '';
            document.getElementById('edit-credit-equiv-subject').value = this.dataset.equivalentSubject || '';
            document.getElementById('edit-credit-equiv-school').value = this.dataset.equivalentSchool || '';
            document.getElementById('edit-credit-remarks').value = this.dataset.remarks || '';
            $('#editCreditModal').modal('show');
        });
    });
})();

// ── Promote to Alumni — inline form expand/collapse ──────────────────
document.querySelectorAll('.js-promote-trigger').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var formEl    = document.getElementById(this.dataset.target);
        var triggerEl = this.closest('[id^="promote-alumni-trigger-"]');
        if (formEl)    formEl.style.display = '';
        if (triggerEl) triggerEl.style.display = 'none';
    });
});

document.querySelectorAll('.js-promote-cancel').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var formEl    = document.getElementById(this.dataset.target);
        var triggerEl = document.getElementById(this.dataset.trigger);
        if (formEl)    formEl.style.display = 'none';
        if (triggerEl) triggerEl.style.display = '';
    });
});
</script>