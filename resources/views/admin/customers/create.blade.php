@extends('layouts.admin')
@section('title', __('customers.add'))
@section('css')

    <link rel="stylesheet" href="{{ asset('assets/css/select2.min.css') }}" />

@endsection
@section('content')

    <form action="{{ route('admin.customers.store') }}" method="POST" enctype="multipart/form-data" class="form-wrapper">
        @csrf
        <div class="row">
            <div class="col-md-8">
                <div class="card shadow no-border mb-4">
                    <div class="card-body">
                        <!-- GENERAL INFO SECTION -->
                        <h5 class="card-title">{{ __('customers.general_info') }}</h5>
                        <hr>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="name">{{ __('customers.customer_name') }}</label>
                                    <span class="text-danger"> *</span>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror"
                                        name="name" id="name" value="{{ old('name') }}"
                                        placeholder="{{ __('customers.enter_customer_name') }}" required>
                                    @error('name')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="email">{{ __('customers.customer_email') }}</label>
                                    <input type="text" class="form-control @error('email') is-invalid @enderror"
                                        name="email" id="email" value="{{ old('email') }}"
                                        placeholder="{{ __('customers.enter_customer_email') }}">
                                    @error('email')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                         <!-- BILLING INFO SECTION -->

                        <div class="row">
                            <!--<div class="col-md-6">-->
                            <!--    <div class="mb-4">-->
                            <!--        <label class="mb-2" for="billing_city">Billing City</label>-->
                            <!--        <span class="text-danger"> *</span>-->
                            <!--        <input type="text" class="form-control" name="billing_city" id="billing_city" value="{{ old('billing_city') }}" placeholder="Enter billing city">-->
                            <!--    </div>-->
                            <!--</div>-->
                            <!--<div class="col-md-6">-->
                            <!--    <div class="mb-4">-->
                            <!--        <label class="mb-2" for="billing_postcode">Billing Postcode</label>-->
                            <!--        <span class="text-danger"> *</span>-->
                            <!--        <input type="text" class="form-control @error('billing_postcode') is-invalid @enderror" name="billing_postcode" id="billing_postcode" value="{{ old('billing_postcode') }}" placeholder="Enter billing postcode" required>-->
                            <!--        @error('billing_postcode')
        -->
                                <!--            <span class="text-danger" role="alert">-->
                                <!--                <strong>{{ $message }}</strong>-->
                                <!--            </span>-->
                                <!--
    @enderror-->
                            <!--    </div>-->
                            <!--</div>-->
                            <!--<div class="col-md-6">-->
                            <!--    <div class="mb-4">-->
                            <!--        <label class="mb-2" for="billing_state">Billing State</label>-->
                            <!--        <span class="text-danger"> *</span>-->
                            <!--        <select id="billing_state" class="form-select @error('billing_state') is-invalid @enderror" name="billing_state" required>-->
                            <!--            <option value="">Please select State</option>-->
                            <!--            @foreach ($shipping_state_options as $state)
    -->
                            <!--                <option value="{{ $state }}"{{ old('billing_state') == $state ? ' selected' : '' }}>-->
                            <!--                    {{ $state }}-->
                            <!--                </option>-->
                            <!--
    @endforeach-->
                            <!--        </select>-->
                            <!--        @error('billing_state')
        -->
                                <!--            <span class="text-danger" role="alert">-->
                                <!--                <strong>{{ $message }}</strong>-->
                                <!--            </span>-->
                                <!--
    @enderror-->
                            <!--    </div>-->
                            <!--</div>-->
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <label class="mb-2" for="billing_address">{{ __('customers.billing_address') }}</label>
                                    <span class="text-danger"> *</span>
                                    <textarea class="form-control @error('billing_address') is-invalid @enderror" name="billing_address"
                                        id="billing_address" rows="3" placeholder="{{ __('customers.enter_billing_address') }}" required>{{ old('billing_address') }}</textarea>
                                    @error('billing_address')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <!--<div class="col-md-6">-->
                            <!--    <div class="mb-4">-->
                            <!--        <label class="mb-2" for="shipping_city">Shipping City</label>-->
                            <!--        <span class="text-danger"> *</span>-->
                            <!--        <input type="text" class="form-control" name="shipping_city" id="shipping_city" value="{{ old('shipping_city') }}" placeholder="Enter shipping city">-->
                            <!--    </div>-->
                            <!--</div>-->
                            <!--<div class="col-md-6">-->
                            <!--    <div class="mb-4">-->
                            <!--        <label class="mb-2" for="shipping_postcode">Shipping Postcode</label>-->
                            <!--        <input type="text" class="form-control @error('shipping_postcode') is-invalid @enderror" name="shipping_postcode" id="shipping_postcode" value="{{ old('shipping_postcode') }}" placeholder="Enter shipping postcode">-->
                            <!--        @error('shipping_postcode')
        -->
                                <!--            <span class="text-danger" role="alert">-->
                                <!--                <strong>{{ $message }}</strong>-->
                                <!--            </span>-->
                                <!--
    @enderror-->
                            <!--    </div>-->
                            <!--</div>-->
                            <!--<div class="col-md-6">-->
                            <!--    <div class="mb-4">-->
                            <!--        <label class="mb-2" for="shipping_state">Shipping State</label>-->
                            <!--        <select id="shipping_state" class="form-select @error('shipping_state') is-invalid @enderror" name="shipping_state">-->
                            <!--            <option value="">Please select State</option>-->
                            <!--            @foreach ($shipping_state_options as $state)
    -->
                            <!--                <option value="{{ $state }}" {{ old('shipping_state') == $state ? ' selected' : '' }}>-->
                            <!--                    {{ $state }}-->
                            <!--                </option>-->
                            <!--
    @endforeach-->
                            <!--        </select>-->
                            <!--        @error('shipping_state')
        -->
                                <!--            <span class="text-danger" role="alert">-->
                                <!--                <strong>{{ $message }}</strong>-->
                                <!--            </span>-->
                                <!--
    @enderror-->
                            <!--    </div>-->
                            <!--</div>-->
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <label class="mb-2" for="shipping_address">{{ __('customers.shipping_address') }}</label>
                                    <textarea class="form-control @error('shipping_address') is-invalid @enderror" name="shipping_address"
                                        id="shipping_address" rows="3" placeholder="{{ __('customers.enter_shipping_address') }}">{{ old('shipping_address') }}</textarea>
                                    @error('shipping_address')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- ADVANCED INFO SECTION -->
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <h5 class="card-title mb-0">{{ __('customers.advanced_info') }}</h5>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#advancedInfoCollapse" aria-expanded="false" aria-controls="advancedInfoCollapse">
                                <i class="fa fa-chevron-down" aria-hidden="true"></i>
                            </button>
                        </div>
                        <hr>
                        <div class="collapse" id="advancedInfoCollapse">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="customer_type">{{ __('customers.customer_type') }}</label>
                                    <select name="customer_type" id="customer_type" class="form-select">
                                        <option value="cod" {{ old('customer_type', 'cod') === 'cod' ? 'selected' : '' }}>{{ __('customers.customer_type_cod') }}</option>
                                        <option value="credit" {{ old('customer_type') === 'credit' ? 'selected' : '' }}>{{ __('customers.customer_type_credit') }}</option>
                                    </select>
                                    <small class="text-muted d-block">{{ __('customers.customer_type_cod_help') }}</small>
                                    <small class="text-muted d-block">{{ __('customers.customer_type_credit_help') }}</small>
                                    <small class="text-muted">{{ __('customers.customer_type_credit_help_extra') }}</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                @include('admin.customers.partials.payment-term-field', [
                                    'customerType' => old('customer_type', 'cod'),
                                    'selectedPaymentTermDays' => old('payment_term_days', 30),
                                ])
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="customerCategory">{{ __('customers.category') }}</label>
                                    <input list="categoryOptions"
                                        class="form-control @error('category') is-invalid @enderror" name="category"
                                        id="customerCategory" value="{{ old('category') }}"
                                        placeholder="{{ __('customers.enter_category_optional') }}">
                                    <datalist id="categoryOptions">
                                        @foreach ($category_list as $category)
                                            <option value="{{ $category->category }}"></option>
                                        @endforeach
                                    </datalist>
                                    @error('category')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="sql_customer_code">
                                        {{ __('customers.customer_code') }}
                                        <span data-bs-toggle="tooltip" data-bs-placement="right"
                                            title="{{ __('customers.customer_code_tooltip') }}"
                                            style="cursor: pointer;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                                fill="currentColor" class="bi bi-info-circle text-muted mb-1"
                                                viewBox="0 0 16 16">
                                                <path
                                                    d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16" />
                                                <path
                                                    d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0" />
                                            </svg>
                                        </span>
                                    </label>
                                    <input type="text"
                                        class="form-control @error('sql_customer_code') is-invalid @enderror"
                                        name="sql_customer_code" id="sql_customer_code"
                                        value="{{ old('sql_customer_code') }}" placeholder="{{ __('customers.enter_customer_code') }}">
                                    @error('sql_customer_code')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="attn_name">{{ __('customers.attn_name') }}</label>
                                    <input type="text" class="form-control @error('attn_name') is-invalid @enderror"
                                        name="attn_name" id="attn_name" value="{{ old('attn_name') }}"
                                        placeholder="{{ __('customers.enter_attn_name') }}">
                                    @error('attn_name')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="attn_contact">{{ __('customers.attn_contact') }}</label>
                                    <input type="text" class="form-control @error('attn_contact') is-invalid @enderror"
                                        name="attn_contact" id="attn_contact" value="{{ old('attn_contact') }}"
                                        placeholder="{{ __('customers.enter_attn_contact') }}">
                                    @error('attn_contact')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="fax_no">{{ __('customers.fax_no') }}</label>
                                    <input type="text" class="form-control @error('fax_no') is-invalid @enderror"
                                        name="fax_no" id="fax_no" value="{{ old('fax_no') }}"
                                        placeholder="{{ __('customers.enter_fax_no') }}">
                                    @error('fax_no')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="default_driver_id">{{ __('customers.select_lorry') }}</label>
                                    <select class="form-select @error('default_driver_id') is-invalid @enderror"
                                        id="default_driver_id" name="default_driver_id">
                                        <option value="">{{ __('customers.choose') }}</option>
                                        @foreach ($drivers as $driver)
                                            <option value="{{ $driver->id }}"
                                                {{ old('default_driver_id') ? 'selected' : '' }}>
                                                {{ $driver->lorry_number }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="area_id">{{ __('customers.select_area') }}</label>
                                    <select class="form-select @error('area_id') is-invalid @enderror" id="area_id"
                                        name="area_id">
                                        <option value="">{{ __('customers.choose') }}</option>
                                        @foreach ($areas as $area)
                                            <option value="{{ $area->id }}"
                                                {{ old('area') == $area->id ? 'selected' : '' }}>
                                                {{ $area->area_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        </div>

                        <!-- VISIBILITY & PERMISSIONS SECTION -->
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <h5 class="card-title mb-0">{{ __('customers.visibility_permissions') }}</h5>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#visibilityPermissionsCollapse" aria-expanded="false" aria-controls="visibilityPermissionsCollapse">
                                <i class="fa fa-chevron-down" aria-hidden="true"></i>
                            </button>
                        </div>
                        <hr>
                        <div class="collapse" id="visibilityPermissionsCollapse">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2">{{ __('customers.products_visibility') }}</label>
                                    <button type="button" class="btn btn-outline-primary w-100" data-bs-toggle="modal"
                                        data-bs-target="#addProductModal">
                                        <i class="fa fa-plus" aria-hidden="true"></i> {{ __('customers.add_customer_products') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <label class="mb-2">{{ __('customers.price_permission') }}</label>
                                    <div class="d-flex mt-2">
                                        <div class="form-check me-3 mb-1">
                                            <label class="form-check-label" for="hide">
                                                <input class="form-check-input" type="radio" name="price_permission"
                                                    id="hide" value="0" checked>
                                                {{ __('customers.hide_price') }}
                                            </label>
                                        </div>
                                        <div class="form-check me-3 mb-1">
                                            <label class="form-check-label" for="unhide">
                                                <input class="form-check-input" type="radio" name="price_permission"
                                                    id="unhide" value="1">
                                                {{ __('customers.unhide_price') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="mb-2">{{ __('customers.invoice_visibility') }}</label>
                                    <div class="d-flex mt-2">
                                        <div class="form-check me-3 mb-1">
                                            <label class="form-check-label" for="hide_invoice">
                                                <input class="form-check-input" type="radio" name="invoice_visibility"
                                                    id="hide_invoice" value="0" checked>
                                                {{ __('customers.hide_invoice') }}
                                            </label>
                                        </div>
                                        <div class="form-check me-3 mb-1">
                                            <label class="form-check-label" for="unhide_invoice">
                                                <input class="form-check-input" type="radio" name="invoice_visibility"
                                                    id="unhide_invoice" value="1">
                                                {{ __('customers.unhide_invoice') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="mb-2">{{ __('customers.invoice_price_visibility') }}</label>
                                    <div class="d-flex mt-2">
                                        <div class="form-check me-3 mb-1">
                                            <label class="form-check-label" for="invoice_price_hide">
                                                <input class="form-check-input" type="radio"
                                                    name="invoice_price_permission" id="invoice_price_hide"
                                                    value="0" checked>
                                                {{ __('customers.hide_product_price') }}
                                            </label>
                                        </div>
                                        <div class="form-check me-3 mb-1">
                                            <label class="form-check-label" for="invoice_price_unhide">
                                                <input class="form-check-input" type="radio"
                                                    name="invoice_price_permission" id="invoice_price_unhide"
                                                    value="1">
                                                {{ __('customers.unhide_product_price') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="mb-2" for="remark">{{ __('customers.remark') }}</label>
                                    <textarea class="form-control @error('remark') is-invalid @enderror" name="remark" id="remark"
                                        placeholder="{{ __('customers.enter_remark') }}">{{ old('remark') }}</textarea>
                                    @error('remark')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        </div>


                        <!--<h5 class="card-title">Payment Method</h5>-->
                        <!--<hr>-->
                        <!--<div class="row">-->
                        <!--    <div class="col-md-12">-->
                        <!--        <div class="mb-4">-->
                        <!--            <label class="mb-2" for="payment_method">Payment Method<span class="text-danger">*</span></label>-->
                        <!--            <select id="payment_method" class="form-select @error('payment_method') is-invalid @enderror" placeholder="Select payment method" name="payment_method[]" required multiple>-->
                        <!--                @foreach ($payment_method_options as $payment_method)
    -->
                        <!--                    <option value="{{ $payment_method }}"{{ in_array($payment_method, old('payment_method', [])) ? 'selected' : '' }}>-->
                        <!--                        {{ __('user.payment_method.' . $payment_method) }}-->
                        <!--                    </option>-->
                        <!--
    @endforeach-->
                        <!--            </select>-->
                        <!--            @error('payment_method')
        -->
                            <!--                <span class="text-danger" role="alert">-->
                            <!--                    <strong>{{ $message }}</strong>-->
                            <!--                </span>-->
                            <!--
    @enderror-->
                        <!--        </div>-->
                        <!--    </div>-->
                        <!--</div>-->
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow no-border mb-0">
                    <div class="card-body">
                        <h5 class="card-title">{{ __('customers.customer_products') }}</h5>
                        <hr>
                        <div id="product_bag-item"></div>
                        <div class="d-flex justify-content-end mt-4">
                            <a href="{{ route('admin.customers') }}" class="btn btn-secondary px-4 me-2 mb-1">{{ __('ui.back') }}</a>
                            <button type="submit" class="btn btn-primary px-4 mb-1">
                                {{ __('ui.save') }}
                                <div class="spinner-border spinner-border-sm d-none" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @include('admin.includes.add_products_modal')

@endsection
@section('script')

    <script src="{{ asset('assets/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/js/sweetalert.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]')
            tooltips.forEach(el => new bootstrap.Tooltip(el))
        })
        var selected_products = [];
        $(document).ready(function() {
            function syncPaymentTermField() {
                const isCredit = $('#customer_type').val() === 'credit';
                $('#payment_term_wrap').toggleClass('d-none', !isCredit);
            }

            $('#customer_type').on('change', syncPaymentTermField);
            syncPaymentTermField();

            $('#payment_method').select2({
                placeholder: 'Select a payment method'
            });

            $('#default_driver_id').select2({
                placeholder: 'Select a default driver'
            });

            $('#area').select2({
                placeholder: 'Select an area'
            });

            $('#customerCategory').on('change blur', function() {
                const category = $(this).val().trim();
                if (category) {
                    fetch(appUrl + '/admin/get-products-for-category', {
                        method: 'POST',
                        body: JSON.stringify({category: category}),
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Response data:', data);
                        if (data.success) {
                            data.products.forEach(product => {
                                // Check if product already added
                                const exists = selected_products.some(p => p.product_id == product.id);
                                if (!exists) {
                                    selected_products.push({
                                        product_id: product.id,
                                        product_name: product.name,
                                        price: product.price,
                                        quantity: '',
                                        weight: '',
                                        remark: '',
                                        total_price: 0
                                    });
                                }
                            });
                            display_selected_products();
                        } else {
                            console.log('No products found for category:', category, 'Message:', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
                }
            });

            // Toggle chevron icons for collapsible sections
            const advancedInfoBtn = document.querySelector('[data-bs-target="#advancedInfoCollapse"]');
            const visibilityPermissionsBtn = document.querySelector('[data-bs-target="#visibilityPermissionsCollapse"]');

            const advancedInfoCollapse = document.getElementById('advancedInfoCollapse');
            const visibilityPermissionsCollapse = document.getElementById('visibilityPermissionsCollapse');

            if (advancedInfoBtn && advancedInfoCollapse) {
                advancedInfoCollapse.addEventListener('hide.bs.collapse', function() {
                    advancedInfoBtn.querySelector('i').classList.remove('fa-chevron-up');
                    advancedInfoBtn.querySelector('i').classList.add('fa-chevron-down');
                });
                advancedInfoCollapse.addEventListener('show.bs.collapse', function() {
                    advancedInfoBtn.querySelector('i').classList.remove('fa-chevron-down');
                    advancedInfoBtn.querySelector('i').classList.add('fa-chevron-up');
                });
            }

            if (visibilityPermissionsBtn && visibilityPermissionsCollapse) {
                visibilityPermissionsCollapse.addEventListener('hide.bs.collapse', function() {
                    visibilityPermissionsBtn.querySelector('i').classList.remove('fa-chevron-up');
                    visibilityPermissionsBtn.querySelector('i').classList.add('fa-chevron-down');
                });
                visibilityPermissionsCollapse.addEventListener('show.bs.collapse', function() {
                    visibilityPermissionsBtn.querySelector('i').classList.remove('fa-chevron-down');
                    visibilityPermissionsBtn.querySelector('i').classList.add('fa-chevron-up');
                });
            }
        });
    </script>

@endsection
