@extends('layouts.admin')
@section('title', __('orders.add'))
@section('content')

    <form method="POST" action="{{ route('admin.orders.store') }}" enctype="multipart/form-data" class="form-wrapper" id="admin-order-create-form" data-form-draft="order-create" data-form-draft-defer>
        @csrf
        <input type="hidden" id="id" name="customer_id" value="" />
        <div class="row">
            <div class="col-md-8">
                <div class="card shadow no-border mb-0">
                    <div class="card-body">
                        <h5 class="mb-4">{{ __('orders.customer_details') }}</h5>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="is_walk_in" id="is_walk_in" value="1">
                                    <label class="form-check-label" for="is_walk_in">{{ __('orders.walk_in_customer') }}</label>
                                </div>
                            </div>
                        </div>
                        <div id="walk_in_fields" class="row d-none mb-3">
                            <div class="col-md-6">
                                <label class="mb-2">{{ __('orders.walk_in_name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="walk_in_name" id="walk_in_name" class="form-control" value="{{ old('walk_in_name') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="mb-2">{{ __('orders.walk_in_phone') }} <span class="text-danger">*</span></label>
                                <input type="text" name="walk_in_phone" id="walk_in_phone" class="form-control" value="{{ old('walk_in_phone') }}">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="mb-2">{{ __('orders.delivery_date') }}</label>
                                <select name="delivery_date" id="create_delivery_date" class="form-select">
                                    <option value="">{{ __('orders.none') }}</option>
                                    @foreach ($deliveryDates as $date)
                                        <option value="{{ $date }}" {{ old('delivery_date') == $date ? 'selected' : '' }}>
                                            {{ \Carbon\Carbon::parse($date)->format('d-m-Y') }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="mb-2">{{ __('orders.delivery_time') }}</label>
                                <select name="delivery_slot_id" id="create_delivery_slot_id" class="form-select" disabled>
                                    <option value="">{{ __('orders.none') }}</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="mb-2">{{ __('orders.fulfillment_type') }}</label>
                                <select name="fulfillment_type" id="fulfillment_type" class="form-select">
                                    <option value="delivery">{{ __('orders.fulfillment_delivery') }}</option>
                                    <option value="pickup">{{ __('orders.fulfillment_pickup') }}</option>
                                </select>
                            </div>
                            <div class="col-md-6" id="create-driver-wrap">
                                <label class="mb-2">{{ __('orders.assign_driver') }}</label>
                                <select name="driver_id" id="driver_id" class="form-select">
                                    <option value="">{{ __('orders.none') }}</option>
                                    @foreach ($drivers as $id => $label)
                                        <option value="{{ $id }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row" id="order_customer_row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="order_customer">{{ __('orders.customer') }}</label>
                                    <span class="text-danger"> *</span>
                                    <select class="form-select" name="customer" id="order_customer" onchange="init_customer_details()">
                                        <option value="">{{ __('orders.choose_customer') }}</option>
                                        @foreach($customers_list as $customer)
                                            <option value="{{ $customer->id }}"{{ ($input['customer'] ?? '') == $customer->id? " selected" : "" }}>
                                                {{ $customer->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div id="customer_info" class="d-none">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="mb-2" for="attn_name">{{ __('orders.attn_name') }}</label>
                                        <input type="text" class="form-control" name="attn_name" id="attn_name" value="{{ old('attn_name') }}" placeholder="{{ __('orders.attn_name_placeholder') }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="mb-2" for="attn_contact">{{ __('orders.attn_contact') }}</label>
                                        <input type="text" class="form-control" name="attn_contact" id="attn_contact" value="{{ old('attn_contact') }}" placeholder="{{ __('orders.attn_contact_placeholder') }}">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="mb-2" for="area">{{ __('orders.select_area') }}</label>
                                        <select class="form-select @error('area') is-invalid @enderror"  id="area" name="area">
                                            <option value="">{{ __('orders.choose') }}</option>
                                            @foreach ($areaList as $area)
                                                <option value="{{ $area }}" {{ old('area') == $area ? 'selected' : '' }}>
                                                    {{ $area }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group mb-4">
                                        <label class="mb-2" for="billing_address">{{ __('orders.billing_address') }}</label>
                                        <span class="text-danger"> *</span>
                                        <textarea id="billing_address" name="billing_address" value="{{ old('billing_address') }}" class="form-control" rows="3" placeholder="{{ __('orders.billing_address_placeholder') }}" required>{{ old('billing_address') }}</textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group mb-4">
                                        <label class="mb-2" for="shipping_address">{{ __('orders.shipping_address') }}</label>
                                        <textarea id="shipping_address" name="shipping_address" value="{{ old('shipping_address') }}" class="form-control" rows="3" placeholder="{{ __('orders.shipping_address_placeholder') }}">{{ old('shipping_address') }}</textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="mb-2" for="payment_method">{{ __('orders.payment_method') }}</label>
                                        <select id="payment_method" name="payment_method" class="form-select">
                                            <option value="" selected>{{ __('orders.select_payment_method') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-4" id="transferSlipGroup" style="display: none;">
                                        <label class="mb-2" for="transfer_slip">{{ __('orders.upload_transfer_slip') }}</label>
                                        <input type="file" id="transfer_slip" name="transfer_slip" class="form-control" accept="image/*">
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
    <script>
        var step = 'customer_info';
        var payment_method_options = {!! json_encode($payment_method_options) !!};
        var selected_products = [];
        var order_text = @json(__('orders.js.create_order'));
        var order_subtext = @json(__('orders.js.create_order_confirm'));
        var selectPaymentMethodPlaceholder = @json(__('orders.select_payment_method'));
        window.selectPaymentMethodPlaceholder = selectPaymentMethodPlaceholder;
        var walkInPaymentMethodKeys = @json(\App\User::walkInOrderPaymentMethodKeys());
        
        var deliverySlotsUrl = @json($deliverySlotsUrl ?? '');
        var oldCreateSlotId = @json(old('delivery_slot_id'));

        function loadCreateDeliverySlots(date) {
            var $slotSelect = $('#create_delivery_slot_id');
            $slotSelect.prop('disabled', true).html('<option value="">{{ __('orders.none') }}</option>');

            if (!date || !deliverySlotsUrl) {
                return;
            }

            $.get(deliverySlotsUrl, { date: date }, function (response) {
                if (!response.slots || !response.slots.length) {
                    $slotSelect.html('<option value="">{{ __('orders.no_delivery_slots_for_date') }}</option>');
                    return;
                }

                var html = '<option value="">{{ __('orders.none') }}</option>';
                response.slots.forEach(function (slot) {
                    var selected = oldCreateSlotId && String(oldCreateSlotId) === String(slot.id) ? ' selected' : '';
                    html += '<option value="' + slot.id + '"' + selected + '>' + slot.label + '</option>';
                });
                $slotSelect.html(html).prop('disabled', false);
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            toggleTransferSlip();
            toggleCreateDriverField();
        });

        function toggleCreateDriverField() {
            var isPickup = document.getElementById('fulfillment_type').value === 'pickup';
            document.getElementById('create-driver-wrap').style.display = isPickup ? 'none' : '';
            document.getElementById('driver_id').disabled = isPickup;
        }

        document.getElementById('fulfillment_type').addEventListener('change', toggleCreateDriverField);
        $(document).ready(function() {
            
            $('#order_customer').select2();
            
            $('#payment_method').select2({
                placeholder: @json(__('orders.select_payment_method_placeholder'))
            });

            $('#area').select2({
                placeholder: @json(__('orders.select_area_placeholder'))
            });
            
            $('#payment_method').on('change', function() {
                let val = $(this).val()
                
                console.debug(val)
                if (val == 'bank-transfer') {
                    $('#transferSlipGroup').css('display', 'block')
                } else {
                    $('#transferSlipGroup').css('display', 'none')
                }
            });

            $('#is_walk_in').on('change', function () {
                if ($(this).is(':checked')) {
                    enableWalkInMode();
                } else {
                    disableWalkInMode();
                }
            });

            $('#walk_in_name').on('input', function () {
                $('#attn_name').val($(this).val());
            });
            $('#walk_in_phone').on('input', function () {
                $('#attn_contact').val($(this).val());
            });

            @if (old('is_walk_in'))
                $('#is_walk_in').prop('checked', true).trigger('change');
            @endif

            $('#create_delivery_date').on('change', function () {
                oldCreateSlotId = null;
                loadCreateDeliverySlots($(this).val());
            });

            var orderForm = document.getElementById('admin-order-create-form');
            if (orderForm && window.FormDraft) {
                FormDraft.restore(orderForm).then(function (restored) {
                    if (!restored && $('#create_delivery_date').val()) {
                        loadCreateDeliverySlots($('#create_delivery_date').val());
                    }
                });
            }
        });

        function applyWalkInUiForRestore() {
            $('#walk_in_fields').removeClass('d-none');
            $('#order_customer_row').addClass('d-none');
            $('#order_customer').prop('disabled', true);
            $('#id').val('');

            var paymentMethod = document.getElementById('payment_method');
            var html = '<option value="">' + selectPaymentMethodPlaceholder + '</option>';
            walkInPaymentMethodKeys.forEach(function(key) {
                html += '<option value="' + key + '">' + payment_method_options[key] + '</option>';
            });
            paymentMethod.innerHTML = html;

            $('#customer_info').removeClass('d-none');
            $('form button.next').removeClass('d-none');
        }

        function applyOrderCreateStepUi(currentStep) {
            var customerInfo = document.getElementById('customer_info');
            var addProductInfo = document.getElementById('add-product-info');
            var backBtn = document.querySelector('button.back');
            var orderCustomer = document.getElementById('order_customer');

            if (currentStep === 'select_products') {
                customerInfo.classList.add('d-none');
                addProductInfo.classList.remove('d-none');
                if (backBtn) {
                    backBtn.classList.remove('d-none');
                }
                if (orderCustomer) {
                    orderCustomer.disabled = true;
                    if (window.jQuery) {
                        jQuery(orderCustomer).trigger('change.select2');
                    }
                }
            } else {
                customerInfo.classList.remove('d-none');
                addProductInfo.classList.add('d-none');
                if (backBtn) {
                    backBtn.classList.add('d-none');
                }
                if (orderCustomer && !document.getElementById('is_walk_in').checked) {
                    orderCustomer.disabled = false;
                    if (window.jQuery) {
                        jQuery(orderCustomer).trigger('change.select2');
                    }
                }
            }
        }

        function finishOrderCreateRestore(fields, extra) {
            if (extra && extra.payment_method) {
                var paymentMethod = document.getElementById('payment_method');
                if (paymentMethod) {
                    paymentMethod.value = extra.payment_method;
                    if (window.jQuery && jQuery(paymentMethod).data('select2')) {
                        jQuery(paymentMethod).val(extra.payment_method).trigger('change');
                    }
                    toggleTransferSlip();
                }
            }

            applyOrderCreateStepUi(extra && extra.step ? extra.step : 'customer_info');

            if (selected_products.length) {
                display_selected_products();
            }

            if (fields && fields.delivery_date) {
                oldCreateSlotId = fields.delivery_slot_id || null;
                loadCreateDeliverySlots(fields.delivery_date);
            }
        }

        FormDraft.registerHook('order-create', {
            save: function () {
                var orderCustomer = document.getElementById('order_customer');
                return {
                    step: step,
                    selected_products: selected_products,
                    payment_method: document.getElementById('payment_method') ? document.getElementById('payment_method').value : null,
                    customer: orderCustomer ? orderCustomer.value : '',
                    customer_id: document.getElementById('id') ? document.getElementById('id').value : '',
                };
            },
            restore: function (form, fields, extra) {
                extra = extra || {};
                step = extra.step || 'customer_info';
                selected_products = extra.selected_products || [];

                if (document.getElementById('is_walk_in').checked) {
                    applyWalkInUiForRestore();
                    finishOrderCreateRestore(fields, extra);
                    return Promise.resolve(true);
                }

                var customerId = fields.customer || extra.customer || document.getElementById('order_customer').value;
                if (customerId) {
                    $('#order_customer').val(customerId);
                    document.getElementById('id').value = fields.customer_id || extra.customer_id || customerId;
                    $('#order_customer').trigger('change.select2');
                }

                if (!customerId) {
                    finishOrderCreateRestore(fields, extra);
                    return Promise.resolve(true);
                }

                return init_customer_details({ paymentMethodsOnly: true }).then(function () {
                    finishOrderCreateRestore(fields, extra);
                    return true;
                }).catch(function () {
                    finishOrderCreateRestore(fields, extra);
                    return false;
                });
            },
        });

        function enableWalkInMode() {
            $('#walk_in_fields').removeClass('d-none');
            $('#order_customer_row').addClass('d-none');
            $('#order_customer').prop('disabled', true).val('').trigger('change.select2');
            $('#id').val('');

            var paymentMethod = document.getElementById('payment_method');
            var html = '<option value="">' + selectPaymentMethodPlaceholder + '</option>';
            walkInPaymentMethodKeys.forEach(function(key) {
                html += '<option value="' + key + '">' + payment_method_options[key] + '</option>';
            });
            paymentMethod.innerHTML = html;

            $('#customer_info').removeClass('d-none');
            $('form button.next').removeClass('d-none');

            $('#productList').html('');
            selected_products = [];
            $('#product_bag-item').html('');
            $('#total-price').text('0.00');
        }

        function disableWalkInMode() {
            $('#walk_in_fields').addClass('d-none');
            $('#order_customer_row').removeClass('d-none');
            $('#order_customer').prop('disabled', false);
            $('#customer_info').addClass('d-none');
            $('form button.next').addClass('d-none');
            $('form button.back').addClass('d-none');
            $('#add-product-info').addClass('d-none');
            step = 'customer_info';
        }
    </script>

@endsection
