<div class="table-responsive">
    <table class="table table-bordered align-middle">
        <thead>
            <tr>
                <th style="width: 70px;">Allow</th>
                <th>Permission</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($permissions as $key => $permission)
                <tr>
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input" name="permissions[]"
                            value="{{ $key }}" id="perm_{{ $key }}"
                            {{ ($allowed[$key] ?? false) ? 'checked' : '' }}>
                    </td>
                    <td>
                        <label for="perm_{{ $key }}" class="mb-0 fw-semibold">{{ $permission['label'] }}</label>
                    </td>
                    <td class="text-muted">{{ $permission['description'] ?? '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-muted">No permissions defined for this portal.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="d-flex justify-content-end gap-2 mb-3">
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelectorAll('input[name=\'permissions[]\']:not([disabled])').forEach(el => el.checked = true)">Select All</button>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelectorAll('input[name=\'permissions[]\']:not([disabled])').forEach(el => el.checked = false)">Clear All</button>
</div>
