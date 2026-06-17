@extends('layouts.admin')
@section('title', 'Manage Roles')
@section('content')

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                        <div>
                            <h5 class="card-title mb-1">Manage Roles</h5>
                            <p class="text-muted mb-0">Create roles and choose what each role can access. Superadmin always has full access.</p>
                        </div>
                        <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">Add New Role</a>
                    </div>

                    @if ($superadminRole)
                        <h6 class="mb-3">Superadmin</h6>
                        @include('admin.roles.partials.role-table', ['roles' => collect([$superadminRole])])
                    @endif

                    @foreach ($portals as $portalKey => $portalMeta)
                        @php
                            $roles = $rolesByPortal->get($portalKey, collect());
                            if ($portalKey === 'admin') {
                                $roles = $roles->where('is_superadmin', false)->values();
                            }
                        @endphp
                        <h6 class="mt-4 mb-3">{{ $portalMeta['label'] ?? ucfirst($portalKey) }}</h6>
                        @include('admin.roles.partials.role-table', ['roles' => $roles])
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="deleteRoleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this role?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="" method="POST" id="delete-role-form">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('script')
    <script>
        document.addEventListener('click', function(event) {
            const button = event.target.closest('.btn-delete-role');
            if (!button) {
                return;
            }
            document.getElementById('delete-role-form').action = button.dataset.action;
            new bootstrap.Modal(document.getElementById('deleteRoleModal')).show();
        });
    </script>
@endsection
