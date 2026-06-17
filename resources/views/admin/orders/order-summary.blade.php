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
                                                'partial' => 'bg-warning text-dark',
                                                default => 'bg-secondary',
                                            };
                                        @endphp
                                        <span class="badge {{ $paymentBadgeClass }}">
                                            {{ __('order.payment_status.' . $order->payment_status) }}
                                        </span>
                                    </p>
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

                    <div class="card shadow no-border mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Record Payment</h5>
                            <hr>
                            <form action="{{ route('admin.orders.payments.store', $order->id) }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="mb-3">
                                    <label class="mb-1">Method</label>
                                    <select name="payment_method" class="form-select" required>
                                        @foreach ($paymentMethods as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="mb-1">Amount (RM)</label>
                                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control"
                                        value="{{ number_format($order->balanceDue(), 2, '.', '') }}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="mb-1">Payment Proof</label>
                                    <input type="file" name="payment_proof" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                                </div>
                                <div class="mb-3">
                                    <label class="mb-1">Notes</label>
                                    <textarea name="notes" class="form-control" rows="2"></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Record Payment</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            @if ($payments->count())
                <div class="card shadow no-border">
                    <div class="card-body">
                        <h5 class="card-title">Payment History</h5>
                        <hr>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Method</th>
                                        <th>Amount</th>
                                        <th>Recorded By</th>
                                        <th>Notes</th>
                                        <th>Proof</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($payments as $payment)
                                        <tr>
                                            <td>{{ $payment->created_at->format('d-m-Y H:i') }}</td>
                                            <td>{{ $paymentMethods[$payment->payment_method] ?? $payment->payment_method }}</td>
                                            <td>{{ number_format($payment->amount, 2) }}</td>
                                            <td>{{ $payment->recorder->name ?? 'System' }}</td>
                                            <td>{{ $payment->notes ?: '-' }}</td>
                                            <td>
                                                @if ($payment->payment_proof)
                                                    <a href="{{ route('admin.orders.payment-proof', [$order->id, $payment->payment_proof]) }}" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
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
    </script>
@endsection
