@extends('layouts.admin')
@section('title', 'Delivery Slots')
@section('css')
    <link href="{{ asset('assets/datatables/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" type="text/css">
@endsection
@section('content')
    <div class="card shadow no-border">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="card-title mb-0">Delivery Slots</h5>
                <a href="{{ route('admin.delivery-slots.create') }}" class="btn btn-primary">Add Slot</a>
            </div>
            <hr>
            <table id="slots-table" class="table table-bordered w-100">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Options</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Max Orders</th>
                        <th>Booked</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
    <div class="modal" id="delete" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body"><p>Delete this delivery slot?</p></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <form action="" method="POST" id="delete-form">@csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger">Delete</button>
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
        $('#slots-table').DataTable({
            processing: true, serverSide: true,
            ajax: { url: appUrl + '/admin/fetch-delivery-slots', type: 'POST', data: { _token: csrfToken } },
            order: [[2, 'asc']],
            columnDefs: [{ visible: false, targets: [0] }],
            columns: [
                { data: 'id' }, { data: 'options', orderable: false }, { data: 'slot_date' },
                { data: 'time_label' }, { data: 'max_orders' }, { data: 'orders_count' },
                { data: 'is_enabled' }, { data: 'created_at' },
            ]
        });
        $(document).on('click', '.btn-delete', function () {
            $('#delete-form').attr('action', $(this).data('action'));
        });
    </script>
@endsection
