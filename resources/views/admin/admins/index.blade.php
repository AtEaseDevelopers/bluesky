@extends('layouts.admin')
@section('title', 'All Admin')
@section('css')

    <link href="{{ asset(path: 'assets/datatables/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" type="text/css">

@endsection
@section('content')
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                        <h5 class="card-title">All Admin</h5>
                        <a href="{{ route('admin.admins.create') }}" class="btn btn-primary">
                            Add New Admin
                        </a>
                    </div>
                    <hr>
                    <div class="table-responsive">
                        <table id="admin-table" class="table table-bordered w-100">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Options</th>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
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
                    <h5 class="modal-title">Delete Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure to delete this admin?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                        aria-label="Close">Close</button>
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
        $(document).on('change', '.toggle-status', function() {
            const isChecked = $(this).is(':checked');
            const adminId = $(this).data('id');
            const newStatus = isChecked ? 'active' : 'inactive';

            $.ajax({
                "url": appUrl + '/admin/update-admin-status',
                method: 'POST',
                data: {
                    _token: csrfToken,
                    admin_id: adminId,
                    status: newStatus
                },
                success: function(res) {
                    if (res.success) {
                        showToast(`Status changed to ${newStatus}`, 'success');
                    }
                },
                error: function() {
                    showToast('Error updating status', 'danger');
                }
            });
        });

        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0 show`;
            toast.role = 'alert';
            toast.style.position = 'fixed';
            toast.style.top = '20px'; // Top instead of bottom
            toast.style.right = '20px'; // Still on the right
            toast.style.bottom = ''; // Clear bottom setting
            toast.style.zIndex = 9999;

            toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
            document.body.appendChild(toast);

            setTimeout(() => toast.remove(), 2500);
        }

        $('#admin-table').DataTable({
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
                "url": appUrl + '/admin/fetch-admins',
                "dataType": "json",
                "type": "POST",
                "data": {
                    _token: csrfToken,
                },
                "error": function(xhr, error, thrown) {
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
                    "data": "admin_name",
                    orderable: true
                },
                {
                    "data": "admin_username",
                    orderable: true
                },
                {
                    "data": "admin_email",
                    orderable: true
                },
                {
                    "data": "created_at",
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
    </script>

@endsection
