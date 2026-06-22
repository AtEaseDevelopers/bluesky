@extends('layouts.admin')
@section('title', __('roles.add'))
@section('content')

    @include('admin.roles.partials.form', [
        'action' => route('admin.roles.store'),
        'method' => 'POST',
        'role' => null,
        'permissions' => [],
        'allowed' => [],
        'portals' => $portals,
        'portalPermissions' => $portalPermissions,
    ])

@endsection
@section('script')
    <script>
        const portalSelect = document.getElementById('portal');
        const sections = document.querySelectorAll('.portal-permissions');

        function syncPortalSections() {
            const portal = portalSelect.value;
            sections.forEach(function(section) {
                const active = section.dataset.portal === portal;
                section.classList.toggle('d-none', !active);
                section.querySelectorAll('input[type=checkbox]').forEach(function(input) {
                    input.disabled = !active;
                    if (!active) {
                        input.checked = false;
                    }
                });
            });
        }

        if (portalSelect) {
            portalSelect.addEventListener('change', syncPortalSections);
            syncPortalSections();
        }
    </script>
@endsection
