{{-- Document header. Expects $meta from App\Support\LegalDocuments::meta(). --}}
<header class="legal-doc-header">
    <h2 class="legal-doc-title" id="{{ $titleId ?? '' }}">{{ $meta['title'] }}</h2>
    <dl class="legal-doc-meta">
        <div><dt>Version</dt><dd>{{ $meta['version'] }}</dd></div>
        <div><dt>Effective date</dt><dd>{{ $meta['effective_date'] ?? 'Pending approval by DTC management and the Data Protection Officer' }}</dd></div>
        <div><dt>Last updated</dt><dd>{{ $meta['last_updated'] }}</dd></div>
    </dl>
</header>
