@php
    // Only re-populate from old() for the form that was submitted.
    $isOldForm = old('_requirement_id') !== null
        ? (string) old('_requirement_id') === (string) ($requirement->id ?? '')
        : $requirement === null && old('name') !== null;
    $value = fn ($key, $default = null) => $isOldForm ? old($key, $default) : $default;
    $types = $isOldForm ? (array) old('applicant_types', []) : ($requirement?->applicant_types ?? []);
    $extensions = $isOldForm ? (array) old('allowed_extensions', []) : ($requirement ? $requirement->extensions() : ['pdf', 'jpg', 'jpeg', 'png']);
    $required = $isOldForm ? (bool) old('is_required') : (bool) ($requirement?->is_required);
    $active = $isOldForm ? (bool) old('is_active') : ($requirement ? $requirement->is_active : true);
@endphp
<div class="form-row">
    <div class="form-group col-md-6">
        <label for="{{ $prefix }}-name">Name</label>
        <input id="{{ $prefix }}-name" name="name" class="form-control" maxlength="150" required value="{{ $value('name', $requirement?->name) }}" placeholder="e.g. PSA Birth Certificate">
    </div>
    <div class="form-group col-md-6">
        <label for="{{ $prefix }}-description">Instructions for applicants <span class="text-muted font-weight-normal">(optional)</span></label>
        <input id="{{ $prefix }}-description" name="description" class="form-control" maxlength="500" value="{{ $value('description', $requirement?->description) }}" placeholder="e.g. Clear scan of the original">
    </div>
</div>
<div class="form-group">
    <span class="d-block font-weight-bold mb-1">Applicant types <span class="text-muted font-weight-normal">(none checked = all types)</span></span>
    <div class="adm-checkbox-grid">
        @foreach(\App\Models\AdmissionRequirement::APPLICANT_TYPES as $type => $label)
            <label><input type="checkbox" name="applicant_types[]" value="{{ $type }}" @checked(in_array($type, $types, true))> {{ $label }}</label>
        @endforeach
    </div>
</div>
<div class="form-row">
    <div class="form-group col-md-4">
        <span class="d-block font-weight-bold mb-1">Allowed file types</span>
        <div class="adm-checkbox-grid">
            @foreach(['pdf' => 'PDF', 'jpg' => 'JPG / JPEG', 'png' => 'PNG'] as $ext => $label)
                <label><input type="checkbox" name="allowed_extensions[]" value="{{ $ext }}" @checked(in_array($ext, $extensions, true))> {{ $label }}</label>
            @endforeach
        </div>
    </div>
    <div class="form-group col-md-3">
        <label for="{{ $prefix }}-size">Max file size (MB)</label>
        <input id="{{ $prefix }}-size" type="number" name="max_size_mb" class="form-control" min="0.1" max="{{ \App\Models\AdmissionRequirement::MAX_SIZE_LIMIT_KB / 1024 }}" step="0.1" required value="{{ $value('max_size_mb', $requirement ? round($requirement->max_size_kb / 1024, 1) : 5) }}">
    </div>
    <div class="form-group col-md-3">
        <label for="{{ $prefix }}-condition">Only when</label>
        <select id="{{ $prefix }}-condition" name="condition" class="form-control">
            <option value="">Always applicable</option>
            @foreach(\App\Models\AdmissionRequirement::CONDITIONS as $key => $label)
                <option value="{{ $key }}" @selected($value('condition', $requirement?->condition) === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group col-md-2">
        <label for="{{ $prefix }}-order">Display order</label>
        <input id="{{ $prefix }}-order" type="number" name="sort_order" class="form-control" min="0" max="9999" value="{{ $value('sort_order', $requirement?->sort_order ?? 100) }}">
    </div>
</div>
<div class="form-group d-flex flex-wrap" style="gap:18px">
    <label class="mb-0"><input type="hidden" name="is_required" value="0"><input type="checkbox" name="is_required" value="1" @checked($required)> Required for admission approval</label>
    <label class="mb-0"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($active)> Active</label>
</div>
