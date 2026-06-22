@extends('layouts.admin')
@section('title', __('ui.reports.daily_sales'))
@section('content')

    <div class="row mb-5">
        <div class="col-md-12">
            <h4 class="mb-4">{{ __('ui.reports.daily_sales') }}</h4>
            <p class="text-muted mb-3">
                {{ __('ui.reports.report_for', ['date' => \Carbon\Carbon::parse($reportDate)->format('d M Y')]) }}
            </p>
            @include('admin.reports.partials.filters')
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-12">
            <h4 class="mb-4">{{ __('ui.reports.payment_collection_summary') }}</h4>
            <div class="card shadow no-border mb-4">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('ui.reports.payment_method') }}</th>
                                    <th class="text-end">{{ __('ui.reports.payment_count') }}</th>
                                    <th class="text-end">{{ __('ui.reports.total_collected') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reportService->summaryCategoryLabels() as $key => $label)
                                    <tr>
                                        <td>{{ $label }}</td>
                                        <td class="text-end">{{ $paymentSummary[$key]['count'] ?? 0 }}</td>
                                        <td class="text-end">{{ number_format($paymentSummary[$key]['total'] ?? 0, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td>{{ $paymentSummary['grand_total']['label'] ?? __('ui.reports.grand_total') }}</td>
                                    <td class="text-end">{{ $paymentSummary['grand_total']['count'] ?? 0 }}</td>
                                    <td class="text-end">{{ number_format($paymentSummary['grand_total']['total'] ?? 0, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <small class="text-muted d-block mt-2">
                        {{ __('ui.reports.payment_summary_note') }}
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <h4>{{ __('ui.reports.sales_detail') }}</h4>
                <div>
                    <a href="{{ route('admin.export-daily-sales-report') . $query_params }}" class="btn btn-success">
                        <i class="fa fa-file-excel-o me-2" aria-hidden="true"></i> {{ __('ui.export_excel') }}
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
                                    <th>{{ __('ui.reports.order_at') }}</th>
                                    <th>{{ __('ui.reports.customer') }}</th>
                                    <th>{{ __('ui.reports.order_status') }}</th>
                                    <th>{{ __('ui.reports.item_name') }}</th>
                                    <th>{{ __('ui.reports.sku') }}</th>
                                    <th>{{ __('ui.reports.quantity') }}</th>
                                    <th>{{ __('ui.reports.unit_price') }}</th>
                                    <th>{{ __('ui.reports.total_price') }}</th>
                                    <th>{{ __('ui.reports.payment_method') }}</th>
                                    <th>{{ __('ui.reports.area') }}</th>
                                    <th>{{ __('ui.reports.billing_address') }}</th>
                                    <th>{{ __('ui.reports.shipping_address') }}</th>
                                    <th>{{ __('ui.reports.last_updated_at') }}</th>
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
                                        <td>
                                            @if ($pre_order_id != $order->id)
                                                {{ __('order.status.' . $order->status) }}
                                            @endif
                                        </td>
                                        <td>{{ $order->product_name }}</td>
                                        <td>{{ $order->sku }}</td>
                                        <td>{{ $order->quantity }}</td>
                                        <td>RM {{ number_format($order->unit_price, 2) }}</td>
                                        <td>RM {{ number_format($order->price, 2) }}</td>
                                        <td>{{ $reportService->paymentMethodLabel($order->payment_method) }}</td>
                                        <td>{{ $order->area }}</td>
                                        <td>
                                            @if ($pre_order_id != $order->id)
                                                {{ trim($order->billing_address . ' ' . $order->billing_city . ' ' . $order->billing_postcode . ' ' . $order->billing_state) }}
                                            @endif
                                        </td>
                                        <td>
                                            @if ($pre_order_id != $order->id)
                                                {{ trim($order->shipping_address . ' ' . $order->shipping_city . ' ' . $order->shipping_postcode . ' ' . $order->shipping_state) }}
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
                                        <td colspan="14">{{ __('ui.no_records') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2">{{ __('ui.reports.total_sales_count') }}</td>
                                    <td colspan="2">{{ $total_sales_count }}</td>
                                    <td colspan="2">{{ __('ui.reports.total_quantity_sold') }}</td>
                                    <td colspan="2">{{ $total_quantity_sold }}</td>
                                    <td colspan="2">{{ __('ui.reports.total_sales') }}</td>
                                    <td colspan="4">RM {{ number_format($total_sales, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
