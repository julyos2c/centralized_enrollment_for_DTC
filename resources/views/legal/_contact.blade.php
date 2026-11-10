{{-- Privacy contact from Admin → Settings → General. Expects $legal. --}}
@php $dpo = $legal['dpo']; @endphp
@if($dpo['name'] || $dpo['email'] || $dpo['phone'] || $dpo['office'])
    <ul class="legal-contact">
        @if($dpo['name'])<li><strong>{{ $dpo['name'] }}</strong>, {{ $legal['institution'] }}</li>@endif
        @if($dpo['email'])<li>Email: <a href="mailto:{{ $dpo['email'] }}">{{ $dpo['email'] }}</a></li>@endif
        @if($dpo['phone'])<li>Telephone: {{ $dpo['phone'] }}</li>@endif
        @if($dpo['office'])<li>Office: {{ $dpo['office'] }}</li>@endif
    </ul>
@else
    <p class="legal-pending">
        The contact details of {{ $legal['institution'] }}'s Data Protection Officer will be published here.
        Until then, please bring privacy questions and requests to the Registrar's Office{{ $legal['address'] ? ' at ' . $legal['address'] : '' }},
        and mention your application reference number if you have one.
    </p>
@endif
