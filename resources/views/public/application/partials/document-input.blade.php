<div class="document-input">

    <label for="document_{{ $name }}">
        {{ $label }}
        @if(!empty($requirement) && $requirement->is_required)
            <span class="document-input-required">Required</span>
        @endif
    </label>

    @if (!empty($description))
        <div class="text-muted small mb-2">
            {{ $description }}
        </div>
    @endif

    <div class="document-input-control @error('documents.' . $name) is-invalid @enderror">
        <label for="document_{{ $name }}" class="document-input-btn">
            <i data-lucide="upload" class="mr-1" aria-hidden="true"></i>
            Choose File
        </label>

        <span class="document-input-filename" data-placeholder="No file chosen">
            No file chosen
        </span>

        <input
            type="file"
            name="documents[{{ $name }}]"
            id="document_{{ $name }}"
            class="document-input-file @error('documents.' . $name) is-invalid @enderror"
            accept="{{ !empty($requirement) ? $requirement->acceptList() : '.pdf,.jpg,.jpeg,.png' }}"
        >
    </div>

    <small class="form-text text-muted">
        @if(!empty($requirement))
            {{ $requirement->formatSummary() }}.
            {{ $requirement->is_required ? 'You may also upload it later in your admission portal.' : 'Uploading is optional.' }}
        @else
            PDF, JPG, JPEG, or PNG. Maximum file size: 5 MB.
            Uploading is optional.
        @endif
    </small>

    @error('documents.' . $name)
        <div class="text-danger small mt-1">
            {{ $message }}
        </div>
    @enderror

</div>
