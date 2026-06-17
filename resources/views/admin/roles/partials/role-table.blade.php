<div class="table-responsive mb-4">
    <table class="table table-bordered align-middle">
        <thead>
            <tr>
                <th>Role</th>
                <th>Description</th>
                <th>Type</th>
                <th style="width: 140px;">Actions</th>
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
                            <span class="badge bg-dark">Superadmin</span>
                        @elseif ($role->is_system)
                            <span class="badge bg-secondary">System</span>
                        @else
                            <span class="badge bg-primary">Custom</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('admin.roles.edit', $role->slug) }}" class="btn btn-sm btn-primary">
                            <i class="fa fa-edit"></i>
                        </a>
                        @if (!$role->is_system)
                            <button type="button" class="btn btn-sm btn-danger btn-delete-role"
                                data-action="{{ route('admin.roles.destroy', $role->slug) }}">
                                <i class="fa fa-trash"></i>
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-muted">No roles yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
