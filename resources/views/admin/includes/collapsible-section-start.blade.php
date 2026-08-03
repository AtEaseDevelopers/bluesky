@php
    $panelId = $panelId ?? 'adminSectionPanel';
    $title = $title ?? '';
    $expanded = $expanded ?? false;
    $subtitle = $subtitle ?? null;
@endphp
<div class="admin-collapsible-section mb-3">
    <button type="button"
        class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-between gap-2 admin-filter-toggle"
        data-bs-toggle="collapse"
        data-bs-target="#{{ $panelId }}"
        aria-expanded="{{ $expanded ? 'true' : 'false' }}"
        aria-controls="{{ $panelId }}">
        <span class="text-start">
            <span class="fw-semibold d-block">{{ $title }}</span>
            @if ($subtitle)
                <small class="text-muted fw-normal">{{ $subtitle }}</small>
            @endif
        </span>
        <i class="fa fa-chevron-down admin-filter-chevron flex-shrink-0" aria-hidden="true"></i>
    </button>
    <div class="collapse {{ $expanded ? 'show' : '' }}" id="{{ $panelId }}">
        <div class="pt-3">
