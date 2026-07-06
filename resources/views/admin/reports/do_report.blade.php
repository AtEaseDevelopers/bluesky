@extends('layouts.admin')
@section('title', __('reports.do_report'))
@section('content')

    <div class="row mb-5">
        <div class="col-md-12">
            <h4 class="mb-4">{{ __('reports.do_report') }}</h4>
            @include('admin.reports.partials.filters')
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <h4>{{ __('reports.do_report') }}</h4>
                <div>
                    <a href="{{ url('/admin/download_do_zip') . $query_params }}" class="btn btn-success">
                        <i class="fa fa-file me-2" aria-hidden="true"></i> {{ __('reports.download_do') }}
                    </a>
                </div>
            </div>
            <div class="card shadow no-border">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>{{ __('reports.order_id') }}</th>
                                    <th>{{ __('reports.order_at') }}</th>
                                    <th>{{ __('reports.customer') }}</th>
                                    <th>{{ __('reports.weight') }}</th>
                                    <th>{{ __('reports.products') }}</th>
                                    <th>{{ __('reports.total_price') }}</th>
                                    <th>{{ __('reports.payment_method') }}</th>
                                    <th>{{ __('reports.area') }}</th>
                                    <th>{{ __('reports.billing_address') }}</th>
                                    <th>{{ __('reports.shipping_address') }}</th>
                                    <th>{{ __('reports.lorry') }}</th>
                                    <th>{{ __('reports.status') }}</th>
                                    <th>{{ __('reports.last_updated_at') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orders as $index => $order)
                                    <tr>
                                        <td>{{ $order->id }}</td>
                                        <td>{{ $order->created_at }}</td>
                                        <td>
                                            <a href="{{ route('admin.customers.edit', encrypt($order->user_id)) }}" class="text-dark" target="_blank">
                                                {{ $order->name }}
                                            </a>
                                        </td>
                                        <td>{{ $order->order_weight ?? 0 }}{{ __('product.kg_unit') }}</td>
                                        <td class="white-space-nowrap">{!! $order->product_info !!}</td>
                                        <td>RM {{ $order->total_price }}</td>
                                        <td>{{ $order->payment_method ? __('user.payment_method.'.$order->payment_method) : '' }}</td>
                                        <td>{{ $order->area }}</td>
                                        <td>{{ $order->billing_address .", ". $order->billing_city .", ". $order->billing_postcode .", ". $order->billing_state }}</td>
                                        <td>{{ $order->shipping_address .", ". $order->shipping_city .", ". $order->shipping_postcode .", ". $order->shipping_state }}</td>
                                        <td>
                                            @php
                                                $driver = null;
                                                for ($i = 0; $i < count($drivers); $i++) {
                                                    if ($drivers[$i]->id == $order->driver_id) {
                                                        $driver = $drivers[$i];
                                                        break;
                                                    }
                                                }
                                            @endphp
                                            @if ($order->driver_id)
                                                {!! $driver != null ? e($driver->name ?: $driver->username) : '<span class="text-danger">' . e(__('reports.driver_deleted')) . '</span>' !!}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-center">{{ __('order.status.' . $order->status) }}</td>
                                        <td>{{ $order->updated_at }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
