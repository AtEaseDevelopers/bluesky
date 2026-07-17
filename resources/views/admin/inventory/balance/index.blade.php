@extends('layouts.admin')
@section('title', __('inventory.stock_balance'))
@section('css')
    <link href="{{ asset('assets/datatables/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" type="text/css">
@endsection
@section('content')
@php
    $admin = Auth::guard('web_admin')->user();
    $canEditInventory = $admin->canModule('products', 'edit');
@endphp

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                        <h5 class="card-title">{{ __('inventory.stock_balance') }}</h5>
                        <div class="d-flex gap-2">
                            @if ($canEditInventory)
                                <a href="{{ route('admin.inventory.stock-in.create') }}" class="btn btn-success">
                                    <i class="fa fa-plus me-1"></i> {{ __('inventory.stock_in') }}
                                </a>
                                <a href="{{ route('admin.inventory.stock-out.create') }}" class="btn btn-warning">
                                    <i class="fa fa-minus me-1"></i> {{ __('inventory.stock_out') }}
                                </a>
                            @endif
                            <a href="{{ route('admin.inventory.movements') }}" class="btn btn-secondary">
                                {{ __('inventory.movement_log') }}
                            </a>
                        </div>
                    </div>
                    <hr>
                    <div class="table-responsive">
                        <table id="stock-balance-table" class="table table-bordered w-100">
                            <thead>
                                <tr>
                                    <th>{{ __('inventory.id') }}</th>
                                    @if ($canEditInventory)
                                        <th>{{ __('inventory.actions') }}</th>
                                    @endif
                                    <th>{{ __('inventory.product') }}</th>
                                    <th>{{ __('inventory.sku') }}</th>
                                    <th>{{ __('inventory.uom') }}</th>
                                    <th>{{ __('inventory.price') }}</th>
                                    <th>{{ __('inventory.quantity') }}</th>
                                    <th>{{ __('inventory.weight_kg') }}</th>
                                    <th>{{ __('inventory.last_updated') }}</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($canEditInventory)
    <div class="modal fade" id="editStockModal" tabindex="-1" aria-labelledby="editStockModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editStockModalLabel">{{ __('inventory.edit_stock') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
                </div>
                <form id="edit-stock-form" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <input type="hidden" name="product_id" id="edit-stock-product-id">
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <img src="" alt="" id="edit-stock-image-preview" class="rounded border" style="width:80px;height:80px;object-fit:cover">
                            <div>
                                <strong id="edit-stock-product-name"></strong><br>
                                <span class="text-muted" id="edit-stock-product-sku"></span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="edit-stock-image">{{ __('inventory.product_image') }}</label>
                            <input type="file" class="form-control" name="images" id="edit-stock-image" accept="image/jpeg,image/png,image/jpg">
                            <small class="text-muted">{{ __('inventory.image_help') }}</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="edit-stock-price">{{ __('inventory.default_price') }}</label>
                            <div class="input-group">
                                <span class="input-group-text">RM</span>
                                <input type="number" step="0.01" min="0" class="form-control" name="price" id="edit-stock-price" required>
                                <span class="input-group-text" id="edit-stock-price-uom">/ KG</span>
                            </div>
                        </div>
                        <div class="mb-3" id="edit-stock-quantity-wrap">
                            <label class="form-label" for="edit-stock-quantity">{{ __('inventory.quantity') }}</label>
                            <div class="input-group">
                                <input type="number" step="0.001" min="0" class="form-control" name="quantity" id="edit-stock-quantity" required>
                                <span class="input-group-text" id="edit-stock-quantity-uom">KG</span>
                            </div>
                        </div>
                        <div class="mb-3" id="edit-stock-weight-wrap">
                            <label class="form-label" for="edit-stock-weight">{{ __('inventory.weight_kg') }}</label>
                            <input type="number" step="0.001" min="0" class="form-control" name="weight" id="edit-stock-weight">
                            <small class="text-muted" id="edit-stock-weight-help">{{ __('inventory.weight_reference') }}</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.cancel') }}</button>
                        <button type="submit" class="btn btn-primary" id="edit-stock-save-btn">
                            {{ __('inventory.save_changes') }}
                            <span class="spinner-border spinner-border-sm d-none" id="edit-stock-spinner" role="status"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

@endsection
@section('script')
    <script src="{{ asset('assets/datatables/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/datatables/js/dataTables.bootstrap4.min.js') }}"></script>
    <script>
        const canEditInventory = @json($canEditInventory);
        const stockBalanceColumns = [
            { data: 'id', orderable: false },
        ];

        if (canEditInventory) {
            stockBalanceColumns.push({ data: 'options', orderable: false });
        }

        stockBalanceColumns.push(
            { data: 'name', orderable: true },
            { data: 'sku', orderable: true },
            { data: 'uom_name', orderable: true },
            { data: 'price', orderable: true, searchable: false },
            { data: 'quantity', orderable: true },
            { data: 'weight', orderable: true },
            { data: 'updated_at', orderable: true },
        );

        const stockBalanceTable = $('#stock-balance-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            order: [[canEditInventory ? 2 : 1, 'asc']],
            columnDefs: [{ visible: false, targets: [0] }],
            ajax: {
                url: appUrl + '/admin/fetch-stock-balances',
                dataType: 'json',
                type: 'POST',
                data: { _token: csrfToken },
            },
            columns: stockBalanceColumns,
        });

        if (canEditInventory) {
        const editStockModal = new bootstrap.Modal(document.getElementById('editStockModal'));
        const skuLabel = @json(__('inventory.sku'));

        $(document).on('click', '.btn-edit-stock', function () {
            const btn = $(this);
            const uom = btn.data('uom') || 'KG';
            const sellIn = btn.data('sellIn') || 'qty';
            const isWeightProduct = sellIn === 'weight';
            const defaultImage = @json(asset('assets/images/product-default.jpg'));

            $('#edit-stock-product-id').val(btn.data('product-id'));
            $('#edit-stock-product-name').text(btn.data('name'));
            $('#edit-stock-product-sku').text(skuLabel + ': ' + btn.data('sku'));
            $('#edit-stock-price').val(btn.data('price'));
            $('#edit-stock-quantity').val(btn.data('quantity'));
            $('#edit-stock-weight').val(btn.data('weight'));
            $('#edit-stock-price-uom').text('/ ' + uom);
            $('#edit-stock-quantity-uom').text(uom);
            $('#edit-stock-image').val('');
            $('#edit-stock-image-preview').attr('src', btn.data('image-url') || defaultImage);

            $('#edit-stock-quantity-wrap').toggle(!isWeightProduct);
            $('#edit-stock-weight-wrap').toggle(isWeightProduct);
            $('#edit-stock-quantity').prop('required', !isWeightProduct);
            $('#edit-stock-weight').prop('required', isWeightProduct);
            $('#edit-stock-weight-help').text(isWeightProduct
                ? @json(__('inventory.weight_required'))
                : @json(__('inventory.weight_reference')));

            editStockModal.show();
        });

        $('#edit-stock-image').on('change', function () {
            const file = this.files[0];
            if (!file) {
                return;
            }

            $('#edit-stock-image-preview').attr('src', URL.createObjectURL(file));
        });

        $('#edit-stock-form').on('submit', function (e) {
            e.preventDefault();

            const saveBtn = $('#edit-stock-save-btn');
            const spinner = $('#edit-stock-spinner');
            const formData = new FormData(this);

            saveBtn.prop('disabled', true);
            spinner.removeClass('d-none');

            $.ajax({
                url: appUrl + '/admin/inventory/update-stock',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
            })
                .done(function () {
                    editStockModal.hide();
                    stockBalanceTable.ajax.reload(null, false);
                })
                .fail(function (xhr) {
                    const errors = xhr.responseJSON?.errors;
                    let message = xhr.responseJSON?.message || @json(__('inventory.update_failed'));

                    if (errors) {
                        message = Object.values(errors).flat().join('\n');
                    }

                    alert(message);
                })
                .always(function () {
                    saveBtn.prop('disabled', false);
                    spinner.addClass('d-none');
                });
        });
        }
    </script>
@endsection
