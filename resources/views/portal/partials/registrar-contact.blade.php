@php
    $registrarContact = \App\Models\Setting::whereIn('key', ['registrar_email', 'registrar_phone', 'registrar_office', 'registrar_hours'])->pluck('value', 'key');
    $registrarEmail = filter_var($registrarContact->get('registrar_email'), FILTER_VALIDATE_EMAIL) ?: null;
@endphp
<div class="modal fade dr-edit-modal" id="registrarContactModal" tabindex="-1" aria-labelledby="registrarContactTitle" aria-hidden="true" data-registrar-contact data-email="{{ $registrarEmail }}">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="registrarContactTitle">Contact the Registrar</h2>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close contact details">&times;</button>
            </div>
            <div class="modal-body">
                <p class="dr-edit-hint">Ask about rescheduling or the next pickup step. Your documents and payment records stay intact.</p>
                <div class="dr-edit-documents my-3">
                    <div><strong>Booking reference</strong><span data-contact-reference></span></div>
                    @if($registrarEmail)<div><strong>Email</strong><span class="text-break">{{ $registrarEmail }}</span></div>@endif
                    @foreach(['registrar_phone' => 'Phone', 'registrar_office' => 'Office', 'registrar_hours' => 'Office hours'] as $key => $label)
                        @if(filled($registrarContact->get($key)))<div><strong>{{ $label }}</strong><span class="text-break">{{ $registrarContact->get($key) }}</span></div>@endif
                    @endforeach
                </div>
                @if(!$registrarEmail)<p>Visit the Registrar’s Office to arrange another pickup time.</p>@endif
                <p class="dr-edit-hint">Include your booking reference when you contact the office.</p>
            </div>
            <div class="modal-footer">
                <div>
                    <button type="button" class="btn btn-secondary" data-contact-copy data-copy-value="" data-copy-label="Booking reference">Copy reference</button>
                    @if($registrarEmail)<a class="btn btn-primary" data-contact-email>Email Registrar</a>@endif
                </div>
            </div>
        </div>
    </div>
</div>
