@props(['name', 'label' => 'Upload file', 'accept' => '.jpg,.jpeg,.png,.pdf', 'hint' => 'PDF, JPG, PNG • Maximum 5 MB'])
<label class="dtc-file-upload" for="{{ $name }}">
    <input id="{{ $name }}" name="{{ $name }}" type="file" accept="{{ $accept }}" {{ $attributes }}>
    <span class="dtc-file-upload-icon"><i data-lucide="cloud-upload"></i></span>
    <span class="dtc-file-upload-title">{{ $label }}</span>
    <span class="dtc-file-upload-hint">{{ $hint }}</span>
</label>
