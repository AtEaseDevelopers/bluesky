@php
    $isEdit = !empty($role);
    $selectedPortal = old('portal', $role->portal ?? 'admin');
@endphp

<div class="row mb-4">
    <div class="col-lg-10">
        <div class="card shadow no-border">
            <div class="card-body">
                <h5 class="card-title">{{ $isEdit ? __('roles.edit') : __('roles.add') }}</h5>
                <hr>

                <form action="{{ $action }}" method="POST">
                    @csrf
                    @if ($method !== 'POST')
                        @method($method)
                    @endif

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-4">
                                <label class="mb-2" for="name">{{ __('roles.role_name') }}</label>
                                <span class="text-danger">*</span>
                                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
                                    value="{{ old('name', $role->name ?? '') }}" {{ ($role && $role->is_system && $role->is_superadmin) ? 'readonly' : '' }} required>
                                @error('name')<span class="text-danger"><strong>{{ $message }}</strong></span>@enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-4">
                                <label class="mb-2" for="portal">{{ __('roles.portal') }}</label>
                                <span class="text-danger">*</span>
                                @if ($isEdit)
                                    <input type="text" class="form-control" value="{{ $role->portalLabel() }}" readonly>
                                @else
                                    <select name="portal" id="portal" class="form-select @error('portal') is-invalid @enderror" required>
                                        @foreach ($portals as $portalKey => $portalMeta)
                                            <option value="{{ $portalKey }}" {{ $selectedPortal === $portalKey ? 'selected' : '' }}>
                                                {{ __('permissions.portals.' . $portalKey . '.label') }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('portal')<span class="text-danger"><strong>{{ $message }}</strong></span>@enderror
                                @endif
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-4">
                                <label class="mb-2" for="description">{{ __('roles.description') }}</label>
                                <textarea name="description" id="description" rows="2" class="form-control">{{ old('description', $role->description ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>

                    @if ($role && $role->is_superadmin)
                        <div class="alert alert-info">{{ __('roles.superadmin_notice') }}</div>
                    @else
                        <h6 class="mb-3">{{ __('roles.permissions') }}</h6>
                        @if ($isEdit)
                            @include('admin.roles.partials.permission-table', [
                                'permissions' => $permissions,
                                'allowed' => $allowed,
                                'portalKey' => $role->portal,
                            ])
                        @else
                            @foreach ($portals as $portalKey => $portalMeta)
                                <div class="portal-permissions {{ $selectedPortal === $portalKey ? '' : 'd-none' }}" data-portal="{{ $portalKey }}">
                                    @include('admin.roles.partials.permission-table', [
                                        'permissions' => $portalPermissions[$portalKey] ?? [],
                                        'allowed' => [],
                                        'portalKey' => $portalKey,
                                    ])
                                </div>
                            @endforeach
                        @endif
                    @endif

                    <div class="d-flex justify-content-between mt-4">
                        <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">{{ __('ui.back') }}</a>
                        @if (!$role || !$role->is_superadmin)
                            <button type="submit" class="btn btn-primary">{{ $isEdit ? __('roles.save') : __('roles.create') }}</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
