@extends('layouts.admin')
@section('title', __('delivery_slots.title'))
@section('css')
    <link href="{{ asset('assets/datatables/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" type="text/css">
@endsection
@section('content')
    <div class="card shadow no-border mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <h5 class="card-title mb-1">{{ __('delivery_slots.daily_slots') }}</h5>
                    <p class="text-muted mb-0">{{ __('delivery_slots.daily_slots_help') }}</p>
                </div>
                <a href="{{ route('admin.delivery-slots.create') }}" class="btn btn-primary">{{ __('delivery_slots.add_slot') }}</a>
            </div>
            <hr>
            <table id="slots-table" class="table table-bordered w-100">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('orders.option') }}</th>
                        <th>{{ __('delivery_slots.time') }}</th>
                        <th>{{ __('delivery_slots.max_orders') }}</th>
                        <th>{{ __('delivery_slots.total_orders') }}</th>
                        <th>{{ __('delivery_slots.status') }}</th>
                        <th>{{ __('delivery_slots.created') }}</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    <div class="card shadow no-border">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <h5 class="card-title mb-1">{{ __('delivery_slots.blackouts') }}</h5>
                    <p class="text-muted mb-0">{{ __('delivery_slots.blackouts_help') }}</p>
                </div>
                <a href="{{ route('admin.delivery-blackouts.create') }}" class="btn btn-outline-primary">{{ __('delivery_slots.add_blackout') }}</a>
            </div>
            <hr>
            <table id="blackouts-table" class="table table-bordered w-100">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('orders.option') }}</th>
                        <th>{{ __('delivery_slots.date_range') }}</th>
                        <th>{{ __('delivery_slots.label') }}</th>
                        <th>{{ __('delivery_slots.status') }}</th>
                        <th>{{ __('delivery_slots.created') }}</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    <div class="modal" id="delete" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body"><p>{{ __('delivery_slots.delete_slot_confirm') }}</p></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.close') }}</button>
                    <form action="" method="POST" id="delete-form">@csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger">{{ __('ui.delete') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="delete-blackout" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body"><p>{{ __('delivery_slots.delete_blackout_confirm') }}</p></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.close') }}</button>
                    <form action="" method="POST" id="delete-blackout-form">@csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger">{{ __('ui.delete') }}</button>
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
                { data: 'id' }, { data: 'options', orderable: false }, { data: 'time_label' },
                { data: 'max_orders' }, { data: 'orders_count' },
                { data: 'is_enabled' }, { data: 'created_at' },
            ]
        });

        $('#blackouts-table').DataTable({
            processing: true, serverSide: true,
            ajax: { url: appUrl + '/admin/fetch-delivery-blackouts', type: 'POST', data: { _token: csrfToken } },
            order: [[2, 'desc']],
            columnDefs: [{ visible: false, targets: [0] }],
            columns: [
                { data: 'id' }, { data: 'options', orderable: false }, { data: 'date_range' },
                { data: 'label' }, { data: 'is_enabled' }, { data: 'created_at' },
            ]
        });

        $(document).on('click', '.btn-delete', function () {
            $('#delete-form').attr('action', $(this).data('action'));
        });

        $(document).on('click', '.btn-delete-blackout', function () {
            $('#delete-blackout-form').attr('action', $(this).data('action'));
        });
    </script>
@endsection
