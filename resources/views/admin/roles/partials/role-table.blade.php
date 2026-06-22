<div class="table-responsive mb-4">
    <table class="table table-bordered align-middle">
        <thead>
            <tr>
                <th>{{ __('roles.role') }}</th>
                <th>{{ __('roles.description') }}</th>
                <th>{{ __('roles.type') }}</th>
                <th style="width: 140px;">{{ __('roles.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($roles as $role)
                <tr>
                    <td>
                        <strong>{{ $role->name }}</strong>
                        <div class="text-muted small">{{ $role->slug }}</div>
                    </td>
                    <td>{{ $role->description ?: '—' }}</td>
                    <td>
                        @if ($role->is_superadmin)
                            <span class="badge bg-dark">{{ __('roles.type_labels.superadmin') }}</span>
                        @elseif ($role->is_system)
                            <span class="badge bg-secondary">{{ __('roles.type_labels.system') }}</span>
                        @else
                            <span class="badge bg-primary">{{ __('roles.type_labels.custom') }}</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('admin.roles.edit', $role->slug) }}" class="btn btn-sm btn-primary" title="{{ __('ui.edit') }}">
                            <i class="fa fa-edit"></i>
                        </a>
                        @if (!$role->is_system)
                            <button type="button" class="btn btn-sm btn-danger btn-delete-role"
                                data-action="{{ route('admin.roles.destroy', $role->slug) }}" title="{{ __('ui.delete') }}">
                                <i class="fa fa-trash"></i>
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-muted">{{ __('roles.no_roles') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
