@extends('driver.layouts.app')
@section('title', $order->do_no ?? __('driver_portal.deliveries.order_number', ['id' => $order->id]))
@section('content')

    @php
        $statusLabel = \App\Http\Controllers\Driver\DeliveryOrderController::statusLabel($order->status);
        $canonicalStatus = \App\Http\Controllers\Driver\DeliveryOrderController::$legacy_status_map[$order->status] ?? $order->status;
        $total = (float) $order->total_price;
        $paid = (float) $order->paid_amount;
        $balance = $total - $paid;
        if ($paid <= 0) { $payLabel = __('driver_portal.payment.unpaid'); $payClass = 'pill-unpaid'; }
        elseif ($balance > 0.001) { $payLabel = __('driver_portal.payment.partial'); $payClass = 'pill-partial'; }
        else { $payLabel = __('driver_portal.payment.paid'); $payClass = 'pill-paid'; }
    @endphp

    <a href="{{ route('driver.orders.index') }}" class="btn btn-link ps-0 mb-2" style="text-decoration:none; font-weight:600;">
        <i class="fa fa-arrow-left me-1"></i> {{ __('driver_portal.deliveries.back') }}
    </a>

    @php
        $driverPermissions = $driverPermissions ?? [];
        $driverCan = fn (string $permission) => $driverPermissions[$permission] ?? true;
    @endphp

    @if ($driverCan('customer_info'))
    <div class="card driver-card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
                <h2 class="display-font mb-0" style="font-size:1.5rem;">{{ $order->do_no ?? __('driver_portal.deliveries.order_number', ['id' => $order->id]) }}</h2>
                <span class="pill pill-{{ $canonicalStatus }}">{{ $statusLabel }}</span>
            </div>
            @if ($order->do_date)
                <div class="text-muted-ink mt-1"><i class="fa fa-calendar me-1"></i>{{ \Illuminate\Support\Carbon::parse($order->do_date)->format('d M Y') }}</div>
            @endif

            <hr style="border-color:var(--line);">
            <div class="row g-3">
                <div class="col-6">
                    <div class="detail-label">{{ __('driver_portal.deliveries.customer') }}</div>
                    <div>{{ $order->attn_name ?? optional($order->customer)->name ?? '—' }}</div>
                </div>
                <div class="col-6">
                    <div class="detail-label">{{ __('driver_portal.deliveries.contact') }}</div>
                    <div>
                        @php $contact = $order->attn_contact ?? optional($order->customer)->phone; @endphp
                        @if ($contact)
                            <a href="tel:{{ $contact }}" class="fw-semibold"><i class="fa fa-phone me-1"></i>{{ $contact }}</a>
                        @else — @endif
                    </div>
                </div>
                <div class="col-12">
                    <div class="detail-label">{{ __('driver_portal.deliveries.delivery_address') }}</div>
                    <div>{{ $order->shipping_address ?? $order->billing_address ?? '—' }}
                        {{ $order->shipping_postcode ?? $order->billing_postcode }}
                        {{ $order->shipping_state ?? $order->billing_state }}</div>
                </div>
            </div>
        </div>
    </div>
    @else
    <div class="card driver-card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
                <h2 class="display-font mb-0" style="font-size:1.5rem;">{{ $order->do_no ?? __('driver_portal.deliveries.order_number', ['id' => $order->id]) }}</h2>
                <span class="pill pill-{{ $canonicalStatus }}">{{ $statusLabel }}</span>
            </div>
        </div>
    </div>
    @endif

    {{-- Order items / adjust --}}
    <div class="card driver-card mb-3">
        <div class="card-body">
            <h5 class="display-font mb-3" style="font-size:1.15rem;">{{ __('driver_portal.deliveries.order_items') }}</h5>

            @if ($canAdjustOrder && $driverCan('adjust_order'))
                <p class="text-muted-ink mb-3" style="font-size:.92rem;">{{ __('driver_portal.deliveries.adjust_order_help') }}</p>
                <form action="{{ route('driver.orders.adjust', $order->id) }}" method="POST" id="driver-adjust-form">
                    @csrf
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" id="driver-adjust-table">
                            <thead>
                                <tr>
                                    <th>{{ __('orders.product') }}</th>
                                    <th>{{ __('driver_portal.deliveries.unit_price') }}</th>
                                    <th>{{ __('driver_portal.deliveries.estimated') }}</th>
                                    <th>{{ __('driver_portal.deliveries.actual_qty') }}</th>
                                    <th>{{ __('driver_portal.deliveries.actual_weight') }}</th>
                                    <th class="text-end">{{ __('driver_portal.deliveries.line_total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($orderProducts as $item)
                                    @php
                                        $catalogProduct = $productsById->get($item->product_id);
                                        $sellIn = \App\Product::resolveSellInForOrderLine($item, $catalogProduct);
                                        $needsQty = \App\Product::lineNeedsQuantityInput($sellIn);
                                        $needsWeight = \App\Product::lineNeedsWeightInput($sellIn);
                                        $estLabel = \App\Product::formatOrderLineQtyLabel($item, $catalogProduct);
                                        $reviewQty = $needsQty ? $item->quantity : null;
                                        $reviewWeight = $needsWeight ? ($item->weight ?? $item->product_weight) : null;
                                        $initialLineTotal = (float) $item->price;
                                        if ($catalogProduct) {
                                            $calcProduct = clone $catalogProduct;
                                            $calcProduct->sell_in = $sellIn;
                                            $initialLineTotal = $calcProduct->calculateLinePrice(
                                                (float) $item->unit_price,
                                                $reviewQty !== null ? (float) $reviewQty : null,
                                                $reviewWeight !== null ? (float) $reviewWeight : null
                                            );
                                        }
                                    @endphp
                                    <tr class="driver-adjust-line" data-unit-price="{{ $item->unit_price }}" data-sell-in="{{ $sellIn }}">
                                        <td>{{ $item->product_name }}</td>
                                        <td>{{ number_format((float) $item->unit_price, 2) }}</td>
                                        <td>{{ $estLabel }}</td>
                                        <td>
                                            @if ($needsQty)
                                                <input type="number" step="0.001" min="0.001" class="form-control form-control-sm line-qty"
                                                    name="line_items[{{ $item->id }}][quantity]" value="{{ $item->quantity }}" required>
                                            @else
                                                <span class="text-muted-ink">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($needsWeight)
                                                <input type="number" step="0.001" min="0.001" class="form-control form-control-sm line-weight"
                                                    name="line_items[{{ $item->id }}][weight]" value="{{ $item->weight ?? $item->product_weight }}" required>
                                            @else
                                                <span class="text-muted-ink">—</span>
                                            @endif
                                        </td>
                                        <td class="line-total text-end">RM {{ number_format($initialLineTotal, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="5" class="text-end fw-bold">{{ __('driver_portal.deliveries.total_amount') }}</td>
                                    <td class="text-end fw-bold" id="driver-adjust-grand-total">RM {{ number_format($total, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <button type="submit" class="btn btn-brand btn-block-tall w-100 mt-3">
                        <i class="fa fa-save me-1"></i> {{ __('driver_portal.deliveries.save_adjustments') }}
                    </button>
                </form>
            @else
                @forelse ($orderProducts as $item)
                    @php
                        $catalogProduct = $productsById->get($item->product_id);
                        $qtyLabel = \App\Product::formatOrderLineQtyLabel($item, $catalogProduct);
                    @endphp
                    <div class="d-flex justify-content-between py-2" style="border-bottom:1px solid var(--line);">
                        <div>
                            <div>{{ $item->product_name }}</div>
                            <div class="text-muted-ink" style="font-size:.9rem;">
                                {{ __('driver_portal.deliveries.qty', ['qty' => $qtyLabel]) }}
                            </div>
                        </div>
                        <div class="text-end fw-semibold">RM {{ number_format((float) $item->price, 2) }}</div>
                    </div>
                @empty
                    <div class="text-muted-ink">{{ __('driver_portal.deliveries.no_items') }}</div>
                @endforelse

                <div class="d-flex justify-content-between mt-3 fw-bold" style="font-size:1.1rem;">
                    <div>{{ __('driver_portal.deliveries.total_amount') }}</div>
                    <div>RM {{ number_format($total, 2) }}</div>
                </div>
            @endif
        </div>
    </div>

    {{-- Payment status --}}
    @if ($driverCan('record_payment') || $driverCan('payment_proof') || ($driverCan('make_payment') && $balance > 0.001))
    <div class="card driver-card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="display-font mb-0" style="font-size:1.15rem;">{{ __('driver_portal.deliveries.payment') }}</h5>
                <span class="pill {{ $payClass }}">{{ $payLabel }}</span>
            </div>
            <div class="row g-2 text-center">
                <div class="col-4"><div class="detail-label">{{ __('driver_portal.deliveries.total') }}</div><div class="fw-semibold">RM {{ number_format($total, 2) }}</div></div>
                <div class="col-4"><div class="detail-label">{{ __('driver_portal.deliveries.paid') }}</div><div class="fw-semibold">RM {{ number_format($paid, 2) }}</div></div>
                <div class="col-4"><div class="detail-label">{{ __('driver_portal.deliveries.balance') }}</div><div class="fw-semibold">RM {{ number_format(max($balance, 0), 2) }}</div></div>
            </div>
            @if ($driverCan('make_payment') && $balance > 0.001)
                <div class="mt-3 pt-3" style="border-top:1px solid var(--line);">
                    <button type="button" class="btn btn-accent btn-block-tall w-100" disabled>
                        <i class="fa fa-credit-card me-1"></i> {{ __('driver_portal.deliveries.make_payment') }}
                    </button>
                    <p class="text-muted-ink mb-0 mt-2" style="font-size:.88rem;">{{ __('driver_portal.deliveries.payment_gateway_coming_soon') }}</p>
                </div>
            @endif
            @if ($order->isCodCustomer())
            @php
                $latestPayment = $order->payments->where('status', 'confirmed')->sortByDesc('id')->first();
                $methodKey = $latestPayment ? 'order.payment_methods.' . $latestPayment->payment_method : null;
                $methodLabel = $methodKey ? __($methodKey) : null;
                if ($methodLabel === $methodKey) {
                    $methodLabel = $latestPayment ? ucfirst($latestPayment->payment_method) : null;
                }
            @endphp
            @if ($latestPayment)
                <hr style="border-color:var(--line);">
                <div class="text-muted-ink" style="font-size:.92rem;">
                    {{ $methodLabel }}
                    · {{ $latestPayment->created_at->format('d M Y, h:i A') }}
                </div>
                @if ($latestPayment->payment_proof && $driverCan('payment_proof'))
                    <div class="mt-2">
                        <a href="{{ route('driver.orders.payment-proof', $order->id) }}" target="_blank" class="btn btn-sm btn-outline-brand">
                            <i class="fa fa-file me-1"></i> {{ __('driver_portal.deliveries.view_payment_proof') }}
                        </a>
                    </div>
                @endif
            @endif
            @endif
        </div>
    </div>
    @endif

    {{-- Update delivery status --}}
    @if ($driverCan('update_status'))
    <div class="card driver-card mb-3">
        <div class="card-body">
            <h5 class="display-font mb-3" style="font-size:1.15rem;">{{ __('driver_portal.deliveries.update_status') }}</h5>
            <form action="{{ route('driver.orders.update-status', $order->id) }}" method="POST">
                @csrf
                <div class="d-flex gap-2">
                    @foreach ($driverStatuses as $value => $label)
                        <button type="submit" name="status" value="{{ $value }}"
                            class="btn btn-block-tall flex-fill {{ $canonicalStatus === $value ? 'btn-brand' : 'btn-outline-brand' }}">
                            @if ($value === 'in_route') <i class="fa fa-truck me-1"></i> @else <i class="fa fa-check-circle me-1"></i> @endif
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- Record payment (COD only) --}}
    @if ($order->isCodCustomer() && $driverCan('record_payment'))
    <div class="card driver-card mb-3">
        <div class="card-body">
            <h5 class="display-font mb-3" style="font-size:1.15rem;">{{ __('driver_portal.deliveries.record_payment') }}</h5>
            <form action="{{ route('driver.orders.record-payment', $order->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="payment_method">{{ __('driver_portal.deliveries.payment_method') }}</label>
                    <select class="form-select @error('payment_method') is-invalid @enderror" name="payment_method" id="payment_method" required>
                        <option value="" disabled {{ old('payment_method', $order->payment_method) ? '' : 'selected' }}>{{ __('driver_portal.deliveries.select_method') }}</option>
                        @foreach ($paymentMethods as $value => $label)
                            <option value="{{ $value }}" {{ old('payment_method', $order->payment_method) === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('payment_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="paid_amount">{{ __('driver_portal.deliveries.amount_collected') }}</label>
                    <input type="number" step="0.01" min="0" class="form-control @error('paid_amount') is-invalid @enderror"
                        name="paid_amount" id="paid_amount" value="{{ old('paid_amount', $order->paid_amount ?: number_format($total, 2, '.', '')) }}" required>
                    @error('paid_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3" id="proof-wrapper">
                    <label class="form-label" for="payment_proof">{{ __('driver_portal.deliveries.payment_proof') }} <span class="text-muted-ink" style="font-weight:500;">{{ __('driver_portal.deliveries.payment_proof_hint') }}</span></label>
                    <input type="file" class="form-control @error('payment_proof') is-invalid @enderror"
                        name="payment_proof" id="payment_proof" accept=".jpg,.jpeg,.png,.pdf">
                    @error('payment_proof')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-accent btn-block-tall w-100">
                    <i class="fa fa-check me-1"></i> {{ __('driver_portal.deliveries.save_payment') }}
                </button>
            </form>
        </div>
    </div>
    @endif

@endsection
@if (($canAdjustOrder && $driverCan('adjust_order')) || ($order->isCodCustomer() && $driverCan('record_payment')))
@section('script')
    @if ($canAdjustOrder && $driverCan('adjust_order'))
    <script>
        (function () {
            function driverBillAmount(row) {
                var sellIn = row.dataset.sellIn || 'weight';
                var qtyInput = row.querySelector('.line-qty');
                var weightInput = row.querySelector('.line-weight');
                var qty = qtyInput ? (parseFloat(qtyInput.value) || 0) : 0;
                var weight = weightInput ? (parseFloat(weightInput.value) || 0) : 0;

                if (sellIn === 'qty') {
                    return qty;
                }
                if (sellIn === 'qty_bill_weight' || sellIn === 'weight') {
                    return qty * weight;
                }

                return weight;
            }

            function recalculateDriverAdjustTotals() {
                var deliveryFee = {{ number_format((float) ($order->delivery_fee ?? 0), 2, '.', '') }};
                var adjustment = {{ number_format((float) ($order->amount_adjustment ?? 0), 2, '.', '') }};
                var subtotal = 0;

                document.querySelectorAll('#driver-adjust-table tbody tr.driver-adjust-line').forEach(function (row) {
                    var unitPrice = parseFloat(row.dataset.unitPrice) || 0;
                    var lineTotal = unitPrice * driverBillAmount(row);
                    row.querySelector('.line-total').textContent = 'RM ' + lineTotal.toFixed(2);
                    subtotal += lineTotal;
                });

                var grandTotal = Math.max(0, subtotal + deliveryFee + adjustment);
                document.getElementById('driver-adjust-grand-total').textContent = 'RM ' + grandTotal.toFixed(2);
            }

            document.querySelectorAll('#driver-adjust-table .line-qty, #driver-adjust-table .line-weight').forEach(function (el) {
                el.addEventListener('input', recalculateDriverAdjustTotals);
            });
            recalculateDriverAdjustTotals();
        })();
    </script>
    @endif
    @if ($order->isCodCustomer() && $driverCan('record_payment'))
    <script>
        (function () {
            var proofRequired = @json($proofRequiredMethods);
            var methodEl = document.getElementById('payment_method');
            var proofInput = document.getElementById('payment_proof');
            if (!methodEl || !proofInput) {
                return;
            }

            function toggleProof() {
                proofInput.required = proofRequired.indexOf(methodEl.value) !== -1;
            }
            methodEl.addEventListener('change', toggleProof);
            toggleProof();
        })();
    </script>
    @endif
@endsection
@endif
