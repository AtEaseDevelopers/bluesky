@extends('layouts.admin')
@section('title', __('orders.edit'))
@section('css')

    <link rel="stylesheet" href="{{ asset('assets/css/select2.min.css') }}" />

@endsection
@section('content')

    <form method="POST" action="{{ route('admin.orders.update', encrypt($order->id)) }}" enctype="multipart/form-data" class="form-wrapper">
        @csrf
        <input type="hidden" id="order_id" name="order_id" value="{{ encrypt($order->id) }}" />
        <input type="hidden" id="customer_id" name="customer_id" value="{{ encrypt($order->user_id) }}" />
        <div class="row">
            <div class="col-md-8">
                <div class="card shadow no-border">
                    <div class="card-body">
                        <h5 class="mb-4">{{ __('orders.customer_details') }}</h5>
                    
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="order_customer">{{ __('orders.customer') }}</label>
                                    <span class="text-danger"> *</span>
                                    <select class="form-select" name="customer" id="order_customer">
                                        <option value="{{ $customer->id }}" selected>
                                            {{ $customer->name }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

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
                                        <input type="file" id="transfer_slip" name="transfer_slip" class="form-control" accept="image/*">
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
        window.selectPaymentMethodPlaceholder = @json(__('orders.select_payment_method'));
        
        document.addEventListener('DOMContentLoaded', function () {
            toggleTransferSlip();
            display_selected_products();

            const customerSelect = document.getElementById('order_customer');
            if (customerSelect) {
                customerSelect.disabled = true;
                document.querySelector('select#order_customer').dispatchEvent(new Event('change', { 'bubbles': true }));
                document.getElementById('addProductModal').dispatchEvent(new Event('shown.bs.modal', { 'bubbles': true }));
            }
        });
        $(document).ready(function() {
            $('#payment_method').select2({
                placeholder: @json(__('orders.select_payment_method_placeholder'))
            });

            $('#area').select2({
                placeholder: @json(__('orders.select_area_placeholder'))
            });
        });
    </script>

@endsection
