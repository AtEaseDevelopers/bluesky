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
                                        'pending' => 'bg-warning text-dark',
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
                            @if ($payments->count())
                                <p><strong>Payment Methods:</strong> {{ $order->paymentMethodsLabel() }}</p>
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

                    @if ($order->canSubmitPaymentProof())
                        <div class="card no-border shadow mb-4">
                            <div class="card-body">
                                <h6 class="mb-3">Upload Payment Proof</h6>
                                @if ($isCreditCustomer)
                                    <p class="text-muted small">Submit your bank transfer or e-wallet receipt. Partial payment is allowed — pay by your payment due date{{ $order->payment_due_date ? ' (' . $order->payment_due_date->format('d M Y') . ')' : '' }}. Payment will be applied after our team confirms it.</p>
                                @else
                                    <p class="text-muted small">COD order — submit payment proof at delivery only. You must pay the exact balance due (cash or QR).</p>
                                @endif
                                <form action="{{ route('member.orders.payments.store', $encryptedId) }}" method="POST" enctype="multipart/form-data" class="form-wrapper" id="member-payment-form">
                                    @csrf
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="mb-1">Payment Method</label>
                                            <select name="payment_method" class="form-select" required>
                                                @foreach ($customerPaymentMethods as $key => $label)
                                                    <option value="{{ $key }}" {{ old('payment_method') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="mb-1">Amount (RM)</label>
                                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control" id="member-payment-amount"
                                                value="{{ old('amount', number_format($order->balanceDue(), 2, '.', '')) }}" required
                                                @if (!$isCreditCustomer) readonly @endif>
                                            @if (!$isCreditCustomer)
                                                <small class="text-muted">Exact balance due required for COD.</small>
                                            @endif
                                        </div>
                                        <div class="col-md-4">
                                            <label class="mb-1">Payment Proof</label>
                                            <input type="file" name="payment_proof" class="form-control payment-proof-input"
                                                accept="{{ \App\OrderPayment::proofAcceptAttribute() }}" required>
                                            <small class="text-muted">{{ \App\OrderPayment::proofHelpText() }}</small>
                                            @error('payment_proof')
                                                <div class="text-danger small mt-1"><strong>{{ $message }}</strong></div>
                                            @enderror
                                        </div>
                                        <div class="col-12">
                                            <label class="mb-1">Notes (optional)</label>
                                            <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" placeholder="e.g. Reference number, bank name">
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary">Submit Payment Proof</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif

                    @if ($payments->count())
                        <h6 class="mb-3">Payment History</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Method</th>
                                        <th class="text-end">Amount (RM)</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                        <th>Proof</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($payments as $payment)
                                        <tr>
                                            <td>{{ $payment->created_at->format('d M Y H:i') }}</td>
                                            <td>{{ $paymentMethods[$payment->payment_method] ?? $payment->payment_method }}</td>
                                            <td class="text-end">{{ number_format($payment->amount, 2) }}</td>
                                            <td>
                                                @php
                                                    $statusClass = match ($payment->status) {
                                                        \App\OrderPayment::STATUS_CONFIRMED => 'bg-success',
                                                        \App\OrderPayment::STATUS_PENDING => 'bg-warning text-dark',
                                                        \App\OrderPayment::STATUS_REJECTED => 'bg-danger',
                                                        default => 'bg-secondary',
                                                    };
                                                @endphp
                                                <span class="badge {{ $statusClass }}">
                                                    {{ $paymentStatusLabels[$payment->status] ?? ucfirst($payment->status) }}
                                                </span>
                                            </td>
                                            <td>{{ $payment->notes ?: '-' }}</td>
                                            <td>
                                                @if ($payment->payment_proof && $payment->submitted_by_user_id)
                                                    <a href="{{ route('member.orders.payment-proof', [$encryptedId, $payment->id]) }}" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                                                @else
                                                    -
                                                @endif
                                            </td>
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
        var proofMaxBytes = {{ \App\OrderPayment::PROOF_MAX_KB * 1024 }};
        var proofAllowedExtensions = @json(\App\OrderPayment::$proof_mimes);

        function validatePaymentProofFile(file, required) {
            if (!file || !file.name) {
                return required ? 'Payment proof is required.' : null;
            }

            var extension = file.name.split('.').pop().toLowerCase();
            if (proofAllowedExtensions.indexOf(extension) === -1) {
                return 'Payment proof must be a JPG, PNG image or PDF file.';
            }

            if (file.size > proofMaxBytes) {
                return 'Payment proof must not exceed ' + (proofMaxBytes / 1024 / 1024) + ' MB.';
            }

            return null;
        }

        $(document).ready(function() {
            $(".view-pdf").click(function() {
                var pdfUrl = $(this).attr("href");
                $("#pdfFrame").attr("src", pdfUrl);
                $("#pdfModal").modal("show");
                return false;
            });

            document.getElementById('member-payment-form')?.addEventListener('submit', function (event) {
                var proofInput = this.querySelector('[name="payment_proof"]');
                var error = validatePaymentProofFile(proofInput.files[0], true);
                if (error) {
                    event.preventDefault();
                    alert(error);
                }
            });
        });
    </script>
@endsection
