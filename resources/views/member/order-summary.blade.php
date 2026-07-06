@extends('layouts.member')
@section('title', __('orders.summary'))
@section('content')

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h4 class="mb-4">{{ __('orders.summary_title', ['id' => $order->id]) }}</h4>
                <div>
                    <a href="{{ route('member.orders') }}" class="btn btn-outline-primary me-3 mb-1">
                        <i class="fa fa-chevron-circle-left"></i> {{ __('ui.nav.my_orders') }}
                    </a>
                    @if ($order->status === \App\Order::$status['customer_reviewing'])
                        <a href="{{ route('member.orders.review', $encryptedId) }}" class="btn btn-warning mb-1">
                            <i class="fa fa-check"></i> {{ __('orders.member.review_approve') }}
                        </a>
                    @endif
                    @if ($order->canShowInvoiceToCustomer($customer))
                        <a href="{{ $invoice_url }}" class="btn btn-primary view-pdf mb-1">
                            <i class="fa fa-eye"></i> {{ __('order.file.invoice') }}
                        </a>
                    @elseif ($customer->invoice_visibility && !$order->isFullyPaid() && $order->status !== \App\Order::$status['cancelled'])
                        <span class="btn btn-outline-secondary mb-1 disabled" title="{{ __('orders.member.invoice_after_paid') }}">
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
                    {{ __('orders.member.awaiting_review') }}
                    <a href="{{ route('member.orders.review', $encryptedId) }}" class="alert-link">{{ __('orders.member.review_now') }}</a>
                </div>
            @endif

            <div class="alert alert-light border mb-4">
                @include('partials.subject_to_availability')
                <span class="d-block mt-1 small text-muted">{{ __('ui.storefront.subject_to_availability_note') }}</span>
            </div>

            <div class="card no-border shadow">
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p><strong>{{ __('orders.member.order_date_colon') }}</strong> {{ $order->created_at->format('d M Y h:i a') }}</p>
                            <p><strong>{{ __('orders.member.delivery_label') }}</strong>
                                {{ $order->delivery_date ? $order->delivery_date->format('d M Y') : '-' }}
                                {{ $order->delivery_time_slot }}
                            </p>
                            <p><strong>{{ __('orders.member.status_colon') }}</strong> {{ __('order.status.' . $order->status) }}</p>
                            @if ($order->is_estimated)
                                <p><span class="badge bg-info">{{ __('orders.member.estimated_badge') }}</span></p>
                            @endif
                            <p>
                                <strong>{{ __('orders.member.attn_colon') }}</strong><br/>
                                {{ $order->attn_name ?: '-' }}<br/>
                                {{ $order->attn_contact }}
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p>
                                <strong>{{ __('orders.member.payment_status_colon') }}</strong>
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
                                    <p><strong>{{ __('orders.member.payment_due_colon') }}</strong> {{ $order->payment_due_date->format('d M Y') }}</p>
                                @endif
                            @endif
                            @if ($payments->count())
                                <p><strong>{{ __('orders.member.payment_methods_colon') }}</strong> {{ $order->paymentMethodsLabel() }}</p>
                            @endif
                            @if ($order->invoice_number)
                                <p><strong>{{ __('orders.member.invoice_no_colon') }}</strong> {{ $order->invoice_number }}</p>
                            @endif
                            <p><strong>{{ __('orders.member.billing_address_colon') }}</strong><br/>{!! $order->billing_address !!}</p>
                            <p><strong>{{ __('orders.member.shipping_address_colon') }}</strong><br/>{!! $order->shipping_address !!}</p>
                        </div>
                    </div>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>{{ __('orders.product') }}</th>
                                    @if ($customer->price_permission)
                                        <th class="text-end">{{ __('orders.member.unit_price_rm') }}</th>
                                    @endif
                                    <th>{{ __('orders.qty') }}</th>
                                    <th>{{ __('orders.member.weight_kg') }}</th>
                                    @if ($customer->price_permission)
                                        <th class="text-end">{{ __('orders.member.line_total_rm') }}</th>
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
                                                <br><small>{{ __('orders.member.remark_prefix') }} {{ $product->remark }}</small>
                                            @endif
                                        </td>
                                        @if ($customer->price_permission)
                                            <td class="text-end">{{ number_format($product->unit_price, 2) }}</td>
                                        @endif
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
                                        @if ($customer->price_permission)
                                            <td class="text-end">{{ number_format($product->price, 2) }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                                @if ($customer->price_permission)
                                    <tr>
                                        <td colspan="{{ $customer->price_permission ? 4 : 3 }}" class="text-end"><strong>{{ __('orders.subtotal') }}</strong></td>
                                        <td class="text-end">{{ number_format($order->subtotal, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>{{ __('orders.delivery_fee') }}</strong></td>
                                        <td class="text-end">{{ number_format($order->delivery_fee, 2) }}</td>
                                    </tr>
                                    @if ($order->amount_adjustment != 0)
                                        <tr>
                                            <td colspan="4" class="text-end">
                                                <strong>{{ __('orders.adjustment') }}</strong>
                                                @if ($order->adjustment_remark)
                                                    <br><small>{{ $order->adjustment_remark }}</small>
                                                @endif
                                            </td>
                                            <td class="text-end">{{ number_format($order->amount_adjustment, 2) }}</td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>{{ __('orders.grand_total') }}</strong></td>
                                        <td class="text-end"><strong>{{ number_format($order->total_price, 2) }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>{{ __('orders.paid') }}</strong></td>
                                        <td class="text-end text-success">{{ number_format($order->paid_amount, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>{{ __('orders.balance_due') }}</strong></td>
                                        <td class="text-end text-danger"><strong>{{ number_format($order->balanceDue(), 2) }}</strong></td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    @if ($order->canSubmitPaymentProof())
                        <div class="card no-border shadow mb-4">
                            <div class="card-body">
                                <h6 class="mb-3">{{ __('orders.member.upload_payment_proof') }}</h6>
                                @if ($isCreditCustomer)
                                    <p class="text-muted small">{{ __('orders.member.credit_payment_submit_help', ['date' => $order->payment_due_date ? ' (' . $order->payment_due_date->format('d M Y') . ')' : '']) }}</p>
                                @else
                                    <p class="text-muted small">{{ __('orders.member.cod_payment_submit_help') }}</p>
                                @endif
                                <form action="{{ route('member.orders.payments.store', $encryptedId) }}" method="POST" enctype="multipart/form-data" class="form-wrapper" id="member-payment-form">
                                    @csrf
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="mb-1">{{ __('orders.payment_method') }}</label>
                                            <select name="payment_method" class="form-select" required>
                                                @foreach ($customerPaymentMethods as $key => $label)
                                                    <option value="{{ $key }}" {{ old('payment_method') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="mb-1">{{ __('orders.amount_rm') }}</label>
                                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control" id="member-payment-amount"
                                                value="{{ old('amount', number_format($order->balanceDue(), 2, '.', '')) }}" required
                                                @if (!$isCreditCustomer) readonly @endif>
                                            @if (!$isCreditCustomer)
                                                <small class="text-muted">{{ __('orders.member.exact_balance_cod') }}</small>
                                            @endif
                                        </div>
                                        <div class="col-md-4">
                                            <label class="mb-1">{{ __('orders.proof') }}</label>
                                            <input type="file" name="payment_proof" class="form-control payment-proof-input"
                                                accept="{{ \App\OrderPayment::proofAcceptAttribute() }}" required>
                                            <small class="text-muted">{{ \App\OrderPayment::proofHelpText() }}</small>
                                            @error('payment_proof')
                                                <div class="text-danger small mt-1"><strong>{{ $message }}</strong></div>
                                            @enderror
                                        </div>
                                        <div class="col-12">
                                            <label class="mb-1">{{ __('orders.notes') }} ({{ __('orders.optional') }})</label>
                                            <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" placeholder="{{ __('orders.member.notes_placeholder') }}">
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary">{{ __('orders.member.submit_payment_proof') }}</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif

                    @if ($payments->count())
                        <h6 class="mb-3">{{ __('orders.payment_history') }}</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th>{{ __('orders.date') }}</th>
                                        <th>{{ __('orders.method') }}</th>
                                        <th class="text-end">{{ __('orders.amount_rm') }}</th>
                                        <th>{{ __('orders.status') }}</th>
                                        <th>{{ __('orders.notes') }}</th>
                                        <th>{{ __('orders.proof') }}</th>
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
                                                    <a href="{{ route('member.orders.payment-proof', [$encryptedId, $payment->id]) }}" target="_blank" class="btn btn-sm btn-outline-primary">{{ __('orders.view') }}</a>
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
                    <h5 class="modal-title">{{ __('orders.pdf_preview') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
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
                return required ? @json(__('orders.js.payment_proof_required')) : null;
            }

            var extension = file.name.split('.').pop().toLowerCase();
            if (proofAllowedExtensions.indexOf(extension) === -1) {
                return @json(__('orders.js.payment_proof_format'));
            }

            if (file.size > proofMaxBytes) {
                return @json(__('orders.js.payment_proof_size', ['size' => \App\OrderPayment::PROOF_MAX_KB / 1024]));
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
