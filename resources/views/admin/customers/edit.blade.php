@extends('layouts.admin')
@section('title', __('customers.edit'))
@section('css')

    <link rel="stylesheet" href="{{ asset('assets/css/select2.min.css') }}" />

@endsection
@section('content')

    <form action="{{ route('admin.customers.update', encrypt($customer->id)) }}" method="POST" enctype="multipart/form-data" class="form-wrapper">
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
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="name" value="{{ old('name') ?: $customer->name }}" placeholder="{{ __('customers.enter_customer_name') }}" required>
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
                                    <input type="text" class="form-control @error('email') is-invalid @enderror" name="email" id="email"
                                        value="{{ old('email') ?: $customer->email }}" placeholder="{{ __('customers.enter_customer_email') }}">
                                    @error('email')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                          <div class="col-md-12">
                                <div class="mb-4">
                                    <label class="mb-2" for="billing_address">{{ __('customers.billing_address') }}</label>
                                    <span class="text-danger"> *</span>
                                    <textarea class="form-control @error('billing_address') is-invalid @enderror" name="billing_address" id="billing_address" rows="3" placeholder="{{ __('customers.enter_billing_address') }}" required>{{ old('billing_address') ?: $customer->billing_address }}</textarea>
                                    @error('billing_address')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        <!--</div>-->
                        <!-- SHIPPING INFO SECTION -->
                        <!--<div class="row">-->
                        <!--    <div class="col-md-6">-->
                        <!--        <div class="mb-4">-->
                        <!--            <label class="mb-2" for="shipping_city">Shipping City</label>-->
                        <!--            <span class="text-danger"> *</span>-->
                        <!--            <input type="text" class="form-control" name="shipping_city" id="shipping_city" value="{{ $customer->shipping_city }}" placeholder="Enter shipping city">-->
                        <!--        </div>-->
                        <!--    </div>-->
                        <!--    <div class="col-md-6">-->
                        <!--        <div class="mb-4">-->
                        <!--            <label class="mb-2" for="shipping_postcode">Shipping Postcode</label>-->
                        <!--            <input type="text" class="form-control @error('shipping_postcode') is-invalid @enderror" name="shipping_postcode" id="shipping_postcode" value="{{ old('shipping_postcode') ?: $customer->shipping_postcode }}" placeholder="Enter shipping postcode">-->
                        <!--            @error('shipping_postcode')-->
                        <!--                <span class="text-danger" role="alert">-->
                        <!--                    <strong>{{ $message }}</strong>-->
                        <!--                </span>-->
                        <!--            @enderror-->
                        <!--        </div>-->
                        <!--    </div>-->
                            <!--<div class="col-md-6">-->
                            <!--    <div class="mb-4">-->
                            <!--        <label class="mb-2" for="shipping_state">Shipping State</label>-->
                            <!--        <select id="shipping_state" class="form-select @error('shipping_state') is-invalid @enderror" name="shipping_state">-->
                            <!--            <option value="">Please select State</option>-->
                            <!--            @foreach ($shipping_state_options as $state)-->
                            <!--                <option value="{{ $state }}" {{ (old('shipping_state') ?: $customer->shipping_state) == $state ? ' selected' : '' }}>-->
                            <!--                    {{ $state }}-->
                            <!--                </option>-->
                            <!--            @endforeach-->
                            <!--        </select>-->
                            <!--        @error('shipping_state')-->
                            <!--            <span class="text-danger" role="alert">-->
                            <!--                <strong>{{ $message }}</strong>-->
                            <!--            </span>-->
                            <!--        @enderror-->
                            <!--    </div>-->
                            <!--</div>-->
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <label class="mb-2" for="shipping_address">{{ __('customers.shipping_address') }}</label>
                                    <textarea class="form-control @error('shipping_address') is-invalid @enderror" name="shipping_address" id="shipping_address" rows="3" placeholder="{{ __('customers.enter_shipping_address') }}">{{ old('shipping_address') ?: $customer->shipping_address }}</textarea>
                                    @error('shipping_address')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
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
                                    <label class="mb-2" for="customerCategory">{{ __('customers.category') }}</label>
                                    <input list="categoryOptions" class="form-control @error('category') is-invalid @enderror" name="category" id="customerCategory" value="{{ old('category') ?: $customer->category }}" placeholder="{{ __('customers.enter_category_optional') }}">
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
                                    <label class="mb-2" for="customer_type">{{ __('customers.customer_type') }}</label>
                                    <select name="customer_type" id="customer_type" class="form-select">
                                        <option value="cod" {{ old('customer_type', $customer->customer_type ?? 'cod') === 'cod' ? 'selected' : '' }}>{{ __('customers.customer_type_cod') }}</option>
                                        <option value="credit" {{ old('customer_type', $customer->customer_type ?? 'cod') === 'credit' ? 'selected' : '' }}>{{ __('customers.customer_type_credit') }}</option>
                                    </select>
                                    <small class="text-muted d-block">{{ __('customers.customer_type_cod_help') }}</small>
                                    <small class="text-muted d-block">{{ __('customers.customer_type_credit_help') }}</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                @include('admin.customers.partials.payment-term-field', [
                                    'customerType' => old('customer_type', $customer->customer_type ?? 'cod'),
                                    'selectedPaymentTermDays' => old('payment_term_days', $customer->payment_term_days ?? 30),
                                ])
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="sql_customer_code">Customer Code
                                        <span data-bs-toggle="tooltip" data-bs-placement="right"
                                            title="Optional ** Used for accounting integration purposes"
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
                                    <input type="text" class="form-control @error('sql_customer_code') is-invalid @enderror" name="sql_customer_code" id="sql_customer_code" value="{{ old('sql_customer_code') ?: $customer->sql_customer_code }}" placeholder="Enter SQL customer code">
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
                                    <input type="text" class="form-control @error('attn_name') is-invalid @enderror" name="attn_name" id="attn_name" value="{{ old('attn_name') ?: $customer->attn_name }}" placeholder="Enter Attn. Name (optional)">
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
                                    <input type="text" class="form-control @error('attn_contact') is-invalid @enderror" name="attn_contact" id="attn_contact" value="{{ old('attn_contact') ?: $customer->attn_contact }}" placeholder="Enter Attn. Contact (optional)">
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
                                    <input type="text" class="form-control @error('fax_no') is-invalid @enderror" name="fax_no" id="fax_no" value="{{ old('fax_no') ?: $customer->fax_no }}" placeholder="Enter Fax Number (optional)">
                                    @error('fax_no')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <label class="mb-2">{{ __('customers.assigned_drivers_lorry') }}</label>
                                    @include('admin.customers.partials.driver-picker', [
                                        'drivers' => $drivers,
                                        'selectedDriverIds' => old('driver_ids', $assigned_driver_ids ?? []),
                                    ])
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="area_id">{{ __('customers.select_area') }}</label>
                                    <select class="form-select @error('area_id') is-invalid @enderror" id="area_id" name="area_id">
                                        <option value="">{{ __('customers.choose') }}</option>
                                        @foreach ($areas as $area)
                                            <option value="{{ $area->id }}" {{ $customer->area == $area->id ? 'selected' : '' }}>
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
                        <hr>                        <div class="collapse" id="visibilityPermissionsCollapse">                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="">{{ __('customers.products_visibility') }}</label>
                                    <a type="button" class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                        <i class="fa fa-plus" aria-hidden="true"></i> {{ __('customers.add_customer_products') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <label class="mb-2" for="attn_contact">{{ __('customers.price_permission') }}</label>
                                    <div class="d-flex mt-2">
                                        <div class="form-check me-3 mb-1">
                                            <label class="form-check-label" for="hide">
                                                <input class="form-check-input" type="radio" name="price_permission" id="hide" value="0" {{ $customer->price_permission == 0 ? 'checked' : '' }}>
                                                {{ __('customers.hide_price') }}
                                            </label>
                                        </div>
                                        <div class="form-check me-3 mb-1">
                                            <label class="form-check-label" for="unhide">
                                                <input class="form-check-input" type="radio" name="price_permission" id="unhide" value="1" {{ $customer->price_permission == 1 ? 'checked' : '' }}>
                                                {{ __('customers.unhide_price') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="mb-2" for="attn_contact">{{ __('customers.invoice_visibility') }}</label>
                                    <div class="d-flex mt-2">
                                        <div class="form-check me-3 mb-1">
                                            <label class="form-check-label" for="hide_invoice">
                                                <input class="form-check-input" type="radio" name="invoice_visibility" id="hide_invoice" value="0" {{ $customer->invoice_visibility == 0 ? 'checked' : '' }}>
                                                {{ __('customers.hide_invoice') }}
                                            </label>
                                        </div>
                                        <div class="form-check me-3 mb-1">
                                            <label class="form-check-label" for="unhide_invoice">
                                                <input class="form-check-input" type="radio" name="invoice_visibility" id="unhide_invoice" value="1" {{ $customer->invoice_visibility == 1 ? 'checked' : '' }}>
                                                {{ __('customers.unhide_invoice') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="mb-2" for="attn_contact">{{ __('customers.invoice_price_visibility') }}</label>
                                    <div class="d-flex mt-2">
                                        <div class="form-check me-3 mb-1">
                                            <label class="form-check-label" for="invoice_price_hide">
                                                <input class="form-check-input" type="radio" name="invoice_price_permission" id="invoice_price_hide" value="0" {{ $customer->invoice_price_permission == 0 ? 'checked' : '' }}>
                                                {{ __('customers.hide_product_price') }}
                                            </label>
                                        </div>
                                        <div class="form-check me-3 mb-1">
                                            <label class="form-check-label" for="invoice_price_unhide">
                                                <input class="form-check-input" type="radio" name="invoice_price_permission" id="invoice_price_unhide" value="1" {{ $customer->invoice_price_permission == 1 ? 'checked' : '' }}>
                                                {{ __('customers.unhide_product_price') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="mb-2" for="remark">{{ __('customers.remark') }}</label>
                                    <textarea class="form-control @error('remark') is-invalid @enderror" name="remark" id="remark" placeholder="{{ __('customers.enter_remark') }}">{{ old('remark') ?: $customer->remark }}</textarea>
                                    @error('remark')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        </div>

                        <!-- BILLING INFO SECTION -->
                        <!--<div class="row">-->
                        <!--    <div class="col-md-6">-->
                        <!--        <div class="mb-4">-->
                        <!--            <label class="mb-2" for="billing_city">Billing City</label>-->
                        <!--            <span class="text-danger"> *</span>-->
                        <!--            <input type="text" class="form-control" name="billing_city" id="billing_city" value="{{ old('billing_city') ?: $customer->billing_city }}" placeholder="Enter billing city">-->
                        <!--        </div>-->
                        <!--    </div>-->
                        <!--    <div class="col-md-6">-->
                        <!--        <div class="mb-4">-->
                        <!--            <label class="mb-2" for="billing_postcode">Billing Postcode</label>-->
                        <!--            <span class="text-danger"> *</span>-->
                        <!--            <input type="text" class="form-control @error('billing_postcode') is-invalid @enderror" name="billing_postcode" id="billing_postcode" value="{{ old('billing_postcode') ?: $customer->billing_postcode }}" placeholder="Enter billing postcode" required>-->
                        <!--            @error('billing_postcode')-->
                        <!--                <span class="text-danger" role="alert">-->
                        <!--                    <strong>{{ $message }}</strong>-->
                        <!--                </span>-->
                        <!--            @enderror-->
                        <!--        </div>-->
                        <!--    </div>-->
                            <!--<div class="col-md-6">-->
                            <!--    <div class="mb-4">-->
                            <!--        <label class="mb-2" for="billing_state">Billing State</label>-->
                            <!--        <span class="text-danger"> *</span>-->
                            <!--        <select id="billing_state" class="form-select @error('billing_state') is-invalid @enderror" name="billing_state" required>-->
                            <!--            <option value="">Please select State</option>-->
                            <!--            @foreach ($shipping_state_options as $state)-->
                            <!--                <option value="{{ $state }}"{{ (old('billing_state') ?: $customer->billing_state) == $state ? ' selected' : '' }}>-->
                            <!--                    {{ $state }}-->
                            <!--                </option>-->
                            <!--            @endforeach-->
                            <!--        </select>-->
                            <!--        @error('billing_state')-->
                            <!--            <span class="text-danger" role="alert">-->
                            <!--                <strong>{{ $message }}</strong>-->
                            <!--            </span>-->
                            <!--        @enderror-->
                            <!--    </div>-->
                            <!--</div>-->

                        <!--</div>-->
                        <!--<h5 class="card-title">Payment Method</h5>-->
                        <!--<hr>-->
                        <!--<div class="row">-->
                        <!--    <div class="col-md-12">-->
                        <!--        <div class="mb-4">-->
                        <!--            <label class="mb-2" for="payment_method">Payment Method<span class="text-danger">*</span></label>-->
                        <!--            <select id="payment_method" class="form-select @error('payment_method') is-invalid @enderror" name="payment_method[]" required multiple>-->
                        <!--                @foreach ($payment_method_options as $payment_method)-->
                        <!--                    <option value="{{ $payment_method }}" {{ in_array($payment_method, old('payment_method', []) ?: $customer->payment_method) ? 'selected' : '' }}>-->
                        <!--                        {{ __('user.payment_method.' . $payment_method) }}-->
                        <!--                    </option>-->
                        <!--                @endforeach-->
                        <!--            </select>-->
                        <!--            @error('payment_method')-->
                        <!--                <span class="text-danger" role="alert">-->
                        <!--                    <strong>{{ $message }}</strong>-->
                        <!--                </span>-->
                        <!--            @enderror-->
                        <!--        </div>-->
                        <!--    </div>-->
                        <!--</div>-->
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow no-border mb-4">
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

    <div class="row">
        <div class="col-md-8">
            @if ($customer->isCreditCustomer())
            <div class="card shadow no-border mb-4">
                <div class="card-body">
                    <h5 class="card-title">{{ __('customers.credit_balance') }}</h5>
                    <hr>
                    @php
                        $creditBalance = (float) ($customer->credit_balance ?? 0);
                    @endphp
                    <div class="d-flex align-items-center flex-wrap gap-3 mb-4">
                        <div>
                            <p class="mb-1 text-muted">{{ __('customers.current_balance') }}</p>
                            <h3 class="mb-0 {{ $creditBalance >= 0 ? 'text-success' : 'text-danger' }}">
                                RM {{ number_format($creditBalance, 2) }}
                            </h3>
                        </div>
                        <div>
                            @if ($creditBalance > 0)
                                <span class="badge bg-success">{{ __('customers.credit_available') }}</span>
                            @elseif ($creditBalance < 0)
                                <span class="badge bg-danger">{{ __('customers.outstanding_balance') }}</span>
                            @else
                                <span class="badge bg-secondary">{{ __('customers.no_credit_balance') }}</span>
                            @endif
                        </div>
                    </div>

                    <form action="{{ route('admin.customers.credit.adjust', encrypt($customer->id)) }}" method="POST" class="form-wrapper mb-4">
                        @csrf
                        <div class="row">
                            <div class="col-md-4">
                                <label class="mb-2" for="credit_amount">{{ __('customers.adjustment_rm') }}</label>
                                <input type="number" step="0.01" class="form-control" name="amount" id="credit_amount" placeholder="{{ __('customers.adjustment_placeholder') }}" required>
                                <small class="text-muted">{{ __('customers.adjustment_help') }}</small>
                            </div>
                            <div class="col-md-8">
                                <label class="mb-2" for="credit_notes">{{ __('customers.reason') }}</label>
                                <input type="text" class="form-control" name="notes" id="credit_notes" placeholder="{{ __('customers.reason_placeholder') }}" required>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('customers.apply_adjustment') }}</button>
                        </div>
                    </form>

                    <h6 class="mb-3">{{ __('customers.credit_log') }}</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('customers.date') }}</th>
                                    <th>{{ __('customers.type') }}</th>
                                    <th>{{ __('customers.amount') }}</th>
                                    <th>{{ __('customers.balance_after') }}</th>
                                    <th>{{ __('customers.order') }}</th>
                                    <th>{{ __('customers.notes') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($credit_logs as $log)
                                    <tr>
                                        <td>{{ $log->created_at->format('d-m-Y H:i') }}</td>
                                        <td>{{ \App\CustomerCreditLog::$types[$log->type] ?? ucfirst(str_replace('_', ' ', $log->type)) }}</td>
                                        <td class="{{ $log->amount >= 0 ? 'text-success' : 'text-danger' }}">
                                            {{ $log->amount >= 0 ? '+' : '' }}{{ number_format($log->amount, 2) }}
                                        </td>
                                        <td>{{ number_format($log->balance_after, 2) }}</td>
                                        <td>{{ $log->order_id ? '#' . $log->order_id : '-' }}</td>
                                        <td>{{ $log->notes ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-muted">{{ __('customers.no_credit_movements') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
            @if (!$customer->hasCompletedRegistration())
            <div class="card shadow no-border mb-4">
                <div class="card-body">
                    <h5 class="card-title">{{ __('customers.registration_link_title') }}</h5>
                    <p class="text-muted">{{ __('customers.registration_link_help') }}</p>
                    @php $registrationUrl = $customer->registrationUrl(); @endphp
                    @if ($registrationUrl)
                        <div class="mb-2">
                            <span class="badge bg-{{ $customer->customer_type === 'credit' ? 'info' : 'secondary' }} text-dark me-2">{{ strtoupper($customer->customer_type) }}</span>
                            <span class="badge bg-light text-dark border">{{ $customer->category }}</span>
                        </div>
                        <input type="text" class="form-control mb-2" id="registrationLink" value="{{ $registrationUrl }}" readonly>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="navigator.clipboard.writeText(document.getElementById('registrationLink').value)">{{ __('customers.copy_link') }}</button>
                            <a href="{{ route('admin.customers.generate-registration-link', $customer->id) }}" class="btn btn-sm btn-primary">{{ __('customers.generate_new_link') }}</a>
                        </div>
                        @if ($customer->registration_token_expires_at)
                            <small class="text-muted d-block mt-2">{{ __('customers.expires', ['date' => $customer->registration_token_expires_at->format('d M Y')]) }}</small>
                        @endif
                    @else
                        <a href="{{ route('admin.customers.generate-registration-link', $customer->id) }}" class="btn btn-sm btn-primary">{{ __('customers.generate_registration_link') }}</a>
                    @endif
                </div>
            </div>
            @else
            <div class="card shadow no-border mb-4">
                <div class="card-body">
                    <div class="alert alert-success mb-0">{{ __('customers.registration_completed_alert', ['date' => $customer->registration_completed_at->format('d M Y')]) }}</div>
                </div>
            </div>
            @endif
            @if ($customer->hasCompletedRegistration())
            <div class="card shadow no-border">
                <div class="card-body">
                    <div class="mb-4">
                        <h5 class="card-title">{{ __('customers.reset_password') }}</h5>
                    </div>
                    <form action="{{ route('admin.customer.update-password') }}" method="POST" class="form-wrapper">
                        @csrf
                        <input type="hidden" name="id" value="{{ encrypt($customer->id) }}">
                        <div class="mb-4">
                            <label class="mb-2" for="new_password">{{ __('customers.new_password') }}</label>
                            <span class="text-danger"> *</span>
                            <input type="password" class="form-control @error('new_password') is-invalid @enderror" name="new_password" id="new_password" placeholder="{{ __('customers.enter_new_password') }}" required>
                            @error('new_password')
                                <span class="text-danger" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                {{ __('customers.reset_password') }}
                                <div class="spinner-border spinner-border-sm d-none" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            @endif
        </div>
    </div>

    @include('admin.includes.add_products_modal')

@endsection
@section('script')

    <script src="{{ asset('assets/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/js/sweetalert.min.js') }}"></script>
    <script>
        var selected_products = {!! json_encode($product_visibilities) !!};
        const productIds = selected_products.map(product => product.product_id);

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
        });

        document.addEventListener('DOMContentLoaded', function () {
            display_selected_products();
             const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]')
            tooltips.forEach(el => new bootstrap.Tooltip(el))

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
