@extends('layouts.admin')
@section('title', __('orders.summary'))
@section('content')

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                <h4>{{ __('orders.summary_title', ['id' => $order->id]) }}</h4>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('admin.orders') }}" class="btn btn-secondary">
                        <i class="fa fa-chevron-circle-left"></i> {{ __('ui.back') }}
                    </a>
                    @if (Order::canAdjustQuantities($order->status))
                        <a href="{{ route('admin.orders.review', $order->id) }}" class="btn btn-primary">{{ __('orders.adjust_order') }}</a>
                    @endif
                    @if ($order->canShowInvoice() || $order->canShowDeliveryOrder())
                        <div class="btn-group">
                            <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">{{ __('orders.documents') }}</button>
                            <ul class="dropdown-menu">
                                @if ($order->canShowInvoice())
                                    <li><a class="dropdown-item view-pdf" href="{{ route('admin.order.invoice', $order->id) }}#toolbar=0" data-url="{{ route('admin.order.invoice', $order->id) }}">{{ __('orders.view_invoice') }}</a></li>
                                @endif
                                @if ($order->canShowDeliveryOrder())
                                    <li><a class="dropdown-item view-pdf" href="{{ route('admin.order.delivery-order', $order->id) }}#toolbar=0" data-url="{{ route('admin.order.delivery-order', $order->id) }}">{{ __('orders.view_do') }}</a></li>
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
                                    <p><strong>{{ __('orders.customer_label') }}</strong> {{ $customerName }}</p>
                                    <p><strong>{{ __('orders.order_type') }}:</strong> {{ __('order.order_type.' . $order->order_type) }}</p>
                                    @if ($customer && ($customer->customer_type ?? 'cod') === 'credit')
                                        <p><strong>{{ __('orders.customer_type') }}:</strong> {{ __('orders.customer_type_credit') }}</p>
                                    @endif
                                    @if ($order->walk_in_phone || $order->attn_contact || ($customer->attn_contact ?? null))
                                        <p><strong>{{ __('orders.phone') }}:</strong> {{ $order->walk_in_phone ?: ($order->attn_contact ?: ($customer->attn_contact ?? '-')) }}</p>
                                    @endif
                                    <p><strong>{{ __('orders.order_date') }}:</strong> {{ $order->created_at->format('Y-m-d h:i a') }}</p>
                                    <p><strong>{{ __('orders.delivery') }}:</strong> {{ $order->delivery_date ? $order->delivery_date->format('d-m-Y') : '-' }} {{ $order->delivery_time_slot }}</p>
                                    <p><strong>{{ __('orders.driver_lorry') }}:</strong> {{ $order->driver_id && isset($drivers[$order->driver_id]) ? $drivers[$order->driver_id] : '-' }}</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>{{ __('orders.status_label') }}</strong> {{ __('order.status.' . $order->status) }}</p>
                                    <p><strong>{{ __('orders.payment_label') }}</strong>
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
                                        <p><strong>{{ __('orders.payment_methods') }}:</strong> {{ $order->paymentMethodsLabel() }}</p>
                                    @endif
                                    @if ($isCreditCustomer)
                                        @if ($order->payment_due_date)
                                            <p><strong>{{ __('orders.payment_due_label') }}:</strong> {{ $order->payment_due_date->format('d-m-Y') }}</p>
                                        @elseif ($order->balanceDue() > 0 && $order->status !== Order::$status['cancelled'])
                                            <p><strong>{{ __('orders.payment_due_label') }}:</strong> <span class="text-muted">{{ __('orders.not_set') }}</span></p>
                                        @endif
                                    @endif
                                    @if ($order->invoice_number)
                                        <p><strong>{{ __('orders.invoice_no') }}:</strong> {{ $order->invoice_number }}</p>
                                    @endif
                                    @if ($order->do_no)
                                        <p><strong>{{ __('orders.do_no') }}:</strong> {{ $order->do_no }}</p>
                                    @endif
                                    @if ($order->autocount_sync_status ?? false)
                                        <p><strong>{{ __('orders.autocount') }}:</strong> {{ str_replace('_', ' ', ucfirst($order->autocount_sync_status)) }}</p>
                                    @endif
                                    <p><strong>{{ __('orders.estimated') }}:</strong> {{ $order->is_estimated ? __('orders.yes') : __('orders.no') }}</p>
                                </div>
                            </div>

                            <p><strong>{{ __('orders.shipping_address_label') }}</strong><br>{!! nl2br(e(strip_tags($order->shipping_address))) !!}</p>

                            <div class="table-responsive mt-4">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>{{ __('orders.product') }}</th>
                                            <th>{{ __('orders.unit_price_short') }}</th>
                                            <th>{{ __('orders.qty') }}</th>
                                            <th>{{ __('orders.weight') }}</th>
                                            <th class="text-end">{{ __('orders.total') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($products as $product)
                                            <tr>
                                                <td>
                                                    <strong>{{ $product->name }}</strong>
                                                    @if ($product->remark)
                                                        <br><small>{{ __('orders.remark_label') }} {{ $product->remark }}</small>
                                                    @endif
                                                </td>
                                                <td>{{ number_format($product->unit_price, 2) }}</td>
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
                                        <tr>
                                            <td colspan="4" class="text-end"><strong>{{ __('orders.paid') }}</strong></td>
                                            <td class="text-end text-success">{{ number_format($order->paid_amount, 2) }}</td>
                                        </tr>
                                        @if ($payments->count())
                                            <tr>
                                                <td colspan="4" class="text-end"><strong>{{ __('orders.payment_breakdown') }}</strong></td>
                                                <td class="text-end">{{ $order->paymentMethodsLabel() }}</td>
                                            </tr>
                                        @endif
                                        <tr>
                                            <td colspan="4" class="text-end"><strong>{{ __('orders.balance_due') }}</strong></td>
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
                                <h5 class="card-title">{{ __('orders.payment_due_date') }}</h5>
                                <hr>
                                <form action="{{ route('admin.orders.payment-due-date', $order->id) }}" method="POST">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="mb-1">{{ __('orders.due_date') }}</label>
                                        <input type="date" name="payment_due_date" class="form-control"
                                            value="{{ old('payment_due_date', optional($order->payment_due_date)->format('Y-m-d')) }}">
                                        <small class="text-muted">{{ __('orders.due_date_help') }}</small>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">{{ __('orders.update_due_date') }}</button>
                                </form>
                            </div>
                        </div>
                    @endif

                    <div class="card shadow no-border mb-4">
                        <div class="card-body">
                            <h5 class="card-title">{{ __('orders.assign_driver') }}</h5>
                            <hr>
                            @if (count($drivers))
                                <form action="{{ route('admin.change-order-lorry') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="orders_id" value="{{ encrypt($order->id) }}">
                                    <div class="mb-3">
                                        <label class="mb-1">{{ __('orders.driver_lorry') }}</label>
                                        <select name="driver_id" class="form-select">
                                            <option value="">{{ __('orders.none') }}</option>
                                            @foreach ($drivers as $id => $label)
                                                <option value="{{ $id }}" {{ (int) $order->driver_id === (int) $id ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">{{ __('orders.update_driver') }}</button>
                                </form>
                            @else
                                <p class="text-muted mb-3">{{ __('orders.no_drivers') }}</p>
                                <a href="{{ route('admin.lorry.create') }}" class="btn btn-outline-primary w-100">{{ __('orders.add_driver_lorry') }}</a>
                            @endif
                        </div>
                    </div>

                    <div class="card shadow no-border mb-4">
                        <div class="card-body">
                            <h5 class="card-title">{{ __('orders.status_actions') }}</h5>
                            <hr>
                            @if (count($nextStatuses))
                                @foreach ($nextStatuses as $nextStatus)
                                    <button type="button" class="btn btn-outline-primary w-100 mb-2 btn-change-status"
                                        data-status="{{ $nextStatus }}">
                                        {{ __('orders.move_to', ['status' => __('order.status.' . $nextStatus)]) }}
                                    </button>
                                @endforeach
                            @else
                                <p class="text-muted mb-0">{{ __('orders.no_status_changes') }}</p>
                            @endif
                            @if ($order->status !== Order::$status['cancelled'] && !($order->status === Order::$status['delivered'] && $order->isFullyPaid()))
                                <button type="button" class="btn btn-outline-danger w-100 mt-2 btn-change-status" data-status="cancelled">
                                    {{ __('orders.cancel_order') }}
                                </button>
                            @endif
                            @if ($order->status === Order::$status['delivered'] && $order->isFullyPaid())
                                <form action="{{ route('admin.orders.sync-autocount', $order->id) }}" method="POST" class="mt-3">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary w-100">{{ __('orders.sync_autocount') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    @if ($order->canRecordAdminPayment())
                    <div class="card shadow no-border mb-4">
                        <div class="card-body">
                            <h5 class="card-title">{{ __('orders.record_payment') }}</h5>
                            @if ($isCreditCustomer)
                                <p class="text-muted small mb-3">{{ __('orders.credit_payment_help') }}</p>
                            @else
                                <p class="text-muted small mb-3">{{ __('orders.cod_payment_help') }}</p>
                            @endif
                            <hr>
                            <form action="{{ route('admin.orders.payments.store', $order->id) }}" method="POST" enctype="multipart/form-data" id="split-payment-form">
                                @csrf
                                <div id="payment-lines">
                                    <div class="payment-line border rounded p-3 mb-3" data-index="0">
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <label class="mb-1">{{ __('orders.method') }}</label>
                                                <select name="payments[0][payment_method]" class="form-select" required>
                                                    @foreach ($paymentMethods as $key => $label)
                                                        <option value="{{ $key }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="mb-1">{{ __('orders.amount_rm') }}</label>
                                                <input type="number" step="0.01" min="0.01" name="payments[0][amount]" class="form-control payment-amount"
                                                    value="{{ number_format($order->balanceDue(), 2, '.', '') }}" required
                                                    @if (!$isCreditCustomer) data-cod-exact="1" @endif>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="mb-1">{{ __('orders.proof') }}</label>
                                                <input type="file" name="payments[0][payment_proof]" class="form-control payment-proof-input"
                                                    accept="{{ \App\OrderPayment::proofAcceptAttribute() }}">
                                                <small class="text-muted">{{ \App\OrderPayment::proofHelpText() }}</small>
                                            </div>
                                            <div class="col-12">
                                                <label class="mb-1">{{ __('orders.notes') }}</label>
                                                <input type="text" name="payments[0][notes]" class="form-control" placeholder="{{ __('orders.optional') }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                    @if ($isCreditCustomer)
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="add-payment-line">
                                            <i class="fa fa-plus"></i> {{ __('orders.add_payment_method') }}
                                        </button>
                                    @else
                                        <span class="text-muted small">{{ __('orders.split_payment_hint') }}</span>
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="add-payment-line">
                                            <i class="fa fa-plus"></i> {{ __('orders.add_payment_method') }}
                                        </button>
                                    @endif
                                    <span class="text-muted small">{{ __('orders.total_entering') }}: RM <strong id="payment-lines-total">{{ number_format($order->balanceDue(), 2) }}</strong> · {{ __('orders.balance_due_rm') }} {{ number_format($order->balanceDue(), 2) }}</span>
                                </div>
                                @if ($isCreditCustomer)
                                    <p class="text-muted small">{{ __('orders.credit_overpayment_help') }}</p>
                                @endif
                                <button type="submit" class="btn btn-primary w-100">{{ __('orders.record_payments') }}</button>
                            </form>
                        </div>
                    </div>
                    @elseif ($order->balanceDue() > 0 && $order->status !== Order::$status['cancelled'] && !$isCreditCustomer)
                    <div class="alert alert-info mb-4">
                        {!! __('orders.cod_record_when_html') !!}
                    </div>
                    @endif
                </div>
            </div>

            @if ($payments->count())
                <div class="card shadow no-border">
                    <div class="card-body">
                        <h5 class="card-title">{{ __('orders.payment_history') }}</h5>
                        @if ($payments->where('status', \App\OrderPayment::STATUS_PENDING)->count())
                            <div class="alert alert-warning py-2 mb-3">
                                {{ __('orders.proofs_awaiting', ['count' => $payments->where('status', \App\OrderPayment::STATUS_PENDING)->count()]) }}
                            </div>
                        @endif
                        <hr>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>{{ __('orders.date') }}</th>
                                        <th>{{ __('orders.method') }}</th>
                                        <th>{{ __('orders.amount') }}</th>
                                        <th>{{ __('orders.status') }}</th>
                                        <th>{{ __('orders.recorded_by') }}</th>
                                        <th>{{ __('orders.notes') }}</th>
                                        <th>{{ __('orders.proof') }}</th>
                                        <th>{{ __('orders.actions') }}</th>
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
                                                    <br><small class="text-muted">{{ __('orders.by') }} {{ $payment->submitter->name }}</small>
                                                @endif
                                            </td>
                                            <td>{{ $payment->recorder->name ?? ($payment->submitter->name ?? __('orders.system')) }}</td>
                                            <td>{{ $payment->notes ?: '-' }}</td>
                                            <td>
                                                @if ($payment->payment_proof)
                                                    <a href="{{ route('admin.orders.payment-proof', [$order->id, $payment->payment_proof]) }}" target="_blank" class="btn btn-sm btn-outline-primary">{{ __('orders.view') }}</a>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                @if ($payment->status === \App\OrderPayment::STATUS_PENDING)
                                                    <form action="{{ route('admin.orders.payments.confirm', [$order->id, $payment->id]) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success">{{ __('orders.confirm') }}</button>
                                                    </form>
                                                    <form action="{{ route('admin.orders.payments.reject', [$order->id, $payment->id]) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('orders.reject') }}</button>
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
    @php
        $ordersI18n = [
            'method' => __('orders.method'),
            'amount_rm' => __('orders.amount_rm'),
            'proof' => __('orders.proof'),
            'notes' => __('orders.notes'),
            'optional' => __('orders.optional'),
            'payment_n' => __('orders.payment_n'),
            'remove' => __('orders.remove'),
        ];
    @endphp
    <script src="{{ asset('assets/js/sweetalert.min.js') }}"></script>
    <script>
        var ordersJs = @json(__('orders.js'));
        var ordersI18n = @json($ordersI18n);

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
                        Swal.fire(ordersJs.error, data.message || ordersJs.status_change_failed, 'error');
                    }
                })
                .fail(function () {
                    Swal.fire(ordersJs.error, ordersJs.status_change_error, 'error');
                });
        }

        var drivers = @json($drivers);
        var currentDriverId = {{ $order->driver_id ? (int) $order->driver_id : 'null' }};

        function buildDriverSelectHtml() {
            var html = '<label for="swal-driver" class="form-label mb-2">' + ordersJs.assign_driver_required + ' <span class="text-danger">*</span></label>';
            html += '<select id="swal-driver" class="form-select">';
            html += '<option value="">' + ordersJs.select_driver + '</option>';
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
                    title: ordersJs.move_to_in_route,
                    html: buildDriverSelectHtml(),
                    showCancelButton: true,
                    confirmButtonText: ordersJs.confirm_dispatch,
                    focusConfirm: false,
                    preConfirm: function () {
                        var driverId = document.getElementById('swal-driver').value;
                        if (!driverId) {
                            Swal.showValidationMessage(ordersJs.select_driver_required);
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
                title: ordersJs.change_status,
                text: ordersJs.move_order_to.replace(':status', status),
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
                return required ? ordersJs.payment_proof_required : null;
            }

            var extension = file.name.split('.').pop().toLowerCase();
            if (proofAllowedExtensions.indexOf(extension) === -1) {
                return ordersJs.payment_proof_format;
            }

            if (file.size > proofMaxBytes) {
                return ordersJs.payment_proof_size.replace(':size', (proofMaxBytes / 1024 / 1024));
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
                Swal.fire(ordersJs.invalid_payment_proof, proofError, 'warning');
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
                Swal.fire(ordersJs.invalid_amount, ordersJs.cod_exact_balance.replace(':amount', balanceDue.toFixed(2)), 'warning');
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
                    <strong>${ordersI18n.payment_n.replace(':n', idx + 1)}</strong>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-payment-line">&times; ${ordersI18n.remove}</button>
                </div>
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="mb-1">${ordersI18n.method}</label>
                        <select name="payments[${idx}][payment_method]" class="form-select" required>${optionsHtml}</select>
                    </div>
                    <div class="col-md-4">
                        <label class="mb-1">${ordersI18n.amount_rm}</label>
                        <input type="number" step="0.01" min="0.01" name="payments[${idx}][amount]" class="form-control payment-amount" value="0.01" required>
                    </div>
                    <div class="col-md-4">
                        <label class="mb-1">${ordersI18n.proof}</label>
                        <input type="file" name="payments[${idx}][payment_proof]" class="form-control payment-proof-input" accept="${proofAccept}">
                        <small class="text-muted">${proofHelpText}</small>
                    </div>
                    <div class="col-12">
                        <label class="mb-1">${ordersI18n.notes}</label>
                        <input type="text" name="payments[${idx}][notes]" class="form-control" placeholder="${ordersI18n.optional}">
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
                    Swal.fire(ordersJs.warning, ordersJs.at_least_one_payment, 'warning');
                    return;
                }
                event.target.closest('.payment-line').remove();
                updatePaymentLinesTotal();
            }
        });
    </script>
@endsection
