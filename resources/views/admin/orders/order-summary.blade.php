@extends('layouts.admin')
@section('title', 'Order Summary')
@section('content')

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                <h4>Order Summary #{{ $order->id }}</h4>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('admin.orders') }}" class="btn btn-secondary">
                        <i class="fa fa-chevron-circle-left"></i> Back
                    </a>
                    @if (Order::canAdjustQuantities($order->status))
                        <a href="{{ route('admin.orders.review', $order->id) }}" class="btn btn-primary">Adjust Order</a>
                    @endif
                    @if ($order->canShowInvoice() || $order->canShowDeliveryOrder())
                        <div class="btn-group">
                            <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">Documents</button>
                            <ul class="dropdown-menu">
                                @if ($order->canShowInvoice())
                                    <li><a class="dropdown-item view-pdf" href="{{ route('admin.order.invoice', $order->id) }}#toolbar=0" data-url="{{ route('admin.order.invoice', $order->id) }}">View Invoice</a></li>
                                @endif
                                @if ($order->canShowDeliveryOrder())
                                    <li><a class="dropdown-item view-pdf" href="{{ route('admin.order.delivery-order', $order->id) }}#toolbar=0" data-url="{{ route('admin.order.delivery-order', $order->id) }}">View DO</a></li>
                                @endif
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow no-border mb-4">
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p><strong>Customer:</strong> {{ $customerName }}</p>
                                    <p><strong>Order Type:</strong> {{ __('order.order_type.' . $order->order_type) }}</p>
                                    @if ($customer && ($customer->customer_type ?? 'cod') === 'credit')
                                        <p><strong>Customer Type:</strong> Credit</p>
                                    @endif
                                    @if ($order->walk_in_phone)
                                        <p><strong>Phone:</strong> {{ $order->walk_in_phone }}</p>
                                    @endif
                                    <p><strong>Order Date:</strong> {{ $order->created_at->format('Y-m-d h:i a') }}</p>
                                    <p><strong>Delivery:</strong> {{ $order->delivery_date ? $order->delivery_date->format('d-m-Y') : '-' }} {{ $order->delivery_time_slot }}</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Status:</strong> {{ __('order.status.' . $order->status) }}</p>
                                    <p><strong>Payment:</strong>
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
                                    @if ($payments->count())
                                        <p><strong>Payment Methods:</strong> {{ $order->paymentMethodsLabel() }}</p>
                                    @endif
                                    @if ($isCreditCustomer)
                                        @if ($order->payment_due_date)
                                            <p><strong>Payment Due:</strong> {{ $order->payment_due_date->format('d-m-Y') }}</p>
                                        @elseif ($order->balanceDue() > 0 && $order->status !== Order::$status['cancelled'])
                                            <p><strong>Payment Due:</strong> <span class="text-muted">Not set</span></p>
                                        @endif
                                    @endif
                                    @if ($order->invoice_number)
                                        <p><strong>Invoice No:</strong> {{ $order->invoice_number }}</p>
                                    @endif
                                    @if ($order->do_no)
                                        <p><strong>DO No:</strong> {{ $order->do_no }}</p>
                                    @endif
                                    @if ($order->autocount_sync_status ?? false)
                                        <p><strong>AutoCount:</strong> {{ str_replace('_', ' ', ucfirst($order->autocount_sync_status)) }}</p>
                                    @endif
                                    <p><strong>Estimated:</strong> {{ $order->is_estimated ? 'Yes' : 'No' }}</p>
                                </div>
                            </div>

                            <p><strong>Shipping Address:</strong><br>{!! nl2br(e(strip_tags($order->shipping_address))) !!}</p>

                            <div class="table-responsive mt-4">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Unit Price</th>
                                            <th>Qty</th>
                                            <th>Weight</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($products as $product)
                                            <tr>
                                                <td>
                                                    <strong>{{ $product->name }}</strong>
                                                    @if ($product->remark)
                                                        <br><small>Remark: {{ $product->remark }}</small>
                                                    @endif
                                                </td>
                                                <td>{{ number_format($product->unit_price, 2) }}</td>
                                                <td>{{ $product->quantity }}</td>
                                                <td>{{ $product->product_weight ?? $product->weight ?? '-' }}</td>
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
                                        <tr>
                                            <td colspan="4" class="text-end"><strong>Paid</strong></td>
                                            <td class="text-end text-success">{{ number_format($order->paid_amount, 2) }}</td>
                                        </tr>
                                        @if ($payments->count())
                                            <tr>
                                                <td colspan="4" class="text-end"><strong>Payment Breakdown</strong></td>
                                                <td class="text-end">{{ $order->paymentMethodsLabel() }}</td>
                                            </tr>
                                        @endif
                                        <tr>
                                            <td colspan="4" class="text-end"><strong>Balance Due</strong></td>
                                            <td class="text-end text-danger"><strong>{{ number_format($order->balanceDue(), 2) }}</strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    @if ($isCreditCustomer && $order->balanceDue() > 0 && $order->status !== Order::$status['cancelled'])
                        <div class="card shadow no-border mb-4">
                            <div class="card-body">
                                <h5 class="card-title">Payment Due Date</h5>
                                <hr>
                                <form action="{{ route('admin.orders.payment-due-date', $order->id) }}" method="POST">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="mb-1">Due Date</label>
                                        <input type="date" name="payment_due_date" class="form-control"
                                            value="{{ old('payment_due_date', optional($order->payment_due_date)->format('Y-m-d')) }}">
                                        <small class="text-muted">Credit customer only. Status becomes Payment Due after this date if the balance remains unpaid.</small>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">Update Due Date</button>
                                </form>
                            </div>
                        </div>
                    @endif

                    <div class="card shadow no-border mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Status Actions</h5>
                            <hr>
                            @if (count($nextStatuses))
                                @foreach ($nextStatuses as $nextStatus)
                                    <button type="button" class="btn btn-outline-primary w-100 mb-2 btn-change-status"
                                        data-status="{{ $nextStatus }}">
                                        Move to {{ __('order.status.' . $nextStatus) }}
                                    </button>
                                @endforeach
                            @else
                                <p class="text-muted mb-0">No further status changes available.</p>
                            @endif
                            @if ($order->status !== Order::$status['cancelled'] && $order->status !== Order::$status['paid_completed'])
                                <button type="button" class="btn btn-outline-danger w-100 mt-2 btn-change-status" data-status="cancelled">
                                    Cancel Order
                                </button>
                            @endif
                            @if ($order->status === Order::$status['delivered'] && $order->balanceDue() <= 0)
                                <form action="{{ route('admin.orders.complete', $order->id) }}" method="POST" class="mt-3">
                                    @csrf
                                    <button type="submit" class="btn btn-success w-100">Complete Order</button>
                                </form>
                            @endif
                            @if ($order->status === Order::$status['paid_completed'] && $order->payment_status === Order::$payment_status['paid'])
                                <form action="{{ route('admin.orders.sync-autocount', $order->id) }}" method="POST" class="mt-3">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary w-100">Sync to AutoCount</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    @if ($order->canRecordAdminPayment())
                    <div class="card shadow no-border mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Record Payment</h5>
                            @if ($isCreditCustomer)
                                <p class="text-muted small mb-3">Credit customer — partial payments are allowed until the payment due date. One order can use multiple methods (e.g. bank transfer + e-wallet).</p>
                            @else
                                <p class="text-muted small mb-3">COD customer — payment must be collected in full at delivery (cash, QR, or COD). The total must match the balance due exactly; partial or excess payment is not allowed.</p>
                            @endif
                            <hr>
                            <form action="{{ route('admin.orders.payments.store', $order->id) }}" method="POST" enctype="multipart/form-data" id="split-payment-form">
                                @csrf
                                <div id="payment-lines">
                                    <div class="payment-line border rounded p-3 mb-3" data-index="0">
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <label class="mb-1">Method</label>
                                                <select name="payments[0][payment_method]" class="form-select" required>
                                                    @foreach ($paymentMethods as $key => $label)
                                                        <option value="{{ $key }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="mb-1">Amount (RM)</label>
                                                <input type="number" step="0.01" min="0.01" name="payments[0][amount]" class="form-control payment-amount"
                                                    value="{{ number_format($order->balanceDue(), 2, '.', '') }}" required
                                                    @if (!$isCreditCustomer) data-cod-exact="1" @endif>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="mb-1">Proof</label>
                                                <input type="file" name="payments[0][payment_proof]" class="form-control payment-proof-input"
                                                    accept="{{ \App\OrderPayment::proofAcceptAttribute() }}">
                                                <small class="text-muted">{{ \App\OrderPayment::proofHelpText() }}</small>
                                            </div>
                                            <div class="col-12">
                                                <label class="mb-1">Notes</label>
                                                <input type="text" name="payments[0][notes]" class="form-control" placeholder="Optional">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                    @if ($isCreditCustomer)
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="add-payment-line">
                                            <i class="fa fa-plus"></i> Add Payment Method
                                        </button>
                                    @else
                                        <span class="text-muted small">Split across methods? Add lines — total must equal balance due.</span>
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="add-payment-line">
                                            <i class="fa fa-plus"></i> Add Payment Method
                                        </button>
                                    @endif
                                    <span class="text-muted small">Total entering: RM <strong id="payment-lines-total">{{ number_format($order->balanceDue(), 2) }}</strong> · Balance due: RM {{ number_format($order->balanceDue(), 2) }}</span>
                                </div>
                                @if ($isCreditCustomer)
                                    <p class="text-muted small">Amounts above balance due are added to the customer's credit balance.</p>
                                @endif
                                <button type="submit" class="btn btn-primary w-100">Record Payment(s)</button>
                            </form>
                        </div>
                    </div>
                    @elseif ($order->balanceDue() > 0 && $order->status !== Order::$status['cancelled'] && !$isCreditCustomer)
                    <div class="alert alert-info mb-4">
                        COD payment can be recorded when this order is <strong>In Route</strong> or <strong>Delivered</strong>.
                    </div>
                    @endif
                </div>
            </div>

            @if ($payments->count())
                <div class="card shadow no-border">
                    <div class="card-body">
                        <h5 class="card-title">Payment History</h5>
                        @if ($payments->where('status', \App\OrderPayment::STATUS_PENDING)->count())
                            <div class="alert alert-warning py-2 mb-3">
                                {{ $payments->where('status', \App\OrderPayment::STATUS_PENDING)->count() }} customer payment proof(s) awaiting review.
                            </div>
                        @endif
                        <hr>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Method</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Recorded By</th>
                                        <th>Notes</th>
                                        <th>Proof</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($payments as $payment)
                                        <tr>
                                            <td>{{ $payment->created_at->format('d-m-Y H:i') }}</td>
                                            <td>{{ ($allPaymentMethods ?? $paymentMethods)[$payment->payment_method] ?? $payment->payment_method }}</td>
                                            <td>{{ number_format($payment->amount, 2) }}</td>
                                            <td>
                                                @php
                                                    $payStatusClass = match ($payment->status) {
                                                        \App\OrderPayment::STATUS_CONFIRMED => 'bg-success',
                                                        \App\OrderPayment::STATUS_PENDING => 'bg-warning text-dark',
                                                        \App\OrderPayment::STATUS_REJECTED => 'bg-danger',
                                                        default => 'bg-secondary',
                                                    };
                                                @endphp
                                                <span class="badge {{ $payStatusClass }}">
                                                    {{ $paymentStatusLabels[$payment->status] ?? ucfirst($payment->status) }}
                                                </span>
                                                @if ($payment->submitter)
                                                    <br><small class="text-muted">By {{ $payment->submitter->name }}</small>
                                                @endif
                                            </td>
                                            <td>{{ $payment->recorder->name ?? ($payment->submitter->name ?? 'System') }}</td>
                                            <td>{{ $payment->notes ?: '-' }}</td>
                                            <td>
                                                @if ($payment->payment_proof)
                                                    <a href="{{ route('admin.orders.payment-proof', [$order->id, $payment->payment_proof]) }}" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                @if ($payment->status === \App\OrderPayment::STATUS_PENDING)
                                                    <form action="{{ route('admin.orders.payments.confirm', [$order->id, $payment->id]) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success">Confirm</button>
                                                    </form>
                                                    <form action="{{ route('admin.orders.payments.reject', [$order->id, $payment->id]) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                                                    </form>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @include('admin.orders.partials.pdf-modal')

@endsection
@section('script')
    <script src="{{ asset('assets/js/sweetalert.min.js') }}"></script>
    <script>
        function postStatusChange(status, driverId) {
            var payload = {
                _token: "{{ csrf_token() }}",
                status: status
            };

            if (driverId) {
                payload.driver_id = driverId;
            }

            $.post("{{ url('/admin/order/update-status/' . $order->id) }}", payload)
                .done(function (response) {
                    var data = typeof response === 'string' ? JSON.parse(response) : response;
                    if (data.success) {
                        location.reload();
                    } else {
                        Swal.fire('Error', data.message || 'Status change failed', 'error');
                    }
                })
                .fail(function () {
                    Swal.fire('Error', 'An error occurred while updating the order status.', 'error');
                });
        }

        var drivers = @json($drivers);
        var currentDriverId = {{ $order->driver_id ? (int) $order->driver_id : 'null' }};

        function buildDriverSelectHtml() {
            var html = '<label for="swal-driver" class="form-label mb-2">Assign Driver <span class="text-danger">*</span></label>';
            html += '<select id="swal-driver" class="form-select">';
            html += '<option value="">Select driver...</option>';
            Object.keys(drivers).forEach(function (id) {
                var selected = currentDriverId && String(currentDriverId) === String(id) ? ' selected' : '';
                html += '<option value="' + id + '"' + selected + '>' + drivers[id] + '</option>';
            });
            html += '</select>';
            return html;
        }

        $('.btn-change-status').on('click', function () {
            var status = $(this).data('status');

            if (status === '{{ Order::$status['in_route'] }}') {
                Swal.fire({
                    title: 'Move to In Route',
                    html: buildDriverSelectHtml(),
                    showCancelButton: true,
                    confirmButtonText: 'Confirm & Dispatch',
                    focusConfirm: false,
                    preConfirm: function () {
                        var driverId = document.getElementById('swal-driver').value;
                        if (!driverId) {
                            Swal.showValidationMessage('Please select a driver');
                            return false;
                        }
                        return driverId;
                    }
                }).then(function (result) {
                    if (result.isConfirmed) {
                        postStatusChange(status, result.value);
                    }
                });
                return;
            }

            Swal.fire({
                title: 'Change Status',
                text: 'Move order to ' + status + '?',
                icon: 'question',
                showCancelButton: true,
            }).then(function (result) {
                if (result.isConfirmed) {
                    postStatusChange(status);
                }
            });
        });

        $(".view-pdf").click(function(e) {
            e.preventDefault();
            $("#pdfFrame").attr("src", $(this).attr("href"));
            $("#downloadLink").attr("href", $(this).data('url') + '/download');
            $("#pdfModal").modal("show");
        });

        var paymentMethodOptions = @json($paymentMethods);
        var paymentLineIndex = 1;
        var balanceDue = {{ number_format($order->balanceDue(), 2, '.', '') }};
        var requiresExactTotal = {{ $isCreditCustomer ? 'false' : 'true' }};
        var proofAccept = @json(\App\OrderPayment::proofAcceptAttribute());
        var proofHelpText = @json(\App\OrderPayment::proofHelpText());
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

        function validatePaymentProofInputs(form) {
            var inputs = form.querySelectorAll('.payment-proof-input');
            for (var i = 0; i < inputs.length; i++) {
                var input = inputs[i];
                var error = validatePaymentProofFile(input.files[0], input.hasAttribute('required'));
                if (error) {
                    return error;
                }
            }

            return null;
        }

        function updatePaymentLinesTotal() {
            var total = 0;
            document.querySelectorAll('.payment-amount').forEach(function (input) {
                total += parseFloat(input.value) || 0;
            });
            var totalEl = document.getElementById('payment-lines-total');
            if (totalEl) {
                totalEl.textContent = total.toFixed(2);
                if (requiresExactTotal) {
                    totalEl.classList.toggle('text-danger', Math.abs(total - balanceDue) > 0.009);
                    totalEl.classList.toggle('text-success', Math.abs(total - balanceDue) <= 0.009);
                }
            }
        }

        document.getElementById('split-payment-form')?.addEventListener('submit', function (event) {
            var proofError = validatePaymentProofInputs(this);
            if (proofError) {
                event.preventDefault();
                Swal.fire('Invalid payment proof', proofError, 'warning');
                return;
            }

            if (!requiresExactTotal) {
                return;
            }
            var total = 0;
            document.querySelectorAll('.payment-amount').forEach(function (input) {
                total += parseFloat(input.value) || 0;
            });
            if (Math.abs(total - balanceDue) > 0.009) {
                event.preventDefault();
                Swal.fire('Invalid amount', 'COD orders require the exact balance due (RM ' + balanceDue.toFixed(2) + ').', 'warning');
            }
        });

        document.getElementById('split-payment-form')?.addEventListener('input', function (event) {
            if (event.target.matches('.payment-amount')) {
                updatePaymentLinesTotal();
            }
        });

        document.getElementById('add-payment-line')?.addEventListener('click', function () {
            var container = document.getElementById('payment-lines');
            var idx = paymentLineIndex++;
            var optionsHtml = Object.keys(paymentMethodOptions).map(function (key) {
                return '<option value="' + key + '">' + paymentMethodOptions[key] + '</option>';
            }).join('');

            var line = document.createElement('div');
            line.className = 'payment-line border rounded p-3 mb-3';
            line.dataset.index = idx;
            line.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Payment ${idx + 1}</strong>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-payment-line">&times; Remove</button>
                </div>
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="mb-1">Method</label>
                        <select name="payments[${idx}][payment_method]" class="form-select" required>${optionsHtml}</select>
                    </div>
                    <div class="col-md-4">
                        <label class="mb-1">Amount (RM)</label>
                        <input type="number" step="0.01" min="0.01" name="payments[${idx}][amount]" class="form-control payment-amount" value="0.01" required>
                    </div>
                    <div class="col-md-4">
                        <label class="mb-1">Proof</label>
                        <input type="file" name="payments[${idx}][payment_proof]" class="form-control payment-proof-input" accept="${proofAccept}">
                        <small class="text-muted">${proofHelpText}</small>
                    </div>
                    <div class="col-12">
                        <label class="mb-1">Notes</label>
                        <input type="text" name="payments[${idx}][notes]" class="form-control" placeholder="Optional">
                    </div>
                </div>
            `;
            container.appendChild(line);
            updatePaymentLinesTotal();
        });

        document.getElementById('payment-lines')?.addEventListener('click', function (event) {
            if (event.target.closest('.remove-payment-line')) {
                var lines = document.querySelectorAll('#payment-lines .payment-line');
                if (lines.length <= 1) {
                    Swal.fire('Warning', 'At least one payment line is required.', 'warning');
                    return;
                }
                event.target.closest('.payment-line').remove();
                updatePaymentLinesTotal();
            }
        });
    </script>
@endsection
