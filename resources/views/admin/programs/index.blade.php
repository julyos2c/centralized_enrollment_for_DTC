@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Programs & Subjects')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap u-gap-10" >
        <div>
            <h4 class="mb-0 font-weight-bold u-text" >Programs & Subjects</h4>
            <p class="mb-0 u-text-secondary-sm" >
                Manage academic programs and their subjects
            </p>
        </div>
        <button type="button" class="dtc-btn dtc-btn-primary dtc-header-btn" data-toggle="modal" data-target="#addProgramModal">
            <i data-lucide="plus"></i>
            <span class="dtc-header-btn-label">Add Program</span>
        </button>
    </div>
@endsection

@section('content')


{{-- ============ PROGRAMS ============ --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="font-weight-bold u-text" >All Programs</span>
        <span  class="u-text-secondary-sm">{{ $programs->total() }} total</span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Program</th>
                        <th>Code</th>
                        <th>Subjects</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($programs as $program)
                    @php $isSelected = $selectedProgram && $selectedProgram->id === $program->id; @endphp
                    <tr class="js-program-row {{ $isSelected ? 'is-selected' : '' }}" data-program-id="{{ $program->id }}">
                        <td>
                            <div  class="u-text-sm-bold-primary">
                                {{ $program->name }}
                            </div>
                        </td>
                        <td>
                            <span class="dtc-status-badge is-neutral">{{ $program->code }}</span>
                        </td>
                        <td>
                            <span  class="u-text-sm-bold-primary">
                                {{ $program->subjects_count }}
                            </span>
                            <span style="font-size:12px; color:var(--dtc-text-muted);"> subjects</span>
                        </td>
                        <td>
                            <div  class="u-actions-gap">
                                <a href="{{ route('admin.programs.index', ['program' => $program->id]) }}"
                                   class="dtc-btn js-select-program {{ $isSelected ? 'dtc-btn-primary' : 'dtc-btn-secondary' }}"
                                   data-id="{{ $program->id }}">
                                    <i data-lucide="book"></i> Subjects
                                </a>
                                <button type="button"
                                        class="dtc-icon-btn js-edit-program"
                                        title="Edit program"
                                        data-url="{{ route('admin.programs.update', $program) }}"
                                        data-name="{{ $program->name }}"
                                        data-code="{{ $program->code }}">
                                    <i data-lucide="pencil"></i>
                                </button>
                                <form id="del-program-{{ $program->id }}"
                                      method="POST"
                                      action="{{ route('admin.programs.destroy', $program) }}">
                                    @csrf @method('DELETE')
                                    <button type="button"
                                            class="dtc-icon-btn danger"
                                            title="Delete program"
                                            data-dtc-confirm
                                            data-dtc-confirm-title="Delete Program?"
                                            data-dtc-confirm-message="Are you sure you want to delete this program? This action cannot be undone."
                                            data-dtc-confirm-ok="Delete Program"
                                            data-dtc-confirm-form="#del-program-{{ $program->id }}">
                                        <i data-lucide="trash-2"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-4 u-text-muted" >
                            No programs yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap u-gap-15" >
        <small  class="u-text-secondary">
            Showing
            <strong>{{ $programs->firstItem() ?? 0 }}</strong>
            to
            <strong>{{ $programs->lastItem() ?? 0 }}</strong>
            of
            <strong>{{ $programs->total() }}</strong>
            programs
        </small>

        @if($programs->hasPages())
        <div>
            {{ $programs->appends(request()->except('page'))->onEachSide(1)->links('pagination::bootstrap-4') }}
        </div>
        @endif
    </div>
</div>

{{-- ============ SUBJECTS ============
     Extracted to a partial so it can be re-rendered in place via AJAX
     when a program is selected, instead of a full page reload. --}}
<div id="subjects-card-wrapper">
    @include('admin.programs.partials.subjects-card')
</div>

{{-- ============ ADD PROGRAM MODAL ============ --}}
<div class="modal fade" id="addProgramModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.programs.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title u-text" >
                        <i data-lucide="plus-circle" class="mr-2" style="color:var(--dtc-primary);"></i> Add New Program
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Program Name</label>
                        <input type="text" name="name" class="form-control"
                               placeholder="e.g. BS Information Systems"
                               value="{{ old('name') }}" required>
                    </div>
                    <div class="form-group">
                        <label>Program Code</label>
                        <input type="text" name="code" class="form-control"
                               placeholder="e.g. BSIS"
                               value="{{ old('code') }}" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="dtc-btn dtc-btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="dtc-btn dtc-btn-primary">
                        <i data-lucide="plus"></i> Add Program
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============ EDIT PROGRAM MODAL ============
     One shared modal, repopulated by JS from the clicked row's
     data-* attributes. --}}
<div class="modal fade" id="editProgramModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST" id="edit-program-form" action="">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title u-text" >
                        <i data-lucide="pencil" class="mr-2" style="color:var(--dtc-primary);"></i> Edit Program
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Program Name</label>
                        <input type="text" name="name" id="edit-program-name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Program Code</label>
                        <input type="text" name="code" id="edit-program-code" class="form-control" required>
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

@if($errors->any())
<script>
    $(function(){
        @if($errors->has('subject_code') || $errors->has('subject_name') || $errors->has('year_level') || $errors->has('semester'))
            $('#addSubjectModal').modal('show');
        @elseif($errors->has('name') || $errors->has('code'))
            $('#addProgramModal').modal('show');
        @endif
    });
</script>
@endif

@section('css')
<style>
/* Selected-program row highlight - CSS-var driven so it stays correct
   in dark mode without a separate body.dtc-dark override. */
.js-program-row.is-selected {
    background: var(--dtc-surface-soft) !important;
}
.js-program-row.is-selected td {
    border-color: var(--dtc-border);
}
body.dtc-dark .js-program-row.is-selected {
    background: #334155 !important;
}
body.dtc-dark .js-program-row.is-selected td {
    border-color: #475569;
    color: var(--dtc-text);
}
body.dtc-dark .js-program-row.is-selected td > div,
body.dtc-dark .js-program-row.is-selected td > span {
    color: var(--dtc-text) !important;
}
</style>
@endsection

@section('js')
<script>
// -- Edit Program - populate modal from the clicked row's data-* ----------
document.querySelectorAll('.js-edit-program').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('edit-program-form').action = this.dataset.url;
        document.getElementById('edit-program-name').value = this.dataset.name;
        document.getElementById('edit-program-code').value = this.dataset.code;
        $('#editProgramModal').modal('show');
    });
});

// -- Select Program - swap the Subjects card via AJAX, no full reload -----
const subjectsWrapper = document.getElementById('subjects-card-wrapper');

function loadSubjectsCard(url, pushState) {
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (res) { return res.text(); })
        .then(function (html) {
            subjectsWrapper.innerHTML = html;
            // Re-run the partial's own inline script block (innerHTML-injected
            // <script> tags don't auto-execute) so edit-subject buttons work.
            subjectsWrapper.querySelectorAll('script').forEach(function (old) {
                const s = document.createElement('script');
                s.textContent = old.textContent;
                old.replaceWith(s);
            });
            if (pushState) {
                window.history.pushState({}, '', url);
            }
        })
        .catch(function () {
            // Fall back to a normal navigation if the fetch fails.
            window.location.href = url;
        });
}

document.querySelectorAll('.js-select-program').forEach(function (link) {
    link.addEventListener('click', function (e) {
        e.preventDefault();
        const url = this.getAttribute('href');
        const id = this.dataset.id;

        // Update active/inactive button styling immediately - selected
        // program's button matches the "Add Program" primary-blue style.
        document.querySelectorAll('.js-select-program').forEach(function (l) {
            l.classList.remove('dtc-btn-primary');
            l.classList.add('dtc-btn-secondary');
        });
        this.classList.remove('dtc-btn-secondary');
        this.classList.add('dtc-btn-primary');

        // Update selected row highlight.
        document.querySelectorAll('.js-program-row').forEach(function (row) {
            row.classList.toggle('is-selected', row.dataset.programId === id);
        });

        loadSubjectsCard(url, true);
    });
});

// Support browser back/forward between program selections.
window.addEventListener('popstate', function () {
    loadSubjectsCard(window.location.href, false);
});
</script>
@endsection

@endsection