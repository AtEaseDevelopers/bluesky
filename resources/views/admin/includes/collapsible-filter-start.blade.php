@php
    $panelId = $panelId ?? 'adminFilterPanel';
    $title = $title ?? __('ui.filter');
    $expanded = $expanded ?? false;

    if (!$expanded && !empty($expandWhenFilled)) {
        foreach ((array) $expandWhenFilled as $field) {
            if (request()->filled($field)) {
                $expanded = true;
                break;
            }
        }
    }
@endphp
<div class="card shadow no-border mb-0 admin-filter-panel">
    <div class="card-body">
        <button type="button"
            class="btn btn-link admin-filter-toggle w-100 text-start text-decoration-none text-dark d-flex align-items-center justify-content-between gap-3 p-0"
            data-bs-toggle="collapse"
            data-bs-target="#{{ $panelId }}"
            aria-expanded="{{ $expanded ? 'true' : 'false' }}"
            aria-controls="{{ $panelId }}">
            <h5 class="mb-0">{{ $title }}</h5>
            <i class="fa fa-chevron-down admin-filter-chevron flex-shrink-0" aria-hidden="true"></i>
        </button>
        <div class="collapse {{ $expanded ? 'show' : '' }}" id="{{ $panelId }}">
            <div class="admin-filter-body pt-4">
