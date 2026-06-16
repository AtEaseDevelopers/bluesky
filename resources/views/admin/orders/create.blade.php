@extends('layouts.admin')
@section('title', 'Manage Orders')
@section('content')

    <form method="POST" action="{{ route('admin.orders.store') }}" enctype="multipart/form-data" class="form-wrapper">
        @csrf
        <input type="hidden" id="id" name="customer_id" value="" />
        <div class="row">
            <div class="col-md-8">
                <div class="card shadow no-border mb-0">
                    <div class="card-body">
                        <h5 class="mb-4">Customer Details</h5>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="is_walk_in" id="is_walk_in" value="1">
                                    <label class="form-check-label" for="is_walk_in">Walk-in customer (no account)</label>
                                </div>
                            </div>
                        </div>
                        <div id="walk_in_fields" class="row d-none mb-3">
                            <div class="col-md-6">
                                <label class="mb-2">Walk-in Name <span class="text-danger">*</span></label>
                                <input type="text" name="walk_in_name" id="walk_in_name" class="form-control" value="{{ old('walk_in_name') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="mb-2">Walk-in Phone <span class="text-danger">*</span></label>
                                <input type="text" name="walk_in_phone" id="walk_in_phone" class="form-control" value="{{ old('walk_in_phone') }}">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="mb-2">Delivery Slot</label>
                                <select name="delivery_slot_id" class="form-select">
                                    <option value="">None</option>
                                    @foreach ($deliverySlots as $slot)
                                        <option value="{{ $slot->id }}">{{ $slot->slot_date->format('d-m-Y') }} — {{ $slot->time_label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="mb-2">Assign Driver</label>
                                <select name="driver_id" class="form-select">
                                    <option value="">None</option>
                                    @foreach ($drivers as $id => $lorry)
                                        <option value="{{ $id }}">{{ $lorry }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row" id="order_customer_row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="order_customer">Customer</label>
                                    <span class="text-danger"> *</span>
                                    <select class="form-select" name="customer" id="order_customer" onchange="init_customer_details()">
                                        <option value="">Choose Customer...</option>
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
                                        <label class="mb-2" for="attn_name">Attn. Name</label>
                                        <input type="text" class="form-control" name="attn_name" id="attn_name" value="{{ old('attn_name') }}" placeholder="Enter Attn. Name (optional)">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="mb-2" for="attn_contact">Attn. Contact</label>
                                        <input type="text" class="form-control" name="attn_contact" id="attn_contact" value="{{ old('attn_contact') }}" placeholder="Enter Attn. Contact (optional)">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="mb-2" for="area">Select Area</label>
                                        <select class="form-select @error('area') is-invalid @enderror"  id="area" name="area">
                                            <option value="">Choose...</option>
                                            @foreach ($areaList as $area)
                                                <option value="{{ $area }}" {{ old('area') == $area ? 'selected' : '' }}>
                                                    {{ $area }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <!--<div class="col-md-6">-->
                                <!--    <div class="mb-4">-->
                                <!--        <label class="mb-2" for="billing_city">Billing City</label>-->
                                <!--        <span class="text-danger"> *</span>-->
                                <!--        <input type="text" class="form-control" name="billing_city" id="billing_city" value="{{ old('billing_city') }}" placeholder="Enter billing city">-->
                                <!--    </div>-->
                                <!--</div>-->
                                <!--<div class="col-md-6">-->
                                <!--    <div class="form-group mb-4">-->
                                <!--        <label class="mb-2" for="billing_postcode">Billing Postcode</label>-->
                                <!--        <span class="text-danger"> *</span>-->
                                <!--        <input id="billing_postcode" name="billing_postcode" value="{{ old('billing_postcode') }}" class="form-control col-4" placeholder="Enter your billing postcode" required/>-->
                                <!--    </div>-->
                                <!--</div>-->
                                <!--<div class="col-md-6">-->
                                <!--    <div class="form-group mb-4">-->
                                <!--        <label class="mb-2" for="billing_state">Billing State</label>-->
                                <!--        <span class="text-danger"> *</span>-->
                                <!--        <select id="billing_state" class="form-select" name="billing_state" required>-->
                                <!--            <option value="">Choose Billing State</option>-->
                                <!--            @foreach($shipping_state_options as $state)-->
                                <!--                <option value="{{ $state }}"{{ old('billing_state') == $state? " selected" : "" }}>{{ $state }}</option>-->
                                <!--            @endforeach-->
                                <!--        </select>-->
                                <!--    </div>-->
                                <!--</div>-->
                                <div class="col-md-12">
                                    <div class="form-group mb-4">
                                        <label class="mb-2" for="billing_address">Billing Address</label>
                                        <span class="text-danger"> *</span>
                                        <textarea id="billing_address" name="billing_address" value="{{ old('billing_address') }}" class="form-control" rows="3" placeholder="Enter your billing address" required>{{ old('billing_address') }}</textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                            </div>
                            <!--<div class="row">-->
                            <!--    <div class="col-md-6">-->
                            <!--        <div class="mb-4">-->
                            <!--            <label class="mb-2" for="shipping_city">Shipping City</label>-->
                            <!--            <input type="text" class="form-control" name="shipping_city" id="shipping_city" value="{{ old('shipping_city') }}" placeholder="Enter shipping city">-->
                            <!--        </div>-->
                            <!--    </div>-->
                            <!--    <div class="col-md-6">-->
                            <!--        <div class="form-group mb-4">-->
                            <!--            <label class="mb-2" for="shipping_postcode">Shipping Postcode</label>-->
                            <!--            <input id="shipping_postcode" name="shipping_postcode" value="{{ old('shipping_postcode') }}" class="form-control col-4" placeholder="Enter your shipping postcode"/>-->
                            <!--        </div>-->
                            <!--    </div>-->
                            <!--    <div class="col-md-6">-->
                            <!--        <div class="form-group mb-4">-->
                            <!--            <label class="mb-2" for="shipping_state">Shipping State</label>-->
                            <!--            <select id="shipping_state" class="form-select" name="shipping_state">-->
                            <!--                <option value="">Choose Shipping State</option>-->
                            <!--                @foreach($shipping_state_options as $state)-->
                            <!--                    <option value="{{ $state }}"{{ old('shipping_state') == $state? " selected" : "" }}>{{ $state }}</option>-->
                            <!--                @endforeach-->
                            <!--            </select>-->
                            <!--        </div>-->
                            <!--    </div>-->
                                <div class="col-md-12">
                                    <div class="form-group mb-4">
                                        <label class="mb-2" for="shipping_address">Shipping Address</label>
                                        <textarea id="shipping_address" name="shipping_address" value="{{ old('shipping_address') }}" class="form-control" rows="3" placeholder="Enter your shipping address">{{ old('shipping_address') }}</textarea>
                                    </div>
                                </div>
                            <!--</div>-->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="mb-2" for="payment_method">Payment Method</label>
                                        <!--<span class="text-danger"> *</span>-->
                                        <select id="payment_method" name="payment_method" class="form-select">
                                            <option value="" selected>-- Select Payment Method --</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-4" id="transferSlipGroup" style="display: none;">
                                        <label class="mb-2" for="transfer_slip">Upload Transfer Slip</label>
                                        <!--<span class="text-danger"> *</span>-->
                                        <input type="file" id="transfer_slip" name="transfer_slip" class="form-control" accept="image/*">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4 d-none" id="add-product-info">
                            <button type="button" class="btn btn-outline-primary mb-4" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                <i class="fa fa-plus" aria-hidden="true"></i> Add Products
                            </button>
                            <div class="alert alert-info">Please add products to this order.</div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                    <div>
                                        <button type="button" class="btn btn-outline-primary px-5 disabled" disabled>
                                            Grand Total: RM <span id="total-price">0.00</span>
                                        </button>
                                    </div>
                                    <div>
                                        <a href="{{ route('admin.orders') }}" class="btn btn-secondary me-2 mb-1">Back</a>
                                        <button type="submit" class="btns-order-action back d-none btn btn-primary me-2 mb-1">Back To Previous Step</button>
                                        <button type="submit" class="btns-order-action next d-none btn btn-primary mb-1">Next Step</button>
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
                        <h5>Order Products</h5>
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
        var order_text = 'Create Order';
        var order_subtext = 'Confirm to create this order? Kindly double check on the order.';
        
        document.addEventListener('DOMContentLoaded', function () {
            toggleTransferSlip();
        });
        $(document).ready(function() {
            
            $('#order_customer').select2();
            
            $('#payment_method').select2({
                placeholder: 'Select a payment method'
            });

            $('#area').select2({
                placeholder: 'Select an area'
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
        });

        function enableWalkInMode() {
            $('#walk_in_fields').removeClass('d-none');
            $('#order_customer_row').addClass('d-none');
            $('#order_customer').prop('disabled', true).val('').trigger('change.select2');
            $('#id').val('');

            var paymentMethod = document.getElementById('payment_method');
            paymentMethod.innerHTML = '<option value="cod" selected>COD</option>';

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