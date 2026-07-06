@extends('layouts.admin')
@section('title', __('orders.manage'))
@section('css')
    <style>
        #orderTable .order-products-col {
            min-width: 280px;
            width: 280px;
        }
    </style>
@endsection
@section('content')
@php
    $admin = Auth::guard('web_admin')->user();
@endphp
    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card shadow no-border mb-0">
                <div class="card-body">
                    <h5 class="mb-4">{{ __('orders.filter') }}</h5>
                    <form method="GET" class="form-wrapper">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="filterId">{{ __('orders.id') }}</label>
                                    <input type="text" class="form-control" name="id" id="filterId" value="{{ $input['id'] ?? '' }}" placeholder="{{ __('orders.search_id') }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="filterFromDate">{{ __('orders.order_date_from') }}</label>
                                    <input type="date" class="form-control" name="fdate" id="filterFromDate" value="{{ $input['fdate'] ?? '' }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="filterToDate">{{ __('orders.order_date_to') }}</label>
                                    <input type="date" class="form-control" name="tdate" id="filterToDate" value="{{ $input['tdate'] ?? '' }}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="customerCategory">{{ __('orders.customer') }}</label>
                                    <select class="form-select" name="customer" id="filterCustomer">
                                        <option value="">{{ __('ui.all') }}</option>
                                        @foreach($customers_list as $cust)
                                            <option value="{{ $cust->id }}" {{ ($input['customer'] ?? '') == $cust->id? " selected" : "" }}>
                                                {{ $cust->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="area">{{ __('orders.select_area') }}</label>
                                    <select class="form-select @error('area') is-invalid @enderror"  id="area" name="area">
                                        <option value="">{{ __('orders.choose') }}</option>
                                        @foreach ($areaList as $area)
                                            <option value="{{ $area }}" {{ ($input['area'] ?? '') == $area ? 'selected' : '' }}>
                                                {{ $area }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="filterStatus">{{ __('orders.status') }}</label>
                                    <select class="form-select" name="status" id="filterStatus">
                                        <option value="">{{ __('ui.all') }}</option>
                                        @foreach($status_options as $status)
                                        <option value="{{ $status }}"{{ ($input['status'] ?? '') == $status? " selected" : "" }}>{{ trans('order.status.'.$status) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="filterPaymentStatus">{{ __('orders.payment_status') }}</label>
                                    <select class="form-select" name="payment_status" id="filterPaymentStatus">
                                        <option value="">{{ __('ui.all') }}</option>
                                        @foreach($payment_status_options as $paymentStatus)
                                            <option value="{{ $paymentStatus }}" {{ ($input['payment_status'] ?? '') == $paymentStatus ? 'selected' : '' }}>
                                                {{ __('order.payment_status.' . $paymentStatus) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="filterPhone">{{ __('orders.search_phone') }}</label>
                                    <input type="text" class="form-control" name="phone" id="filterPhone" value="{{ $input['phone'] ?? '' }}" placeholder="{{ __('orders.search_phone_placeholder') }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="filterAddress">{{ __('orders.search_address') }}</label>
                                    <input type="text" class="form-control" name="address" id="filterAddress" value="{{ $input['address'] ?? '' }}" placeholder="{{ __('orders.search_address_placeholder') }}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="lorry">{{ __('orders.select_lorry') }}</label>
                                    <select class="form-select" id="lorry" name="lorry">
                                        <option value="">{{ __('orders.choose') }}</option>
                                        @foreach ($drivers as $id => $lorry)
                                            <option value="{{ $id }}" {{ ($input['lorry'] ?? '') == $id ? 'selected' : '' }}>
                                                {{ $lorry }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="orderby">{{ __('orders.order_by') }}</label>
                                    <select class="form-select" name="orderby" id="orderby">
                                        <option value="desc" {{ ($input['orderby'] ?? '') === 'desc'? " selected" : "" }}>{{ __('orders.latest_first') }}</option>
                                        <option value="asc" {{ ($input['orderby'] ?? '') === 'asc'? " selected" : "" }}>{{ __('orders.oldest_first') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <div id="priceRange" class="row col-12">
                                        <div class="form-group col-md-6">
                                            <label class="mb-2" for="filterPriceFrom">{{ __('orders.price_from') }}</label>
                                            <input type="number" class="form-control" name="min_price" id="filterPriceFrom" value="{{ $input['min_price'] }}" step="0.01" placeholder="{{ __('orders.min') }}">
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label class="mb-2" for="filterPriceTo">{{ __('orders.price_to') }}</label>
                                            <input type="number" class="form-control" name="max_price" id="filterPriceTo" value="{{ $input['max_price'] }}" step="0.01" placeholder="{{ __('orders.max') }}">
                                        </div>
                                    </div>
                                    <input type="hidden" name="price_range" id="priceRangeInput">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary me-3">{{ __('ui.search') }}</button>
                                <a href="{{ route('admin.orders') }}">{{ __('ui.clear_search') }}</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    @if (Auth::guard('web_admin')->user()->canModule('orders', 'create'))
                        <a href="{{ route('admin.orders.create') }}" class="btn btn-primary">
                            <i class="fa fa-plus" aria-hidden="true"></i> {{ __('orders.add') }}
                        </a>
                    @endif
                    <button type="button" class="btn btn-primary d-none" id="change-order-statuses" data-bs-toggle="modal" data-bs-target="#order-statuses">
                        {{ __('orders.change_order_status') }}
                    </button>
                    <button type="button" class="btn btn-primary d-none" id="change-order-lorry" data-bs-toggle="modal" data-bs-target="#assign-lorry">
                        {{ __('orders.change_lorry') }}
                    </button>
                </div>
                <div class="d-flex">
                    @if ($admin->canModule('orders', 'edit'))
                        <button type="button" id="syncAutoCountBtn" class="btn btn-outline-secondary me-1">
                            {{ __('orders.sync_autocount') }}
                        </button>
                    @endif
                    <form action="{{ route('admin.orders.export') . $query_params }}">
                        <input type="hidden" class="orders_id" name="orders_id">
                        <button type="submit" class="btn btn-success btn-download-excel me-1">
                            <i class="fa fa-file-excel-o" aria-hidden="true"></i> {{ __('ui.export_excel') }}
                        </button>
                    </form>
                    <button class="btn btn-success status-action-button me-1" data-to_status="completed" data-status="{{ __('order.status.completed') }}" style="display: none;">
                        {{ __('orders.change_status_to', ['status' => __('order.status.completed')]) }}
                    </button>
                    <button class="btn btn-success status-action-button download-zip me-1" data-to_status="completed" data-status="{{ __('order.status.completed') }}" title="{{ __('orders.download_selected_zip') }}" style="display: none;">
                        <i class="fa fa-download"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card shadow no-border mb-0">
                <div class="card-body">
                    <h5 class="mb-4">{{ __('orders.list') }}</h5>
                    <div class="table-responsive">
                        <table id="orderTable" class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input checkall" id="order_checkall">
                                            <label class="form-check-label" for="order_checkall"> </label>
                                        </div>
                                    </th>
                                    <th>{{ __('orders.option') }}</th>
                                    <th>{{ __('orders.order_id') }}</th>
                                    <th>{{ __('orders.order_at') }}</th>
                                    <th>{{ __('orders.customer') }}</th>
                                    <th class="order-products-col">{{ __('orders.products') }}</th>
                                    <th>{{ __('orders.area') }}</th>
                                    <th>{{ __('orders.billing_address') }}</th>
                                    <th>{{ __('orders.shipping_address') }}</th>
                                    <th>{{ __('orders.assign_driver') }}</th>
                                    <th>{{ __('orders.status') }}</th>
                                    <th>{{ __('orders.payment') }}</th>
                                    <th>{{ __('orders.payment_due') }}</th>
                                    <th>{{ __('orders.payment_due_status') }}</th>
                                    <th>{{ __('orders.invoice_sync_status') }}</th>
                                    <th>{{ __('orders.last_updated_at') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orders as $index => $order)
                                    @php
                                        $customer = $order->customer;
                                    @endphp
                                    <tr>
                                        <td class="order-cbx-col">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input cs-checkbox" name="selected_orders[]" id="order_{{ $order->id }}" value="{{ $order->id }}">
                                                <label class="form-check-label" for="order_{{ $order->id }}"> </label>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                    {{ __('orders.action') }}
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item" href="{{ route('admin.orders.summary', $order->id) }}">{{ __('orders.order_summary') }}</a>
                                                    </li>
                                                    @if (Order::canAdjustQuantities($order->status) && $admin->canModule('orders', 'edit'))
                                                        <li>
                                                            <a class="dropdown-item" href="{{ route('admin.orders.review', $order->id) }}">{{ __('orders.adjust_order') }}</a>
                                                        </li>
                                                    @endif
                                                    @if ($order->canShowInvoice())
                                                        <li>
                                                            <a class="dropdown-item view-pdf" href="{{ route('admin.order.invoice', $order->id) }}#toolbar=0" data-url="{{ route('admin.order.invoice', $order->id) }}">{{ __('orders.view_invoice') }}</a>
                                                        </li>
                                                    @endif
                                                    @if ($order->canShowDeliveryOrder())
                                                        <li>
                                                            <a class="dropdown-item view-pdf" href="{{ route('admin.order.delivery-order', $order->id) }}#toolbar=0" data-url="{{ route('admin.order.delivery-order', $order->id) }}">{{ __('orders.view_do') }}</a>
                                                        </li>
                                                    @endif
                                                    @if ($admin->canModule('orders', 'edit'))
                                                        <li>
                                                            <a class="dropdown-item btn-change-lorry" href="javascript:void(0);" data-id="{{ encrypt($order->id) }}" data-lorry="{{ $order->driver_id }}" data-bs-toggle="modal" data-bs-target="#change-lorry">{{ __('orders.change_lorry') }}</a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item btn-add-order-weight" href="javascript:void(0);" data-id="{{ encrypt($order->id) }}" data-bs-toggle="modal" data-bs-target="#add-weight">{{ __('orders.order_weight') }}</a>
                                                        </li>
                                                    @endif
                                                </ul>
                                            </div>
                                        </td>
                                        <td>{{ $order->id }}</td>
                                        <td>{{ $order->created_at }}</td>
                                        <td>
                                            @if ($customer)
                                                <a href="{{ route('admin.customers.edit', encrypt($customer->id)) }}" class="text-dark" target="_blank">
                                                    {{ $customer->name }}
                                                </a>
                                            @else
                                                {{ $order->walk_in_name ?: ($order->attn_name ?: __('orders.walk_in_public')) }}
                                                @if ($order->is_general)
                                                    <span class="badge bg-info text-dark">{{ __('orders.general') }}</span>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="order-products-col">{!! $order->order_products !!}</td>
                                        <td>{{ $order->area }}</td>
                                        <td>{!! $order->billing_address !!}</td>
                                        <td>{!! $order->shipping_address !!}</td>
                                        <td>
                                            @if ($order->driver_id)
                                                {!! isset($drivers[$order->driver_id]) ? $drivers[$order->driver_id] : '<span class="text-danger">' . e(__('orders.lorry_deleted')) . '</span>' !!}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-center">{{ __('order.status.' . $order->status) }}</td>
                                        <td class="text-center">
                                            @php
                                                $paymentBadgeClass = match ($order->payment_status ?? 'unpaid') {
                                                    'payment_due' => 'bg-danger',
                                                    'paid' => 'bg-success',
                                                    'pending' => 'bg-warning text-dark',
                                                    'partial' => 'bg-warning text-dark',
                                                    default => 'bg-secondary',
                                                };
                                            @endphp
                                            <span class="badge {{ $paymentBadgeClass }}">
                                                {{ __('order.payment_status.' . ($order->payment_status ?? 'unpaid')) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @if ($customer && ($customer->customer_type ?? 'cod') === 'credit')
                                                {{ $order->payment_due_date ? $order->payment_due_date->format('d-m-Y') : '-' }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @php
                                                $paymentDueStatusKey = $order->paymentDueStatusKey();
                                                $paymentDueStatusClass = match ($paymentDueStatusKey) {
                                                    'paid' => 'bg-success',
                                                    'overdue', 'due_today' => 'bg-danger',
                                                    'not_due' => 'bg-warning text-dark',
                                                    'not_set' => 'bg-secondary',
                                                    default => 'bg-light text-dark',
                                                };
                                            @endphp
                                            <span class="badge {{ $paymentDueStatusClass }}">
                                                {{ __('orders.payment_due_status_labels.' . $paymentDueStatusKey) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @php
                                                $syncStatusKey = $order->autocountSyncStatusKey();
                                                $syncStatusClass = match ($syncStatusKey) {
                                                    'synced', 'synced_successfully' => 'bg-success',
                                                    'pending_sync' => 'bg-warning text-dark',
                                                    'skipped' => 'bg-secondary',
                                                    default => 'bg-light text-dark',
                                                };
                                            @endphp
                                            <span class="badge {{ $syncStatusClass }}">
                                                {{ __('orders.autocount_sync_status.' . $syncStatusKey) }}
                                            </span>
                                        </td>
                                        <td>{{ $order->updated_at }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="16">
                                        {{ $orders->appends(request()->query())->links('pagination::bootstrap-4') }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="pdfModal" tabindex="-1" aria-labelledby="pdfModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pdfModalLabel">{{ __('orders.pdf_preview') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
                </div>
                <div class="modal-body p-0">
                    <iframe id="pdfFrame" style="width: 100%; height: 80vh; border: none;"></iframe>
                </div>
                <div class="modal-footer">
                    <a id="downloadLink" class="btn btn-primary" href="#" download>
                        <i class="fa fa-download"></i> {{ __('orders.download_pdf') }}
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.close') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="order-statuses" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('orders.order_status') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
                </div>
                <form action="{{ route('admin.change-order-status') }}" method="POST" class="form-wrapper">
                    @csrf
                    <input type="hidden" class="orders_id" name="orders_id">
                    <div class="modal-body">
                        <div class="alert alert-warning d-none" id="order_weight-wrapper">
                            <strong>{{ __('orders.weight_not_added_warning') }}</strong>
                        </div>
                        <div class="mb-4">
                            <label for="status" class="mb-2">{{ __('orders.order_status') }}</label>
                            <span class="text-danger"> *</span>
                            <select class="form-select" id="order_status" name="status" required>
                                <option value="">{{ __('orders.choose') }}</option>
                                @foreach ($statuses as $key => $value)
                                    <option value="{{ $key }}">
                                        {{ $value }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}">{{ __('ui.close') }}</button>
                        <button type="submit" class="btn btn-primary">
                            {{ __('orders.change_status') }}
                            <div class="spinner-border spinner-border-sm d-none" role="status">
                                <span class="visually-hidden">{{ __('orders.loading') }}</span>
                            </div>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="change-lorry" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('orders.change_lorry') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
                </div>
                <form action="{{ route('admin.change-order-delivery') }}" method="POST" class="form-wrapper">
                    @csrf
                    <input type="hidden" class="orders_id" name="orders_id">
                    <div class="modal-body">
                        <div class="mb-4">
                            <label class="mb-2">{{ __('orders.fulfillment_type') }}</label>
                            <select class="form-select" id="modal_fulfillment_type" name="fulfillment_type">
                                <option value="delivery">{{ __('orders.fulfillment_delivery') }}</option>
                                <option value="pickup">{{ __('orders.fulfillment_pickup') }}</option>
                            </select>
                        </div>
                        <div class="mb-4" id="modal-driver-wrap">
                            <label class="mb-2">{{ __('orders.assign_driver') }}</label>
                            <select class="form-select" id="order_driver_id" name="driver_id">
                                <option value="">{{ __('orders.choose') }}</option>
                                @foreach ($drivers as $id => $driver)
                                    <option value="{{ $id }}">{{ $driver }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}">{{ __('ui.close') }}</button>
                        <button type="submit" class="btn btn-primary">
                            {{ __('ui.submit') }}
                            <div class="spinner-border spinner-border-sm d-none" role="status">
                                <span class="visually-hidden">{{ __('orders.loading') }}</span>
                            </div>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="add-weight" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('orders.products_weight') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
                </div>
                <form action="{{ route('admin.update-order-products-weight') }}" method="POST" class="form-wrapper">
                    @csrf
                    <input type="hidden" class="orders_id" name="orders_id">
                    <div class="modal-body"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}">{{ __('ui.close') }}</button>
                        <button type="submit" class="btn btn-primary">
                            {{ __('ui.submit') }}
                            <div class="spinner-border spinner-border-sm d-none" role="status">
                                <span class="visually-hidden">{{ __('orders.loading') }}</span>
                            </div>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="assign-lorry" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('orders.assign_lorry') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
                </div>
                <form action="{{ route('admin.assign-order-driver') }}" method="POST" class="form-wrapper">
                    @csrf
                    <input type="hidden" class="orders_id" name="orders_id">
                    <div class="modal-body">
                        <div class="mb-4">
                            <label class="mb-2">{{ __('orders.fulfillment_type') }}</label>
                            <select class="form-select" id="bulk_fulfillment_type" name="fulfillment_type">
                                <option value="delivery">{{ __('orders.fulfillment_delivery') }}</option>
                                <option value="pickup">{{ __('orders.fulfillment_pickup') }}</option>
                            </select>
                        </div>
                        <div class="mb-4" id="bulk-driver-wrap">
                            <label class="mb-2">{{ __('orders.assign_driver') }}</label>
                            <select class="form-select" id="bulk_driver_id" name="driver_id">
                                <option value="">{{ __('orders.choose') }}</option>
                                @foreach ($drivers as $id => $driver)
                                    <option value="{{ $id }}">{{ $driver }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}">{{ __('ui.close') }}</button>
                        <button type="submit" class="btn btn-primary">
                            {{ __('ui.submit') }}
                            <div class="spinner-border spinner-border-sm d-none" role="status">
                                <span class="visually-hidden">{{ __('orders.loading') }}</span>
                            </div>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <form id="syncAutoCountForm" action="{{ route('admin.orders.sync-autocount-bulk') }}" method="POST" class="d-none">
        @csrf
    </form>

@endsection
@section('script')

    <script src="{{ asset('assets/js/sweetalert.min.js') }}"></script>
    <script>
        var ordersJs = @json(__('orders.js'));

        $(document).ready(function() {

            $('#filterCustomer').select2();

            $(".view-pdf").click(function(e) {
                e.preventDefault();

                var pdfUrl = $(this).attr("href").replace('#toolbar=0', '');
                var downloadUrl = $(this).data('url').replace('#toolbar=0', '');

                $("#pdfFrame").attr("src", $(this).attr("href"));
                $("#downloadLink").attr("href", downloadUrl + '/download');

                $("#pdfModal").modal("show");
            });

            $("#pdfModal").on('hidden.bs.modal', function() {
                $("#pdfFrame").attr("src", '');
            });

            $('.btn-download-excel').on('click', function (e) {
                var selectedOrders = [];
                $("input[name='selected_orders[]']:checked").each(function() {
                    selectedOrders.push($(this).val());
                });
                $('.orders_id').val(selectedOrders);
                if (selectedOrders.length == 0) {
                    e.preventDefault();
                    Swal.fire({
                        title: ordersJs.select_order,
                        text: ordersJs.select_order_export,
                        icon: 'warning',
                        showCancelButton: true,
                    })
                }
            });

            $("#syncAutoCountBtn").on('click', function() {
                var selectedOrders = [];
                $("input[name='selected_orders[]']:checked").each(function() {
                    selectedOrders.push($(this).val());
                });

                if (selectedOrders.length === 0) {
                    Swal.fire({
                        title: ordersJs.select_order,
                        text: ordersJs.select_order_sync,
                        icon: 'warning',
                    });
                    return;
                }

                var form = $("#syncAutoCountForm");
                form.find('input[name="order_ids[]"]').remove();

                selectedOrders.forEach(function(orderId) {
                    form.append($('<input>', {
                        type: 'hidden',
                        name: 'order_ids[]',
                        value: orderId,
                    }));
                });

                form.submit();
            });

            $("#filterPriceFrom,#filterPriceTo").change(function(e){
                $("#priceRangeInput").val($("#filterPriceFrom").val() + "," + $("#filterPriceTo").val());
            });

            $("#order_status").change(function(e){
                if ($(this).val() == 'delivering') {
                    $('#order_weight-wrapper').removeClass('d-none');
                } else {
                    $('#order_weight-wrapper').addClass('d-none');
                }
            });

            $(".order-cbx-col input[type=checkbox]").on('click', function(e){
                $(".status-action-button").hide();
                if ($(".order-cbx-col input[type=checkbox]:checked").length) {
                    $(".status-action-button[data-status='" + "{{ __('order.status.completed') }}"+"']").show()
                    $('#change-order-statuses').removeClass('d-none');
                    $('#change-order-lorry').removeClass('d-none');
                } else {
                    $('#change-order-statuses').addClass('d-none');
                    $('#change-order-lorry').addClass('d-none');
                }
            });

            $(".status-action-button").click(function(){
                var selectedOrders = [];
                $("input[name='selected_orders[]']:checked").each(function() {
                    selectedOrders.push($(this).val());
                });

                if($(this).hasClass('download-zip')){
                    var field_name = 'order_ids[]';
                    var queryParameters = selectedOrders.join('&'+ field_name +'=');
                    window.location.href = "{{ url('/admin/order/batch-download-files') }}" + "?"+ field_name +"=" + queryParameters;
                    return;
                }

                var status = $(this).data('to_status');

                $.ajax({
                    type: "POST",
                    url: "{{ url('/admin/order/batch-update-status') }}",
                    data: {
                        _token: "{{ csrf_token() }}",
                        order_ids: selectedOrders,
                        status: status,
                    },
                    success: function (response) {
                        data = $.parseJSON(response);
                        if(data.success){
                            Swal.fire(ordersJs.order_updated, ordersJs.order_status_updated, 'success').then(function(){
                                window.location.reload();
                            });
                        }else{
                            Swal.fire(ordersJs.error, ordersJs.order_status_error, 'error');
                        }
                    },
                    error: function (error) {
                        Swal.fire(ordersJs.error, ordersJs.order_status_error, 'error');
                    }
                });
            });
        });

        function toggleFulfillmentDriver(selectId, wrapId, driverId) {
            var select = document.getElementById(selectId);
            var wrap = document.getElementById(wrapId);
            var driver = document.getElementById(driverId);
            if (!select || !wrap || !driver) return;
            function sync() {
                var isPickup = select.value === 'pickup';
                wrap.style.display = isPickup ? 'none' : '';
                driver.disabled = isPickup;
            }
            select.addEventListener('change', sync);
            sync();
        }

        toggleFulfillmentDriver('modal_fulfillment_type', 'modal-driver-wrap', 'order_driver_id');
        toggleFulfillmentDriver('bulk_fulfillment_type', 'bulk-driver-wrap', 'bulk_driver_id');
    </script>

@endsection
