@extends('layouts.admin')
@section('title', __('drivers.vehicles_manage'))
@section('css')
    <link href="{{ asset('assets/datatables/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" type="text/css">
@endsection
@section('content')

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                        <h5>{{ __('drivers.vehicles_list') }}</h5>
                        <div class="d-flex gap-2">
                            <a href="{{ route('admin.drivers.index') }}" class="btn btn-secondary">{{ __('drivers.list') }}</a>
                            <a href="{{ route('admin.vehicles.create') }}" class="btn btn-primary">{{ __('drivers.vehicle_add') }}</a>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="datatable-data" class="table table-bordered w-100">
                            <thead>
                                <tr>
                                    <th>{{ __('drivers.id') }}</th>
                                    <th>{{ __('drivers.options') }}</th>
                                    <th>{{ __('drivers.vehicle_number') }}</th>
                                    <th>{{ __('drivers.description') }}</th>
                                    <th>{{ __('drivers.status') }}</th>
                                    <th>{{ __('drivers.created_at') }}</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="delete" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('drivers.delete_vehicle') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body"><p>{{ __('drivers.delete_vehicle_confirm') }}</p></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.close') }}</button>
                    <form action="" method="POST" id="delete-form">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-primary">{{ __('ui.delete') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('script')
    <script src="{{ asset('assets/datatables/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/datatables/js/dataTables.bootstrap4.min.js') }}"></script>
    <script>
        $('#datatable-data').DataTable({
            processing: true,
            serverSide: true,
            order: [[2, 'asc']],
            columnDefs: [{ visible: false, targets: [0] }],
            language: @json(__('drivers.datatable')),
            ajax: {
                url: appUrl + '/admin/fetch-vehicles',
                type: 'POST',
                data: { _token: csrfToken },
            },
            columns: [
                { data: 'id', orderable: false },
                { data: 'options', orderable: false },
                { data: 'vehicle_number', orderable: true },
                { data: 'description', orderable: true },
                { data: 'is_active', orderable: true },
                { data: 'created_at', orderable: true },
            ]
        });

        document.addEventListener('click', function (event) {
            const btn = event.target.closest('.btn-delete');
            if (btn) {
                document.getElementById('delete-form').setAttribute('action', btn.getAttribute('data-action'));
            }
        });
    </script>
@endsection
