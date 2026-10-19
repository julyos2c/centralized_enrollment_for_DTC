@props(['status', 'variant' => null])
@php $tone = $variant ?: match(strtolower((string)$status)) {'approved','verified','completed','paid','released' => 'success', 'pending','under review','submitted','processing' => 'warning', 'rejected','failed','cancelled' => 'danger', default => 'neutral' }; @endphp
<span {{ $attributes->merge(['class' => "dtc-status-badge is-$tone"]) }}><span class="dtc-status-dot"></span>{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
