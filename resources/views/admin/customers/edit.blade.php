@extends('layouts.admin')
@section('title', 'Edit Customer')
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
                        <h5 class="card-title">General Info</h5>
                        <hr>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="name">Customer Name</label>
                                    <span class="text-danger"> *</span>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="name" value="{{ old('name') ?: $customer->name }}" placeholder="Enter customer name" required>
                                    @error('name')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="email">Customer Email</label>
                                    <input type="text" class="form-control @error('email') is-invalid @enderror" name="email" id="email"
                                        value="{{ old('email') ?: $customer->email }}" placeholder="Enter customer email">
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
                                    <label class="mb-2" for="billing_address">Billing Address</label>
                                    <span class="text-danger"> *</span>
                                    <textarea class="form-control @error('billing_address') is-invalid @enderror" name="billing_address" id="billing_address" rows="3" placeholder="Enter billing address" required>{{ old('billing_address') ?: $customer->billing_address }}</textarea>
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
                                    <label class="mb-2" for="shipping_address">Shipping Address</label>
                                    <textarea class="form-control @error('shipping_address') is-invalid @enderror" name="shipping_address" id="shipping_address" rows="3" placeholder="Enter shipping address">{{ old('shipping_address') ?: $customer->shipping_address }}</textarea>
                                    @error('shipping_address')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>

                        <!-- ADVANCED INFO SECTION -->
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <h5 class="card-title mb-0">Advanced Info</h5>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#advancedInfoCollapse" aria-expanded="false" aria-controls="advancedInfoCollapse">
                                <i class="fa fa-chevron-down" aria-hidden="true"></i>
                            </button>
                        </div>
                        <hr>
                        <div class="collapse" id="advancedInfoCollapse">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="customerCategory">Category</label>
                                    <input list="categoryOptions" class="form-control @error('category') is-invalid @enderror" name="category" id="customerCategory" value="{{ old('category') ?: $customer->category }}" placeholder="Enter customer category (optional)">
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
                                    <label class="mb-2" for="customer_type">Customer Type</label>
                                    <select name="customer_type" id="customer_type" class="form-select">
                                        <option value="cod" {{ old('customer_type', $customer->customer_type ?? 'cod') === 'cod' ? 'selected' : '' }}>COD</option>
                                        <option value="credit" {{ old('customer_type', $customer->customer_type ?? 'cod') === 'credit' ? 'selected' : '' }}>Credit</option>
                                    </select>
                                    <small class="text-muted d-block">COD — pays in full on delivery; no credit balance.</small>
                                    <small class="text-muted d-block">Credit — payment terms, credit balance, and payment due dates on orders.</small>
                                </div>
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
                                    <label class="mb-2" for="attn_name">Attn. Name</label>
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
                                    <label class="mb-2" for="attn_contact">Attn. Contact</label>
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
                                    <label class="mb-2" for="fax_no">Fax No.</label>
                                    <input type="text" class="form-control @error('fax_no') is-invalid @enderror" name="fax_no" id="fax_no" value="{{ old('fax_no') ?: $customer->fax_no }}" placeholder="Enter Fax Number (optional)">
                                    @error('fax_no')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="default_driver_id">Select Lorry</label>
                                    <select id="default_driver_id" class="form-select @error('default_driver_id') is-invalid @enderror" name="default_driver_id">
                                        <option value="">Choose...</option>
                                        @foreach ($drivers as $driver)
                                            <option value="{{ $driver->id }}" {{ $customer->default_driver_id == $driver->id ? 'selected' : '' }}>
                                                {{ $driver->lorry_number }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('default_driver_id')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="area_id">Select Area</label>
                                    <select class="form-select @error('area_id') is-invalid @enderror" id="area_id" name="area_id">
                                        <option value="">Choose...</option>
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
                            <h5 class="card-title mb-0">Visibility & Permissions</h5>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#visibilityPermissionsCollapse" aria-expanded="false" aria-controls="visibilityPermissionsCollapse">
                                <i class="fa fa-chevron-down" aria-hidden="true"></i>
                            </button>
                        </div>
                        <hr>                        <div class="collapse" id="visibilityPermissionsCollapse">                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="">Products Visibility</label>
                                    <a type="button" class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                        <i class="fa fa-plus" aria-hidden="true"></i> Add Customer Products
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <label class="mb-2" for="attn_contact">Product Price Permission</label>
                                    <div class="d-flex mt-2">
                                        <div class="form-check me-3 mb-1">
                                            <label class="form-check-label" for="hide">
                                                <input class="form-check-input" type="radio" name="price_permission" id="hide" value="0" {{ $customer->price_permission == 0 ? 'checked' : '' }}>
                                                Hide Price
                                            </label>
                                        </div>
                                        <div class="form-check me-3 mb-1">
                                            <label class="form-check-label" for="unhide">
                                                <input class="form-check-input" type="radio" name="price_permission" id="unhide" value="1" {{ $customer->price_permission == 1 ? 'checked' : '' }}>
                                                Unhide Price
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="mb-2" for="attn_contact">Invoice Visibility</label>
                                    <div class="d-flex mt-2">
                                        <div class="form-check me-3 mb-1">
                                            <label class="form-check-label" for="hide_invoice">
                                                <input class="form-check-input" type="radio" name="invoice_visibility" id="hide_invoice" value="0" {{ $customer->invoice_visibility == 0 ? 'checked' : '' }}>
                                                Hide Invoice
                                            </label>
                                        </div>
                                        <div class="form-check me-3 mb-1">
                                            <label class="form-check-label" for="unhide_invoice">
                                                <input class="form-check-input" type="radio" name="invoice_visibility" id="unhide_invoice" value="1" {{ $customer->invoice_visibility == 1 ? 'checked' : '' }}>
                                                Unhide Invoice
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="mb-2" for="attn_contact">Invoice Product Price Visibility</label>
                                    <div class="d-flex mt-2">
                                        <div class="form-check me-3 mb-1">
                                            <label class="form-check-label" for="invoice_price_hide">
                                                <input class="form-check-input" type="radio" name="invoice_price_permission" id="invoice_price_hide" value="0" {{ $customer->invoice_price_permission == 0 ? 'checked' : '' }}>
                                                Hide Product Price
                                            </label>
                                        </div>
                                        <div class="form-check me-3 mb-1">
                                            <label class="form-check-label" for="invoice_price_unhide">
                                                <input class="form-check-input" type="radio" name="invoice_price_permission" id="invoice_price_unhide" value="1" {{ $customer->invoice_price_permission == 1 ? 'checked' : '' }}>
                                                Unhide Product Price
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="mb-2" for="remark">Remark</label>
                                    <textarea class="form-control @error('remark') is-invalid @enderror" name="remark" id="remark" placeholder="Enter customer remark">{{ old('remark') ?: $customer->remark }}</textarea>
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
                        <h5 class="card-title">Customer Products</h5>
                        <hr>
                        <div id="product_bag-item"></div>
                        <div class="d-flex justify-content-end mt-4">
                            <a href="{{ route('admin.customers') }}" class="btn btn-secondary px-4 me-2 mb-1">Back</a>
                            <button type="submit" class="btn btn-primary px-4 mb-1">
                                Save
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
                    <h5 class="card-title">Credit Balance</h5>
                    <hr>
                    @php
                        $creditBalance = (float) ($customer->credit_balance ?? 0);
                    @endphp
                    <div class="d-flex align-items-center flex-wrap gap-3 mb-4">
                        <div>
                            <p class="mb-1 text-muted">Current Balance</p>
                            <h3 class="mb-0 {{ $creditBalance >= 0 ? 'text-success' : 'text-danger' }}">
                                RM {{ number_format($creditBalance, 2) }}
                            </h3>
                        </div>
                        <div>
                            @if ($creditBalance > 0)
                                <span class="badge bg-success">Credit available — auto-applied on next order</span>
                            @elseif ($creditBalance < 0)
                                <span class="badge bg-danger">Outstanding balance</span>
                            @else
                                <span class="badge bg-secondary">No credit balance</span>
                            @endif
                        </div>
                    </div>

                    <form action="{{ route('admin.customers.credit.adjust', encrypt($customer->id)) }}" method="POST" class="form-wrapper mb-4">
                        @csrf
                        <div class="row">
                            <div class="col-md-4">
                                <label class="mb-2" for="credit_amount">Adjustment (RM)</label>
                                <input type="number" step="0.01" class="form-control" name="amount" id="credit_amount" placeholder="e.g. 50 or -20" required>
                                <small class="text-muted">Positive adds credit, negative reduces.</small>
                            </div>
                            <div class="col-md-8">
                                <label class="mb-2" for="credit_notes">Reason</label>
                                <input type="text" class="form-control" name="notes" id="credit_notes" placeholder="Reason for adjustment" required>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary btn-sm">Apply Adjustment</button>
                        </div>
                    </form>

                    <h6 class="mb-3">Credit Adjustment Log</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Balance After</th>
                                    <th>Order</th>
                                    <th>Notes</th>
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
                                        <td colspan="6" class="text-muted">No credit movements yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
            <div class="card shadow no-border">
                <div class="card-body">
                    <div class="mb-4">
                        <h5 class="card-title">Reset Password</h5>
                    </div>
                    <form action="{{ route('admin.customer.update-password') }}" method="POST" class="form-wrapper">
                        @csrf
                        <input type="hidden" name="id" value="{{ encrypt($customer->id) }}">
                        <div class="mb-4">
                            <label class="mb-2" for="new_password">New Password</label>
                            <span class="text-danger"> *</span>
                            <input type="password" class="form-control @error('new_password') is-invalid @enderror" name="new_password" id="new_password" placeholder="Enter new password" required>
                            @error('new_password')
                                <span class="text-danger" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                Reset Password
                                <div class="spinner-border spinner-border-sm d-none" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
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
