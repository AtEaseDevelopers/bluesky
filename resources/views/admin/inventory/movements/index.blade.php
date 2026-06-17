@extends('layouts.admin')
@section('title', 'Stock Movement Log')
@section('css')
    <link href="{{ asset('assets/datatables/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" type="text/css">
@endsection
@section('content')

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                        <h5 class="card-title">Stock Movement Log</h5>
                        <div class="d-flex gap-2">
                            <a href="{{ route('admin.inventory.index') }}" class="btn btn-secondary">Stock Balance</a>
                            <a href="{{ route('admin.inventory.stock-in.create') }}" class="btn btn-success">Stock In</a>
                            <a href="{{ route('admin.inventory.stock-out.create') }}" class="btn btn-warning">Stock Out</a>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="mb-2" for="filter_type">Movement Type</label>
                            <select id="filter_type" class="form-control">
                                <option value="">All Types</option>
                                @foreach ($movement_types as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <hr>
                    <div class="table-responsive">
                        <table id="stock-movements-table" class="table table-bordered w-100">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Product</th>
                                    <th>Type</th>
                                    <th>Qty Before</th>
                                    <th>Change</th>
                                    <th>Qty After</th>
                                    <th>Weight</th>
                                    <th>Reason</th>
                                    <th>User</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('script')
    <script src="{{ asset('assets/datatables/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/datatables/js/dataTables.bootstrap4.min.js') }}"></script>
    <script>
        var movementsTable = $('#stock-movements-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            order: [[0, 'desc']],
            columnDefs: [{ visible: false, targets: [0] }],
            ajax: {
                url: appUrl + '/admin/fetch-stock-movements',
                dataType: 'json',
                type: 'POST',
                data: function (d) {
                    d._token = csrfToken;
                    d.filter_type = $('#filter_type').val();
                },
            },
            columns: [
                { data: 'id', orderable: false },
                { data: 'movement_date', orderable: true },
                { data: 'product_name', orderable: true },
                { data: 'movement_type', orderable: true },
                { data: 'quantity_before', orderable: false },
                { data: 'quantity_change', orderable: false },
                { data: 'quantity_after', orderable: false },
                { data: 'weight', orderable: false },
                { data: 'reason', orderable: false },
                { data: 'admin_name', orderable: true },
                { data: 'remarks', orderable: false },
            ]
        });

        $('#filter_type').on('change', function () {
            movementsTable.ajax.reload();
        });
    </script>
@endsection
