@php
    $portalKey = $portalKey ?? ($role->portal ?? 'admin');
@endphp

<div class="table-responsive">
    <table class="table table-bordered align-middle">
        <thead>
            <tr>
                <th style="width: 70px;">{{ __('roles.allow') }}</th>
                <th>{{ __('roles.permission') }}</th>
                <th>{{ __('roles.description') }}</th>
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
                @endphp
                <tr>
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input" name="permissions[]"
                            value="{{ $key }}" id="perm_{{ $key }}"
                            {{ ($allowed[$key] ?? false) ? 'checked' : '' }}>
                    </td>
                    <td>
                        <label for="perm_{{ $key }}" class="mb-0 fw-semibold">{{ $label }}</label>
                    </td>
                    <td class="text-muted">{{ $description }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-muted">{{ __('roles.no_permissions') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="d-flex justify-content-end gap-2 mb-3">
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelectorAll('input[name=\'permissions[]\']:not([disabled])').forEach(el => el.checked = true)">{{ __('roles.select_all') }}</button>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelectorAll('input[name=\'permissions[]\']:not([disabled])').forEach(el => el.checked = false)">{{ __('roles.clear_all') }}</button>
</div>
