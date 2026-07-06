<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header d-flex align-content-center flex-wrap gap-2">
                <div>
                    <h5 class="modal-title" id="addProductModalLabel">{{ __('orders.add_product') }}</h5>
                    <p class="mx-auto text-muted">{{ __('orders.add_product_intro') }}</p>
                </div>
                <div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
                </div>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="product-search" placeholder="{{ __('orders.product_search_placeholder') }}">
                    <button class="btn bg-transparent border">
                        <i class="fa fa-search"></i>
                    </button>
                </div>
                <div id="productList"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="background-color: lightblue;" id="select-all">{{ __('roles.select_all') }}</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}">{{ __('ui.close') }}</button>
                <button type="button" class="btn btn-primary" id="add-products">{{ __('orders.add_products') }}</button>
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
            walk_in_name_phone_required: @json(__('orders.js.walk_in_name_phone_required')),
            add_product_to_checkout: @json(__('orders.js.add_product_to_checkout')),
            error_occurred: @json(__('orders.js.error_occurred')),
            yes: @json(__('orders.js.yes')),
        },
    };
</script>
