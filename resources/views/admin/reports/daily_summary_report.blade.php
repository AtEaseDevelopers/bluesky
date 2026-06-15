@extends('layouts.admin')
@section('title', 'Daily Summary Report')
@section('content')

    <div class="row mb-5">
        <div class="col-md-12">
            <h4 class="mb-4">Daily Summary Report</h4>
            @include('admin.reports.partials.filters')
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <h4>Daily Summary Report</h4>
                <div>
                    <a href="{{ route('admin.export-daily-summary-report') . $query_params }}" class="btn btn-success">
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
                                    <th>Unit Weight</th>
                                    <th>Total Weight</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $pre_order_id = null;
                                @endphp
                                @forelse ($orders as $key => $order)
                                    <tr>
                                        <td>
                                            @if ($pre_order_id != $order->id)
                                                {{ ++$key }}
                                            @endif
                                        </td>
                                        <td>{{ $order->created_at }}</td>
                                        <td>{{ $order->name }}</td>
                                        <td>{{ $order->product_name }}</td>
                                        <td>{{ $order->sku }}</td>
                                        <td>{{ '-' }}</td>
                                        <td>{{ $order->quantity }}</td>
                                        <td>{{ $order->weight }}KG</td>
                                        <td>{{ $order->order_weight }}KG</td>
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
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
