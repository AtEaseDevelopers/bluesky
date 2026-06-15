@extends('layouts.admin')
@section('title', 'Daily Summary Stock Report')
@section('content')

    <div class="row mb-5">
        <div class="col-md-12">
            <h4 class="mb-4">Daily Summary Stock Report</h4>
            @include('admin.reports.partials.filters')
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <h4>Daily Summary Stock Report</h4>
                <div>
                    <a href="{{ route('admin.export-daily-summary-stock-report') . $query_params }}" class="btn btn-success">
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
                                    <th>Item Name</th>
                                    <th>Item SKU</th>
                                    <th>Item Category</th>
                                    <th>Item Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($orders as $order)
                                    <tr>
                                        <td>{{ $order->product_name }}</td>
                                        <td>{{ $order->sku }}</td>
                                        <td>{{ '-' }}</td>
                                        <td>{{ $order->quantity }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4">No record found.</td>
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
