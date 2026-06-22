@extends('layouts.admin')
@section('title', __('drivers.manage'))
@section('css')

    <link href="{{ asset('assets/datatables/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" type="text/css">

@endsection
@section('content')

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                        <h5>{{ __('drivers.list') }}</h5>
                        <a href="{{ route('admin.lorry.create') }}" class="btn btn-primary">
                            {{ __('drivers.add') }}
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table id="datatable-data" class="table table-bordered w-100">
                            <thead>
                                <tr>
                                    <th>{{ __('drivers.id') }}</th>
                                    <th>{{ __('drivers.options') }}</th>
                                    <th>{{ __('drivers.name') }}</th>
                                    <th>{{ __('drivers.username') }}</th>
                                    <th>{{ __('drivers.lorry_number') }}</th>
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
                    <h5 class="modal-title">{{ __('drivers.delete_driver') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
                </div>
                <div class="modal-body">
                    <p>{{ __('drivers.delete_confirm') }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}">{{ __('ui.close') }}</button>
                    <form action="" method="POST" id="delete-form" class="form-wrapper">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-primary">
                            {{ __('ui.delete') }}
                            <div class="spinner-border spinner-border-sm d-none" role="status">
                                <span class="visually-hidden">{{ __('inventory.loading') }}</span>
                            </div>
                        </button>
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
        $(document).ready(function() {
            $('#datatable-data').DataTable({
                dom: '<"row d-flex justify-content-between"<"col ps-0"l><"col-md-6 text-center pro-tips py-2"><"col pe-0"f>>t<"row mt-3"<"col-md-6"i><"col-md-6"p>r>',
                processing: true,
                scrollX: true,
                serverSide: true,
                responsive: false,
                order: [
                    [0, "desc"]
                ],
                columnDefs: [{
                    visible: false,
                    targets: [0]
                }],
                language: @json(__('drivers.datatable')),
                ajax: {
                    url: appUrl + '/admin/get-lorry',
                    dataType: "json",
                    type: "POST",
                    data: {
                        _token: csrfToken
                    }
                },
                columns: [{
                        data: "id",
                        orderable: false
                    },
                    {
                        data: "options",
                        orderable: false
                    },
                    {
                        data: "name",
                        orderable: true
                    },
                    {
                        data: "username",
                        orderable: true
                    },
                    {
                        data: "lorry_number",
                        orderable: true
                    },
                    {
                        data: "is_active",
                        orderable: true
                    },
                    {
                        data: "created_at",
                        orderable: true
                    },
                ]
            });

            document.addEventListener('click', function(event) {
                if (event.target.closest('.btn-delete')) {
                    const el = event.target.closest('.btn-delete');
                    document.getElementById('delete-form').setAttribute('action', el.getAttribute('data-action'));
                }
            });
        });
    </script>

@endsection
