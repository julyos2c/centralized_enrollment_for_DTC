{{--
    Unified KPI / stat card used across every dashboard (Admin, Registrar,
    Cashier, Student, New Applicant, Alumni). Keeps icon color, radius,
    shadow, and spacing consistent in one place.

    Props:
      icon   - Lucide icon name, e.g. 'graduation-cap'
      color  - primary | warning | success | danger | info | neutral
      label  - small uppercase label
      value  - main display value
      note   - optional caption under the value (plain text, no link)
      href   - optional link target; renders label + arrow under the value
      linkText - optional override for the link text (defaults to "View All")
      badge  - optional number shown as a red dot on the icon (e.g. unread count)
--}}
@props([
    'icon' => 'circle',
    'color' => 'primary',
    'label' => '',
    'value' => '',
    'note' => null,
    'href' => null,
    'linkText' => 'View All',
    'badge' => null,
])

<div class="dtc-stat-card dtc-stat-{{ $color }}">
    <div class="dtc-stat-card-top">
        <div class="dtc-stat-icon">
            <i data-lucide="{{ $icon }}"></i>
            @if(!is_null($badge) && $badge > 0)
                <span class="dtc-stat-icon-badge">{{ $badge }}</span>
            @endif
        </div>
        <div class="dtc-stat-copy">
            <div class="dtc-stat-label">{{ $label }}</div>
            <div class="dtc-stat-value">{{ $value }}</div>
        </div>
    </div>

    @if($note)
        <div class="dtc-stat-note">{{ $note }}</div>
    @endif

    @if($href)
        <a href="{{ $href }}" class="dtc-stat-link">{{ $linkText }} →</a>
    @endif
</div>
