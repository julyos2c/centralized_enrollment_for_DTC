<div class="document-input">

    <label for="document_{{ $name }}">
        {{ $label }}
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
            accept=".pdf,.jpg,.jpeg,.png"
        >
    </div>

    <small class="form-text text-muted">
        PDF, JPG, JPEG, or PNG. Maximum file size: 5 MB.
        Uploading is optional.
    </small>

    @error('documents.' . $name)
        <div class="text-danger small mt-1">
            {{ $message }}
        </div>
    @enderror

</div>