@extends('layouts.admin')
@section('title', 'All Areas')
@section('css')

    <link href="{{ asset('assets/datatables/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" type="text/css">

@endsection
@section('content')

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                        <h5 class="card-title">Areas</h5>
                        <a href="{{ route('admin.areas.create') }}" class="btn btn-primary">
                            Add New Area
                        </a>
                    </div>
                    <hr>
                    <div class="table-responsive">
                        <table id="areas-table" class="table table-bordered w-100">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Options</th>
                                    <th>Area</th>
                                    <th>Total Customers</th>
                                    <th>Created At</th>
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
                    <h5 class="modal-title">Delete Area</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure to delete this area?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <form action="" method="POST" id="delete-form" class="form-wrapper">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-primary">
                            Delete
                            <div class="spinner-border spinner-border-sm d-none" role="status">
                                <span class="visually-hidden">Loading...</span>
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
        $('#areas-table').DataTable({
            "processing": true,
            "serverSide": true,
            "responsive": true,
            order: [
                [0, "asc"]
            ],
            columnDefs: [{
                'visible': false,
                'targets': [0]
            }],
            "ajax": {
                "url": appUrl + '/admin/fetch-areas',
                "dataType": "json",
                "type": "POST",
                "data": {
                    _token: csrfToken,
                },
                "error": function (xhr, error, thrown) {
                    try {
                        var errorMessage = JSON.parse(xhr.responseText).message;
                        console.log("Error:", errorMessage);
                    } catch (e) {
                        console.error("Failed to parse error response:", xhr.responseText);
                    }
                }
            },
            "columns": [{
                    "data": "id",
                    orderable: false
                },
                {
                    "data": "options",
                    orderable: false
                },
                {
                    "data": "area_name",
                    orderable: true
                },
                {
                    "data": "total_customers",
                    orderable: false
                },
                {
                    "data": "created_at",
                    orderable: true
                },
            ]
        });
    </script>

@endsection
