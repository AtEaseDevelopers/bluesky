<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down modal-lg">
        <div class="modal-content">
            <div class="modal-header d-flex align-content-center flex-wrap gap-2">
                <div class="flex-grow-1">
                    <h5 class="modal-title mb-1" id="addProductModalLabel">{{ __('orders.add_product') }}</h5>
                    <p class="mb-0 text-muted small">{{ __('orders.add_product_intro') }}</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="product-search" placeholder="{{ __('orders.product_search_placeholder') }}">
                    <button class="btn bg-transparent border">
                        <i class="fa fa-search"></i>
                    </button>
                </div>
                <div class="form-check mb-3 d-none" id="select-all-products-wrap">
                    <input type="checkbox" class="form-check-input" id="select-all-products">
                    <label class="form-check-label" for="select-all-products">{{ __('roles.select_all') }}</label>
                </div>
                <div id="productList"></div>
            </div>
            <div class="modal-footer flex-wrap gap-2 admin-add-product-modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="select-all">{{ __('roles.select_all') }}</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}">{{ __('ui.close') }}</button>
                <button type="button" class="btn btn-primary flex-grow-1 flex-sm-grow-0" id="add-products">{{ __('orders.add_products') }}</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.orderUiLabels = {
        remove: @json(__('orders.remove')),
        quantity: @json(__('orders.quantity_label')),
        order_qty_kg: @json(__('orders.member.weight_kg')),
        weight_kg: @json(__('orders.member.weight_kg')),
        estimated_weight: @json(__('product.estimated_weight', ['uom' => 'KG'])),
        optional: @json(__('product.optional')),
        price_based_on_weight: @json(__('product.price_based_on_weight')),
        remark: @json(__('orders.remark')),
        price_label: @json(__('orders.price_label')),
        total_price_label: @json(__('orders.total_price_label')),
        sell_in_label: @json(__('orders.sell_in_label')),
        sell_in: {
            qty: @json(__('product.sell_in_qty')),
            weight: @json(__('product.sell_in_weight')),
            qty_bill_weight: @json(__('product.sell_in_qty_bill_weight')),
        },
        js: {
            warning: @json(__('orders.js.warning')),
            error: @json(__('orders.js.error')),
            fill_required_fields: @json(__('orders.js.fill_required_fields')),
            select_product_for_bag: @json(__('orders.js.select_product_for_bag')),
            fill_required_before_proceed: @json(__('orders.js.fill_required_before_proceed')),
            walk_in_name_required: @json(__('orders.js.walk_in_name_required')),
            walk_in_name_phone_required: @json(__('orders.js.walk_in_name_phone_required')),
            add_product_to_checkout: @json(__('orders.js.add_product_to_checkout')),
            error_occurred: @json(__('orders.js.error_occurred')),
            yes: @json(__('orders.js.yes')),
        },
    };
</script>
