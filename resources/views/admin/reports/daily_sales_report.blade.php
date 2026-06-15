@extends('layouts.admin')
@section('title', 'Daily Sales Report')
@section('content')

    <div class="row mb-5">
        <div class="col-md-12">
            <h4 class="mb-4">Daily Sales Report</h4>
            @include('admin.reports.partials.filters')
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <h4>Daily Sales Report</h4>
                <div>
                    <a href="{{ route('admin.export-daily-sales-report') . $query_params }}" class="btn btn-success">
                        <i class="fa fa-file-excel-o me-2" aria-hidden="true"></i> Export to Excel
                    </a>
                </div>
            </div>
            <div class="card shadow no-border">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Order At</th>
                                    <th>Customer</th>
                                    <th>Item Name</th>
                                    <th>SKU</th>
                                    <th>Category</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Total Price</th>
                                    <th>Payment Method</th>
                                    <th>Area</th>
                                    <th>Billing Address</th>
                                    <th>Shipping Address</th>
                                    <th>Last Updated At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $total_sales_count = 0;
                                    $total_quantity_sold = 0;
                                    $total_sales = 0;
                                    $col_no = 1;
                                    $pre_order_id = null;
                                @endphp
                                @forelse ($orders as $key => $order)
                                    @php
                                        $total_quantity_sold += $order->quantity;
                                        $total_sales += $order->price;
                                    @endphp
                                    <tr>
                                        <td>
                                            @if ($pre_order_id != $order->id)
                                                {{ $col_no++ }}
                                                @php
                                                    $total_sales_count++;
                                                @endphp
                                            @endif
                                        </td>
                                        <td>
                                            @if ($pre_order_id != $order->id)
                                                {{ $order->created_at }}
                                            @endif
                                        </td>
                                        <td>
                                            @if ($pre_order_id != $order->id)
                                                {{ $order->name }}
                                            @endif
                                        </td>
                                        <td>{{ $order->product_name }}</td>
                                        <td>{{ $order->sku }}</td>
                                        <td>{{ '-' }}</td>
                                        <td>{{ $order->quantity }}</td>
                                        <td>RM {{ $order->unit_price }}</td>
                                        <td>RM {{ $order->price }}</td>
                                        <td>{{ $order->payment_method }}</td>
                                        <td>{{ $order->area }}</td>
                                        <td>
                                            @if ($pre_order_id != $order->id)
                                                {{ $order->billing_address . ' ' . $order->billing_city . ' ' . $order->billing_postcode . ' ' . $order->billing_state }}
                                            @endif
                                        </td>
                                        <td>
                                            @if ($pre_order_id != $order->id)
                                                {{ $order->shipping_address . ' ' . $order->shipping_city . ' ' . $order->shipping_postcode . ' ' . $order->shipping_state }}
                                            @endif
                                        </td>
                                        <td>
                                            @if ($pre_order_id != $order->id)
                                                {{ $order->updated_at }}
                                            @endif
                                        </td>
                                    </tr>
                                    @php
                                        $pre_order_id = $order->id;
                                    @endphp
                                @empty
                                    <tr>
                                        <td colspan="13">No record found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2">TOTAL SALES COUNT:</td>
                                    <td colspan="2">{{ $total_sales_count }}</td>
                                    <td colspan="2">TOTAL QUANTITY SOLD:</td>
                                    <td colspan="2">{{ $total_quantity_sold }}</td>
                                    <td colspan="2">TOTAL SALES:</td>
                                    <td colspan="3">RM {{ $total_sales }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
