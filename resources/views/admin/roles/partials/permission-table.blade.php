@php
    $portalKey = $portalKey ?? ($role->portal ?? 'admin');
    $capabilityColumns = ['view', 'create', 'edit'];
    $hasGranular = collect($permissions)->contains(fn ($permission) => isset($permission['capabilities']));
@endphp

<div class="table-responsive">
    <table class="table table-bordered align-middle">
        <thead>
            <tr>
                <th>{{ __('roles.permission') }}</th>
                <th>{{ __('roles.description') }}</th>
                @if ($hasGranular)
                    @foreach ($capabilityColumns as $column)
                        <th class="text-center" style="width: 80px;">{{ __('roles.capabilities.' . $column) }}</th>
                    @endforeach
                @else
                    <th class="text-center" style="width: 70px;">{{ __('roles.allow') }}</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($permissions as $key => $permission)
                @php
                    $labelKey = "permissions.portals.{$portalKey}.permissions.{$key}.label";
                    $descKey = "permissions.portals.{$portalKey}.permissions.{$key}.description";
                    $label = __($labelKey);
                    $description = __($descKey);
                    if ($label === $labelKey) {
                        $label = $permission['label'];
                    }
                    if ($description === $descKey) {
                        $description = $permission['description'] ?? '';
                    }
                    $capabilities = $permission['capabilities'] ?? null;
                @endphp
                <tr>
                    <td class="fw-semibold">{{ $label }}</td>
                    <td class="text-muted">{{ $description }}</td>
                    @if ($capabilities)
                        @foreach ($capabilityColumns as $column)
                            <td class="text-center">
                                @if (isset($capabilities[$column]))
                                    @php
                                        $permKey = $key . '.' . $column;
                                        $capLabelKey = "permissions.portals.{$portalKey}.permissions.{$key}.capabilities.{$column}.label";
                                        $capLabel = __($capLabelKey);
                                        if ($capLabel === $capLabelKey) {
                                            $capLabel = $capabilities[$column]['label'] ?? ucfirst($column);
                                        }
                                    @endphp
                                    <input type="checkbox" class="form-check-input" name="permissions[]"
                                        value="{{ $permKey }}" id="perm_{{ $permKey }}"
                                        title="{{ $capLabel }}"
                                        {{ ($allowed[$permKey] ?? false) ? 'checked' : '' }}>
                                @endif
                            </td>
                        @endforeach
                    @else
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input" name="permissions[]"
                                value="{{ $key }}" id="perm_{{ $key }}"
                                {{ ($allowed[$key] ?? false) ? 'checked' : '' }}>
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $hasGranular ? 5 : 3 }}" class="text-muted">{{ __('roles.no_permissions') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="d-flex justify-content-end gap-2 mb-3">
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelectorAll('input[name=\'permissions[]\']:not([disabled])').forEach(el => el.checked = true)">{{ __('roles.select_all') }}</button>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelectorAll('input[name=\'permissions[]\']:not([disabled])').forEach(el => el.checked = false)">{{ __('roles.clear_all') }}</button>
</div>
