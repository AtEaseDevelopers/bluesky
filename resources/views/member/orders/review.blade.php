@extends('layouts.member')
@section('title', 'Review Order #' . $order->id)
@section('content')

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                <h4 class="mb-0">Review Order #{{ $order->id }}</h4>
                <a href="{{ url('order/summary/' . $encryptedId) }}" class="btn btn-outline-secondary">
                    <i class="fa fa-chevron-circle-left"></i> Back to Summary
                </a>
            </div>

            <div class="alert alert-info">
                Your order has been reviewed by our team. Please confirm the final quantities, weights, and total below.
                Once approved, your order will proceed to delivery.
            </div>

            <div class="card shadow no-border">
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p><strong>Order Date:</strong> {{ $order->created_at->format('d M Y h:i a') }}</p>
                            <p><strong>Delivery:</strong>
                                {{ $order->delivery_date ? $order->delivery_date->format('d M Y') : '-' }}
                                {{ $order->delivery_time_slot }}
                            </p>
                            <p><strong>Shipping Address:</strong><br>{!! nl2br(e(strip_tags($order->shipping_address))) !!}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Status:</strong> {{ __('order.status.' . $order->status) }}</p>
                            @if (Auth::guard('web')->user()->customer_type === 'credit' && $order->payment_due_date)
                                <p><strong>Payment Due:</strong> {{ $order->payment_due_date->format('d M Y') }}</p>
                            @endif
                            @if ($order->adjustment_remark)
                                <p><strong>Adjustment Note:</strong> {{ $order->adjustment_remark }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th class="text-end">Unit Price (RM)</th>
                                    <th>Qty</th>
                                    <th>Weight (kg)</th>
                                    <th class="text-end">Line Total (RM)</th>
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
                                    <td colspan="4" class="text-end"><strong>Subtotal</strong></td>
                                    <td class="text-end">{{ number_format($order->subtotal, 2) }}</td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Delivery Fee</strong></td>
                                    <td class="text-end">{{ number_format($order->delivery_fee, 2) }}</td>
                                </tr>
                                @if ($order->amount_adjustment != 0)
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Adjustment</strong></td>
                                        <td class="text-end">{{ number_format($order->amount_adjustment, 2) }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Grand Total</strong></td>
                                    <td class="text-end"><strong>{{ number_format($order->total_price, 2) }}</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end gap-2 flex-wrap">
                        <form action="{{ route('member.orders.review.reject', $encryptedId) }}" method="POST"
                            onsubmit="return confirm('Are you sure you want to cancel this order?');">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger">Reject / Cancel</button>
                        </form>
                        <form action="{{ route('member.orders.review.approve', $encryptedId) }}" method="POST"
                            onsubmit="return confirm('Approve this order and proceed to delivery?');">
                            @csrf
                            <button type="submit" class="btn btn-success">Approve Order</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
