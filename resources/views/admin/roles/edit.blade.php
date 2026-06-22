@extends('layouts.admin')
@section('title', __('roles.edit'))
@section('content')

    @include('admin.roles.partials.form', [
        'action' => route('admin.roles.update', $role->slug),
        'method' => 'PUT',
        'role' => $role,
        'permissions' => $permissions,
        'allowed' => $allowed,
        'portals' => config('permissions.portals', []),
        'portalPermissions' => [],
    ])

@endsection
