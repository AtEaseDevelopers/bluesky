@extends('layouts.member')
@section('title', 'Order Summary')
@section('content')

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h4 class="mb-4">Order Summary #{{ $order->id }}</h4>
                <div>
                    <a href="{{ route('member.orders') }}" class="btn btn-outline-primary me-3 mb-1">
                        <i class="fa fa-chevron-circle-left"></i> My Orders
                    </a>
                    @if ($order->status === \App\Order::$status['customer_reviewing'])
                        <a href="{{ route('member.orders.review', $encryptedId) }}" class="btn btn-warning mb-1">
                            <i class="fa fa-check"></i> Review & Approve
                        </a>
                    @endif
                    @if ($order->canShowInvoiceToCustomer($customer))
                        <a href="{{ $invoice_url }}" class="btn btn-primary view-pdf mb-1">
                            <i class="fa fa-eye"></i> {{ __('order.file.invoice') }}
                        </a>
                    @elseif ($customer->invoice_visibility && in_array($order->status, ['customer_reviewing', 'in_route', 'delivered', 'paid_completed']))
                        <span class="btn btn-outline-secondary mb-1 disabled" title="Available after payment is collected">
                            <i class="fa fa-eye"></i> {{ __('order.file.invoice') }}
                        </span>
                    @endif
                    @if ($order->canShowDeliveryOrder())
                        <a href="{{ $delivery_order_url }}#toolbar=0" data-url="{{ $delivery_order_download_url }}" class="btn btn-primary mb-1 view-pdf">
                            <i class="fa fa-car"></i> {{ __('order.file.delivery-order') }}
                        </a>
                    @endif
                </div>
            </div>

            @if ($order->status === \App\Order::$status['customer_reviewing'])
                <div class="alert alert-warning">
                    This order is awaiting your review. Please confirm the final amounts before delivery proceeds.
                    <a href="{{ route('member.orders.review', $encryptedId) }}" class="alert-link">Review now</a>
                </div>
            @endif

            <div class="card no-border shadow">
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p><strong>Order Date:</strong> {{ $order->created_at->format('d M Y h:i a') }}</p>
                            <p><strong>Delivery:</strong>
                                {{ $order->delivery_date ? $order->delivery_date->format('d M Y') : '-' }}
                                {{ $order->delivery_time_slot }}
                            </p>
                            <p><strong>Status:</strong> {{ __('order.status.' . $order->status) }}</p>
                            @if ($order->is_estimated)
                                <p><span class="badge bg-info">Estimated — pending final review</span></p>
                            @endif
                            <p>
                                <strong>Attn:</strong><br/>
                                {{ $order->attn_name ?: '-' }}<br/>
                                {{ $order->attn_contact }}
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p>
                                <strong>Payment Status:</strong>
                                @php
                                    $paymentBadgeClass = match ($order->payment_status) {
                                        'payment_due' => 'bg-danger',
                                        'paid' => 'bg-success',
                                        'partial' => 'bg-warning text-dark',
                                        default => 'bg-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $paymentBadgeClass }}">
                                    {{ __('order.payment_status.' . $order->payment_status) }}
                                </span>
                            </p>
                            @if (($customer->customer_type ?? 'cod') === 'credit')
                                @if ($order->payment_due_date)
                                    <p><strong>Payment Due:</strong> {{ $order->payment_due_date->format('d M Y') }}</p>
                                @endif
                            @endif
                            @if ($order->invoice_number)
                                <p><strong>Invoice No:</strong> {{ $order->invoice_number }}</p>
                            @endif
                            <p><strong>Billing Address:</strong><br/>{!! $order->billing_address !!}</p>
                            <p><strong>Shipping Address:</strong><br/>{!! $order->shipping_address !!}</p>
                        </div>
                    </div>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    @if ($customer->price_permission)
                                        <th class="text-end">Unit Price (RM)</th>
                                    @endif
                                    <th>Qty</th>
                                    <th>Weight (kg)</th>
                                    @if ($customer->price_permission)
                                        <th class="text-end">Total (RM)</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($products as $product)
                                    <tr>
                                        <td>
                                            <strong>{{ $product->name }}</strong>
                                            @foreach($product->options as $opt => $opt_itm)
                                                <br><small>{{ $opt }}: {{ $opt_itm }}</small>
                                            @endforeach
                                            @if($product->remark)
                                                <br><small>Remark: {{ $product->remark }}</small>
                                            @endif
                                        </td>
                                        @if ($customer->price_permission)
                                            <td class="text-end">{{ number_format($product->unit_price, 2) }}</td>
                                        @endif
                                        <td>{{ $product->quantity }}</td>
                                        <td>{{ $product->product_weight ?? $product->weight ?? '-' }}</td>
                                        @if ($customer->price_permission)
                                            <td class="text-end">{{ number_format($product->price, 2) }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                                @if ($customer->price_permission)
                                    <tr>
                                        <td colspan="{{ $customer->price_permission ? 4 : 3 }}" class="text-end"><strong>Subtotal</strong></td>
                                        <td class="text-end">{{ number_format($order->subtotal, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Delivery Fee</strong></td>
                                        <td class="text-end">{{ number_format($order->delivery_fee, 2) }}</td>
                                    </tr>
                                    @if ($order->amount_adjustment != 0)
                                        <tr>
                                            <td colspan="4" class="text-end">
                                                <strong>Adjustment</strong>
                                                @if ($order->adjustment_remark)
                                                    <br><small>{{ $order->adjustment_remark }}</small>
                                                @endif
                                            </td>
                                            <td class="text-end">{{ number_format($order->amount_adjustment, 2) }}</td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Grand Total</strong></td>
                                        <td class="text-end"><strong>{{ number_format($order->total_price, 2) }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Paid</strong></td>
                                        <td class="text-end text-success">{{ number_format($order->paid_amount, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Balance Due</strong></td>
                                        <td class="text-end text-danger"><strong>{{ number_format($order->balanceDue(), 2) }}</strong></td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    @if ($payments->count())
                        <h6 class="mb-3">Payment History</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Method</th>
                                        <th class="text-end">Amount (RM)</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($payments as $payment)
                                        <tr>
                                            <td>{{ $payment->created_at->format('d M Y H:i') }}</td>
                                            <td>{{ $payment->payment_method }}</td>
                                            <td class="text-end">{{ number_format($payment->amount, 2) }}</td>
                                            <td>{{ $payment->notes ?: '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="pdfModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">PDF Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <iframe id="pdfFrame" style="width: 100%; height: 80vh;"></iframe>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('script')
    <script>
        $(document).ready(function() {
            $(".view-pdf").click(function() {
                var pdfUrl = $(this).attr("href");
                $("#pdfFrame").attr("src", pdfUrl);
                $("#pdfModal").modal("show");
                return false;
            });
        });
    </script>
@endsection
