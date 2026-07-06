@extends('layouts.member')
@section('title', __('orders.member.review_order_title', ['id' => $order->id]))
@section('content')

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                <h4 class="mb-0">{{ __('orders.member.review_order_title', ['id' => $order->id]) }}</h4>
                <a href="{{ url('order/summary/' . $encryptedId) }}" class="btn btn-outline-secondary">
                    <i class="fa fa-chevron-circle-left"></i> {{ __('orders.member.back_to_summary') }}
                </a>
            </div>

            <div class="alert alert-info">
                {{ __('orders.member.review_intro') }}
            </div>

            <div class="card shadow no-border">
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p><strong>{{ __('orders.member.order_date_colon') }}</strong> {{ $order->created_at->format('d M Y h:i a') }}</p>
                            <p><strong>{{ __('orders.member.delivery_label') }}</strong>
                                {{ $order->delivery_date ? $order->delivery_date->format('d M Y') : '-' }}
                                {{ $order->delivery_time_slot }}
                            </p>
                            <p><strong>{{ __('orders.member.shipping_address_colon') }}</strong><br>{!! nl2br(e(strip_tags($order->shipping_address))) !!}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>{{ __('orders.member.status_colon') }}</strong> {{ __('order.status.' . $order->status) }}</p>
                            @if (Auth::guard('web')->user()->customer_type === 'credit' && $order->payment_due_date)
                                <p><strong>{{ __('orders.member.payment_due_colon') }}</strong> {{ $order->payment_due_date->format('d M Y') }}</p>
                            @endif
                            @if ($order->adjustment_remark)
                                <p><strong>{{ __('orders.member.adjustment_note_colon') }}</strong> {{ $order->adjustment_remark }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>{{ __('orders.product') }}</th>
                                    <th class="text-end">{{ __('orders.member.unit_price_rm') }}</th>
                                    <th>{{ __('orders.qty') }}</th>
                                    <th>{{ __('orders.member.weight_kg') }}</th>
                                    <th class="text-end">{{ __('orders.member.line_total_header') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($products as $product)
                                    <tr>
                                        <td>{{ $product->product_name }}</td>
                                        <td class="text-end">{{ number_format($product->unit_price, 2) }}</td>
                                        <td>{{ $product->quantity ?? '-' }}</td>
                                        <td>
                                            @if ($product->weight)
                                                {{ $product->weight }}
                                            @elseif ($product->quantity && $product->product_weight)
                                                {{ $product->quantity * $product->product_weight }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end">{{ number_format($product->price, 2) }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td colspan="4" class="text-end"><strong>{{ __('orders.subtotal') }}</strong></td>
                                    <td class="text-end">{{ number_format($order->subtotal, 2) }}</td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>{{ __('orders.delivery_fee') }}</strong></td>
                                    <td class="text-end">{{ number_format($order->delivery_fee, 2) }}</td>
                                </tr>
                                @if ($order->amount_adjustment != 0)
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>{{ __('orders.adjustment') }}</strong></td>
                                        <td class="text-end">{{ number_format($order->amount_adjustment, 2) }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td colspan="4" class="text-end"><strong>{{ __('orders.grand_total') }}</strong></td>
                                    <td class="text-end"><strong>{{ number_format($order->total_price, 2) }}</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end gap-2 flex-wrap">
                        <form action="{{ route('member.orders.review.reject', $encryptedId) }}" method="POST"
                            onsubmit="return confirm(@json(__('orders.member.reject_confirm')));">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger">{{ __('orders.member.reject_cancel') }}</button>
                        </form>
                        <form action="{{ route('member.orders.review.approve', $encryptedId) }}" method="POST"
                            onsubmit="return confirm(@json(__('orders.member.approve_confirm')));">
                            @csrf
                            <button type="submit" class="btn btn-success">{{ __('orders.member.approve_order') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
