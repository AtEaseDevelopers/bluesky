@extends('layouts.admin')
@section('title', 'Stock Balance')
@section('css')
    <link href="{{ asset('assets/datatables/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" type="text/css">
@endsection
@section('content')

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                        <h5 class="card-title">Stock Balance</h5>
                        <div class="d-flex gap-2">
                            <a href="{{ route('admin.inventory.stock-in.create') }}" class="btn btn-success">
                                <i class="fa fa-plus me-1"></i> Stock In
                            </a>
                            <a href="{{ route('admin.inventory.stock-out.create') }}" class="btn btn-warning">
                                <i class="fa fa-minus me-1"></i> Stock Out
                            </a>
                            <a href="{{ route('admin.inventory.movements') }}" class="btn btn-secondary">
                                Movement Log
                            </a>
                        </div>
                    </div>
                    <hr>
                    <div class="table-responsive">
                        <table id="stock-balance-table" class="table table-bordered w-100">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Actions</th>
                                    <th>Product</th>
                                    <th>SKU</th>
                                    <th>UOM</th>
                                    <th>Quantity</th>
                                    <th>Weight</th>
                                    <th>Last Updated</th>
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
        $('#stock-balance-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            order: [[2, 'asc']],
            columnDefs: [{ visible: false, targets: [0] }],
            ajax: {
                url: appUrl + '/admin/fetch-stock-balances',
                dataType: 'json',
                type: 'POST',
                data: { _token: csrfToken },
            },
            columns: [
                { data: 'id', orderable: false },
                { data: 'options', orderable: false },
                { data: 'name', orderable: true },
                { data: 'sku', orderable: true },
                { data: 'uom_name', orderable: true },
                { data: 'quantity', orderable: true },
                { data: 'weight', orderable: true },
                { data: 'updated_at', orderable: true },
            ]
        });
    </script>
@endsection
