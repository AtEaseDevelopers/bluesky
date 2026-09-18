@extends('layouts.admin')
@section('title', __('orders.edit'))
@section('css')

    <link rel="stylesheet" href="{{ asset('assets/css/select2.min.css') }}" />

@endsection
@section('content')

    <form method="POST" action="{{ route('admin.orders.update', encrypt($order->id)) }}" enctype="multipart/form-data" class="form-wrapper">
        @csrf
        <input type="hidden" id="order_id" name="order_id" value="{{ encrypt($order->id) }}" />
        <input type="hidden" id="id" name="customer_id" value="{{ $order->user_id }}" />
        <div class="row">
            <div class="col-md-8">
                <div class="card shadow no-border">
                    <div class="card-body">
                        <h5 class="mb-4">{{ __('orders.customer_details') }}</h5>

                        {{-- The order's type is fixed after creation; this hidden
                             flag drives the branch below but offers no toggle. --}}
                        <input type="checkbox" name="is_walk_in" id="is_walk_in" value="1" class="d-none" {{ $order->isWalkInOrder() ? 'checked' : '' }}>

                        @if ($order->isWalkInOrder())
                            {{-- Walk-in order: reuse a past walk-in or enter a new one. --}}
                            <div id="walk_in_fields" class="mb-3">
                                <div class="btn-group mb-3" role="group" aria-label="{{ __('orders.customer_type') }}">
                                    <input type="radio" class="btn-check" name="walk_in_source" id="walk_in_source_new" value="new" autocomplete="off" checked>
                                    <label class="btn btn-outline-primary" for="walk_in_source_new">{{ __('orders.walk_in_create_new') }}</label>
                                    <input type="radio" class="btn-check" name="walk_in_source" id="walk_in_source_existing" value="existing" autocomplete="off">
                                    <label class="btn btn-outline-primary" for="walk_in_source_existing">{{ __('orders.walk_in_search_existing') }}</label>
                                </div>
                                <div id="walk_in_search_wrap" class="mb-3 d-none">
                                    <label class="mb-2" for="walk_in_search">{{ __('orders.walk_in_search_existing') }}</label>
                                    <select class="form-select" id="walk_in_search"></select>
                                    <small class="text-muted d-block mt-1">{{ __('orders.walk_in_search_hint') }}</small>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-2">
                                            <label class="mb-2" for="walk_in_name">{{ __('orders.walk_in_name') }} <span class="text-danger">*</span></label>
                                            <input type="text" name="walk_in_name" id="walk_in_name" class="form-control" value="{{ $order->walk_in_name }}">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-2">
                                            <label class="mb-2" for="walk_in_phone">{{ __('orders.walk_in_phone') }} {{ __('product.optional') }}</label>
                                            <input type="text" name="walk_in_phone" id="walk_in_phone" class="form-control" value="{{ $order->walk_in_phone }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            {{-- Registered order: pick another customer account. --}}
                            <div class="row" id="order_customer_row">
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="mb-2" for="order_customer">{{ __('orders.customer') }}</label>
                                        <span class="text-danger"> *</span>
                                        <select class="form-select" name="customer" id="order_customer">
                                            <option value="">{{ __('orders.choose_customer') }}</option>
                                            @foreach ($customers_list as $customer_option)
                                                <option value="{{ $customer_option->id }}" {{ $order->user_id == $customer_option->id ? 'selected' : '' }}>
                                                    {{ $customer_option->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div id="customer_info" class="d-none">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="mb-2" for="attn_name">{{ __('orders.attn_name') }}</label>
                                        <input type="text" class="form-control" name="attn_name" id="attn_name" value="{{ $order->attn_name }}" placeholder="{{ __('orders.attn_name_placeholder') }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="mb-2" for="attn_contact">{{ __('orders.attn_contact') }}</label>
                                        <input type="text" class="form-control" name="attn_contact" id="attn_contact" value="{{ $order->attn_contact }}" placeholder="{{ __('orders.attn_contact_placeholder') }}">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="mb-2" for="area">{{ __('orders.select_area') }}</label>
                                        <select class="form-select @error('area') is-invalid @enderror"  id="area" name="area">
                                            <option value="">{{ __('orders.choose') }}</option>
                                            @foreach ($areas as $area)
                                                <option value="{{ $area->id }}" {{ old('area', \App\Area::selectedIdForStored($order->area)) == $area->id ? 'selected' : '' }}>
                                                    {{ $area->area_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group mb-4">
                                        <label class="mb-2" for="billing_address">{{ __('orders.billing_address') }}</label>
                                        <span class="text-danger"> *</span>
                                        <textarea id="billing_address" name="billing_address" value="{{ $order->billing_address }}" class="form-control" rows="3" placeholder="{{ __('orders.billing_address_placeholder') }}" required>{{ $order->billing_address }}</textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group mb-4">
                                        <label class="mb-2" for="shipping_address">{{ __('orders.shipping_address') }}</label>
                                        <textarea id="shipping_address" name="shipping_address" value="{{ $order->shipping_address }}" class="form-control" rows="3" placeholder="{{ __('orders.shipping_address_placeholder') }}">{{ $order->shipping_address }}</textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="mb-2" for="payment_method">{{ __('orders.payment_method') }}</label>
                                        <span class="text-danger"> *</span>
                                        <select id="payment_method" name="payment_method" class="form-select" data-selected="{{ $order->payment_method }}">
                                            <option value="" selected>{{ __('orders.select_payment_method') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6" id="transferSlipGroup" style="display: none;">
                                    <div class="form-group mb-4">
                                        <label class="mb-2" for="transfer_slip">{{ __('orders.upload_transfer_slip') }}</label>
                                        <span class="text-danger"> *</span>
                                        <input type="file" id="transfer_slip" name="transfer_slip" class="form-control"
                                            accept="{{ \App\OrderPayment::photoProofAcceptAttribute() }}"
                                            capture="{{ \App\OrderPayment::proofCaptureAttribute() }}">
                                        @if($order->transfer_slip_url)
                                            <div class="card p-3">
                                                <img style="width: 70%;" src="{{ $order->transfer_slip_url }}" />
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4 d-none" id="add-product-info">
                            <button type="button" class="btn btn-outline-primary mb-4" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                <i class="fa fa-plus" aria-hidden="true"></i> {{ __('orders.add_products') }}
                            </button>
                            <div class="alert alert-info">{{ __('orders.add_products_hint') }}</div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                    <div>
                                        <button type="button" class="btn btn-outline-primary px-5 disabled" disabled>
                                            {{ __('orders.grand_total_rm') }} <span id="total-price">0.00</span>
                                        </button>
                                    </div>
                                    <div>
                                        <a href="{{ route('admin.orders') }}" class="btn btn-secondary me-2 mb-1">{{ __('ui.back') }}</a>
                                        <button type="submit" class="btns-order-action back d-none btn btn-primary me-2 mb-1">{{ __('orders.back_previous_step') }}</button>
                                        <button type="submit" class="btns-order-action next d-none btn btn-primary mb-1">{{ __('orders.next_step') }}</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow no-border mb-0">
                    <div class="card-body">
                        <h5>{{ __('orders.order_products') }}</h5>
                        <hr>
                        <div id="product_bag-item"></div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @include('admin.includes.add_products_modal')

@endsection
@section('script')

    <script src="{{ asset('assets/js/sweetalert.min.js') }}"></script>
    <script src="{{ asset('assets/js/select2.min.js') }}"></script>
    <script>
        var step = 'customer_info';
        var payment_method_options = {!! json_encode($payment_method_options) !!};
        var selected_products = {!! json_encode($products) !!};
        const productIds = selected_products.map(product => product.product_id);
        var order_text = @json(__('orders.js.update_order'));
        var order_subtext = @json(__('orders.js.update_order_confirm'));
        var selectPaymentMethodPlaceholder = @json(__('orders.select_payment_method'));
        window.selectPaymentMethodPlaceholder = selectPaymentMethodPlaceholder;
        var walkInPaymentMethodKeys = @json($walk_in_payment_method_keys);
        var walkInSearchUrl = @json(route('admin.orders.walk-in-search'));

        // A walk-in order accepts only the walk-in payment methods; rebuild the
        // dropdown from those keys, keeping the order's saved method selected.
        function editRebuildWalkInPaymentMethods() {
            var paymentMethod = document.getElementById('payment_method');
            var selected = paymentMethod.value || paymentMethod.getAttribute('data-selected') || '';
            var html = '<option value="">' + selectPaymentMethodPlaceholder + '</option>';
            walkInPaymentMethodKeys.forEach(function (key) {
                html += '<option value="' + key + '">' + payment_method_options[key] + '</option>';
            });
            paymentMethod.innerHTML = html;
            if (selected) {
                paymentMethod.value = selected;
            }
            if (window.jQuery && jQuery(paymentMethod).data('select2')) {
                jQuery(paymentMethod).val(paymentMethod.value).trigger('change');
            }
        }

        // Walk-in order: show its details panel + walk-in payment methods. The
        // order type is fixed, so there is no registered-mode counterpart.
        function editEnableWalkInMode() {
            $('#billing_address').prop('required', false);
            editRebuildWalkInPaymentMethods();
            $('#customer_info').removeClass('d-none');
            $('form button.next').removeClass('d-none');
            editToggleWalkInSource();
        }

        function editToggleWalkInSource() {
            var useExisting = $('#walk_in_source_existing').is(':checked');
            $('#walk_in_search_wrap').toggleClass('d-none', !useExisting);
        }

        document.addEventListener('DOMContentLoaded', function () {
            toggleTransferSlip();
            display_selected_products();
            document.getElementById('addProductModal').dispatchEvent(new Event('shown.bs.modal', { 'bubbles': true }));
        });

        $(document).ready(function () {
            $('#order_customer').select2({ matcher: adminSelect2UnicodeMatcher });
            $('#payment_method').select2({
                placeholder: @json(__('orders.select_payment_method_placeholder'))
            });
            $('#area').select2({
                placeholder: @json(__('orders.select_area_placeholder'))
            });

            // select2 fires a jQuery change (not a native one), so bind through
            // jQuery to refresh the chosen customer's details + payment methods.
            $('#order_customer').on('change', function () {
                init_customer_details();
            });

            $('input[name="walk_in_source"]').on('change', editToggleWalkInSource);

            // Keep the PIC (attn) fields mirrored with the walk-in identity.
            $('#walk_in_name').on('input', function () { $('#attn_name').val($(this).val()); });
            $('#walk_in_phone').on('input', function () { $('#attn_contact').val($(this).val()); });

            // Reuse a previous walk-in customer via async search of past orders.
            $('#walk_in_search').select2({
                placeholder: @json(__('orders.walk_in_search_placeholder')),
                allowClear: true,
                ajax: {
                    url: walkInSearchUrl,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) { return { q: params.term || '' }; },
                    processResults: function (data) {
                        return {
                            results: (data.results || []).map(function (r, i) {
                                var label = r.phone ? (r.name + ' — ' + r.phone) : r.name;
                                return { id: i, text: label, name: r.name, phone: r.phone };
                            })
                        };
                    },
                },
            });
            $('#walk_in_search').on('select2:select', function (e) {
                var picked = e.params.data;
                $('#walk_in_name').val(picked.name || '').trigger('input');
                $('#walk_in_phone').val(picked.phone || '').trigger('input');
            });

            // Restore the order's starting mode.
            if ($('#is_walk_in').is(':checked')) {
                editEnableWalkInMode();
            } else {
                // Registered order: load the current customer's details + methods.
                document.getElementById('order_customer').dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    </script>

@endsection
