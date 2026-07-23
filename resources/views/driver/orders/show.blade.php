@extends('driver.layouts.app')
@section('title', $order->do_no)
@section('css')
    <style>
        .driver-jump-nav {
            position: sticky;
            top: 3.5rem;
            z-index: 1020;
            background: linear-gradient(180deg, var(--bg) 70%, rgba(233, 241, 246, 0));
            padding-top: .25rem;
            padding-bottom: .5rem;
        }
        .driver-order-section {
            scroll-margin-top: 7.5rem;
        }
        .driver-jump-link.active {
            background: var(--deep);
            color: #fff;
            border-color: var(--deep);
        }
    </style>
@endsection
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
                <h2 class="display-font mb-0" style="font-size:1.5rem;">{{ $order->do_no }}</h2>
                <div class="d-flex flex-column align-items-end gap-1">
                    <span class="pill pill-{{ $canonicalStatus }}">{{ $statusLabel }}</span>
                    <span class="pill {{ $order->isCreditCustomer() ? 'pill-due' : 'pill-paid' }}">{{ $order->driverCustomerTypeLabel() }}</span>
                </div>
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
                    <div class="detail-label">{{ __('driver_portal.deliveries.customer_type') }}</div>
                    <div>
                        <span class="pill {{ $order->isCreditCustomer() ? 'pill-due' : 'pill-paid' }}">{{ $order->driverCustomerTypeLabel() }}</span>
                    </div>
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
                <h2 class="display-font mb-0" style="font-size:1.5rem;">{{ $order->do_no }}</h2>
                <div class="d-flex flex-column align-items-end gap-1">
                    <span class="pill pill-{{ $canonicalStatus }}">{{ $statusLabel }}</span>
                    <span class="pill {{ $order->isCreditCustomer() ? 'pill-due' : 'pill-paid' }}">{{ $order->driverCustomerTypeLabel() }}</span>
                </div>
            </div>
        </div>
    </div>
    @endif

    @php
        $latestPayment = $order->payments->where('status', 'confirmed')->sortByDesc('id')->first();
        $canOnlinePayment = $driverCan('make_payment') && $balance > 0.001 && $order->canSettleGatewayPayment();
        $canRecordPayment = $driverCan('record_payment') && $order->canRecordDriverPayment();
        $showPaymentSection = $driverCan('record_payment') || $driverCan('payment_proof') || $canOnlinePayment || $latestPayment;
        $showDeliverySection = $driverCan('update_status');
        $showPaymentModeToggle = $canOnlinePayment && $canRecordPayment;
        $defaultPaymentMode = ($errors->has('payment_method') || $errors->has('paid_amount') || $errors->has('payment_proof') || $errors->has('payment_timing'))
            ? 'record'
            : ($order->hasCodDeliveryPreference() && $canRecordPayment
                ? 'record'
                : ($canRecordPayment && !$canOnlinePayment ? 'record' : 'online'));
        $showOnlinePanelInitially = $canOnlinePayment && (!$showPaymentModeToggle || $defaultPaymentMode === 'online');
        $showRecordPanelInitially = $canRecordPayment && (!$showPaymentModeToggle || $defaultPaymentMode === 'record');
    @endphp

    <div class="driver-jump-nav mb-3">
        <div class="d-flex gap-2 flex-nowrap overflow-auto pb-1">
            <a href="#driver-section-items" class="btn btn-sm btn-outline-brand flex-shrink-0 driver-jump-link">
                <i class="fa fa-list-ul me-1"></i>{{ __('driver_portal.deliveries.jump_items') }}
            </a>
            @if ($showPaymentSection)
                <a href="#driver-section-payment" class="btn btn-sm btn-outline-brand flex-shrink-0 driver-jump-link">
                    <i class="fa fa-money me-1"></i>{{ __('driver_portal.deliveries.jump_payment') }}
                </a>
            @endif
            @if ($showDeliverySection)
                <a href="#driver-section-delivery" class="btn btn-sm btn-outline-brand flex-shrink-0 driver-jump-link">
                    <i class="fa fa-check-circle me-1"></i>{{ __('driver_portal.deliveries.jump_delivery') }}
                </a>
            @endif
        </div>
    </div>

    {{-- Order items / adjust --}}
    <div id="driver-section-items" class="driver-order-section card driver-card mb-3">
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
                                                $reviewWeight !== null ? (float) $reviewWeight : null,
                                                true
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

    {{-- Payment --}}
    @if ($showPaymentSection)
    <div id="driver-section-payment" class="driver-order-section card driver-card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="display-font mb-0" style="font-size:1.15rem;">{{ __('driver_portal.deliveries.payment') }}</h5>
                <span class="pill {{ $payClass }}">{{ $payLabel }}</span>
            </div>
            <div class="row g-2 text-center mb-3">
                <div class="col-4"><div class="detail-label">{{ __('driver_portal.deliveries.total') }}</div><div class="fw-semibold">RM {{ number_format($total, 2) }}</div></div>
                <div class="col-4"><div class="detail-label">{{ __('driver_portal.deliveries.paid') }}</div><div class="fw-semibold">RM {{ number_format($paid, 2) }}</div></div>
                <div class="col-4"><div class="detail-label">{{ __('driver_portal.deliveries.balance') }}</div><div class="fw-semibold">RM {{ number_format(max($balance, 0), 2) }}</div></div>
            </div>

            @if ($canOnlinePayment || $canRecordPayment)
                @if ($showPaymentModeToggle)
                    <div class="btn-group w-100 mb-3" role="group" aria-label="{{ __('driver_portal.deliveries.payment') }}">
                        <input type="radio" class="btn-check" name="driver_payment_mode" id="driver_payment_mode_online" value="online" {{ $defaultPaymentMode === 'online' ? 'checked' : '' }}>
                        <label class="btn btn-outline-brand" for="driver_payment_mode_online">
                            <i class="fa fa-qrcode me-1"></i>{{ __('driver_portal.deliveries.online_payment') }}
                        </label>
                        <input type="radio" class="btn-check" name="driver_payment_mode" id="driver_payment_mode_record" value="record" {{ $defaultPaymentMode === 'record' ? 'checked' : '' }}>
                        <label class="btn btn-outline-brand" for="driver_payment_mode_record">
                            <i class="fa fa-pencil-square-o me-1"></i>{{ __('driver_portal.deliveries.record_payment_option') }}
                        </label>
                    </div>
                @endif

                @if ($canOnlinePayment)
                    <div id="driver-payment-online" @if (!$showOnlinePanelInitially) style="display:none;" @endif>
                        <form method="POST" action="{{ route('driver.orders.rm-pay', $order->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-accent btn-block-tall w-100">
                                <i class="fa fa-qrcode me-1"></i> {{ __('driver_portal.deliveries.make_payment') }}
                            </button>
                        </form>
                        <p class="text-muted-ink mb-0 mt-2" style="font-size:.88rem;">{{ __('driver_portal.deliveries.rm_scan_hint') }}</p>
                    </div>
                @endif

                @if ($canRecordPayment)
                    <div id="driver-payment-record" @if (!$showRecordPanelInitially) style="display:none;" @endif>
                        <form action="{{ route('driver.orders.record-payment', $order->id) }}" method="POST" enctype="multipart/form-data" id="driver-record-payment-form">
                            @csrf
                            @if ($order->isCreditCustomer())
                                @php $defaultPaymentTiming = old('payment_timing', 'pay_now'); @endphp
                                <div class="mb-3">
                                    <label class="form-label d-block">{{ __('driver_portal.deliveries.payment_timing_label') }}</label>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="payment_timing" id="payment_timing_pay_now" value="pay_now" {{ $defaultPaymentTiming === 'pay_now' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="payment_timing_pay_now">{{ __('driver_portal.deliveries.pay_now') }}</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="payment_timing" id="payment_timing_pay_later" value="pay_later" {{ $defaultPaymentTiming === 'pay_later' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="payment_timing_pay_later">{{ __('driver_portal.deliveries.pay_later') }}</label>
                                    </div>
                                </div>
                                <div id="driverPayNowFields" style="{{ $defaultPaymentTiming === 'pay_now' ? '' : 'display:none;' }}">
                            @endif
                            <div class="mb-3">
                                <label class="form-label" for="payment_method">{{ __('driver_portal.deliveries.payment_method') }}</label>
                                <select class="form-select @error('payment_method') is-invalid @enderror" name="payment_method" id="payment_method" {{ $order->isCreditCustomer() ? '' : 'required' }}>
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
                                    name="paid_amount" id="paid_amount" value="{{ old('paid_amount', $order->paid_amount ?: number_format($total, 2, '.', '')) }}" {{ $order->isCreditCustomer() ? '' : 'required' }}>
                                @error('paid_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3" id="proof-wrapper">
                                <label class="form-label" for="payment_proof">{{ __('driver_portal.deliveries.payment_proof') }} <span class="text-muted-ink" style="font-weight:500;">{{ __('driver_portal.deliveries.payment_proof_hint') }}</span></label>
                                <input type="file" class="form-control @error('payment_proof') is-invalid @enderror"
                                    name="payment_proof" id="payment_proof" accept=".jpg,.jpeg,.png,.pdf">
                                @error('payment_proof')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            @if ($order->isCreditCustomer())
                                </div>
                                <div id="driverPayLaterInfo" class="alert alert-light border mb-3" style="{{ $defaultPaymentTiming === 'pay_later' ? '' : 'display:none;' }}">
                                    <i class="fa fa-info-circle me-1"></i> {{ __('driver_portal.deliveries.pay_later_help') }}
                                </div>
                            @endif
                            <button type="submit" class="btn btn-accent btn-block-tall w-100">
                                <i class="fa fa-check me-1"></i> {{ __('driver_portal.deliveries.save_payment') }}
                            </button>
                        </form>
                    </div>
                @endif
            @endif

            @if ($order->preferredPaymentMethodLabel() && !$latestPayment && in_array($order->payment_method, \App\OrderPayment::codDeliveryPreferenceKeys(), true))
                <div class="alert alert-warning py-2 px-3 mb-0 mt-3" style="font-size:.92rem;">
                    <i class="fa fa-info-circle me-1"></i>
                    {{ __('driver_portal.deliveries.expected_payment', ['method' => $order->preferredPaymentMethodLabel()]) }}
                </div>
            @endif
            @php
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
        </div>
    </div>
    @endif

    {{-- Update delivery status --}}
    @if ($showDeliverySection)
    @php
        $deliveryStatusContext = $deliveryStatusContext ?? \App\Http\Controllers\Driver\DeliveryOrderController::driverDeliveryStatusContext($order);
    @endphp
    <div id="driver-section-delivery" class="driver-order-section card driver-card mb-3">
        <div class="card-body">
            <h5 class="display-font mb-3" style="font-size:1.15rem;">{{ __('driver_portal.deliveries.update_status') }}</h5>
            @if ($deliveryStatusContext['mode'] === 'confirm' && count($deliveryStatusContext['statuses'] ?? []))
                <p class="text-muted-ink mb-3" style="font-size:.92rem;">{{ __('driver_portal.deliveries.update_status_help') }}</p>
                <form action="{{ route('driver.orders.update-status', $order->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="delivery_proof">{{ __('driver_portal.deliveries.delivery_proof') }} <span class="text-danger">*</span></label>
                        <input type="file"
                               class="form-control @error('delivery_proof') is-invalid @enderror"
                               name="delivery_proof"
                               id="delivery_proof"
                               accept="image/jpeg,image/png,.jpg,.jpeg,.png"
                               required>
                        @error('delivery_proof')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="d-flex gap-2">
                        @foreach ($deliveryStatusContext['statuses'] as $value => $label)
                            <button type="submit" name="status" value="{{ $value }}"
                                class="btn btn-block-tall flex-fill btn-outline-brand">
                                <i class="fa fa-check-circle me-1"></i>
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </form>
            @elseif ($deliveryStatusContext['mode'] === 'done_with_proof')
                <p class="text-muted-ink mb-3" style="font-size:.92rem;">{{ __('driver_portal.deliveries.already_delivered') }}</p>
                <a href="{{ route('driver.orders.delivery-proof', $order->id) }}" target="_blank" class="btn btn-outline-brand w-100">
                    <i class="fa fa-file-image-o me-1"></i> {{ __('driver_portal.deliveries.view_delivery_proof') }}
                </a>
            @elseif ($deliveryStatusContext['mode'] === 'done_no_proof')
                <p class="text-muted-ink mb-0">
                    {{ __('driver_portal.deliveries.delivered_no_proof', [
                        'status' => \App\Http\Controllers\Driver\DeliveryOrderController::statusLabel($deliveryStatusContext['canonical'] ?? $order->status),
                    ]) }}
                </p>
            @else
                <p class="text-muted-ink mb-0">{{ __('driver_portal.deliveries.wait_for_in_route') }}</p>
            @endif
        </div>
    </div>
    @endif


@endsection
@section('script')
    <script>
        (function () {
            document.querySelectorAll('.driver-jump-link').forEach(function (link) {
                link.addEventListener('click', function (event) {
                    event.preventDefault();
                    var target = document.querySelector(link.getAttribute('href'));
                    if (!target) {
                        return;
                    }

                    document.querySelectorAll('.driver-jump-link').forEach(function (item) {
                        item.classList.remove('active');
                    });
                    link.classList.add('active');

                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            });

            function toggleDriverPaymentMode() {
                var selected = document.querySelector('input[name="driver_payment_mode"]:checked');
                if (!selected) {
                    return;
                }

                var mode = selected.value;
                var onlinePanel = document.getElementById('driver-payment-online');
                var recordPanel = document.getElementById('driver-payment-record');

                if (onlinePanel) {
                    onlinePanel.style.display = mode === 'online' ? '' : 'none';
                }
                if (recordPanel) {
                    recordPanel.style.display = mode === 'record' ? '' : 'none';
                }
            }

            document.querySelectorAll('input[name="driver_payment_mode"]').forEach(function (input) {
                input.addEventListener('change', toggleDriverPaymentMode);
            });
            toggleDriverPaymentMode();

            @if ($errors->has('delivery_proof'))
                document.getElementById('driver-section-delivery')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            @elseif ($errors->has('payment_method') || $errors->has('paid_amount') || $errors->has('payment_proof') || $errors->has('payment_timing'))
                document.getElementById('driver-section-payment')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            @elseif ($errors->any())
                document.getElementById('driver-section-items')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            @endif
        })();
    </script>
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
                if (sellIn === 'qty_bill_weight') {
                    return weight > 0 ? weight : qty;
                }
                if (sellIn === 'weight') {
                    return weight;
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
    @if ($canRecordPayment)
    <script>
        (function () {
            var proofRequired = @json($proofRequiredMethods);
            var methodEl = document.getElementById('payment_method');
            var proofInput = document.getElementById('payment_proof');
            var amountInput = document.getElementById('paid_amount');
            var payNowFields = document.getElementById('driverPayNowFields');
            var payLaterInfo = document.getElementById('driverPayLaterInfo');
            var isCredit = @json($order->isCreditCustomer());

            function selectedTiming() {
                var selected = document.querySelector('input[name="payment_timing"]:checked');
                return selected ? selected.value : 'pay_now';
            }

            function toggleCreditPaymentFields() {
                if (!isCredit) {
                    if (methodEl && proofInput) {
                        proofInput.required = proofRequired.indexOf(methodEl.value) !== -1;
                    }
                    return;
                }

                var payNow = selectedTiming() === 'pay_now';
                if (payNowFields) {
                    payNowFields.style.display = payNow ? '' : 'none';
                }
                if (payLaterInfo) {
                    payLaterInfo.style.display = payNow ? 'none' : '';
                }
                if (methodEl) {
                    methodEl.required = payNow;
                }
                if (amountInput) {
                    amountInput.required = payNow;
                }
                if (proofInput && methodEl) {
                    proofInput.required = payNow && proofRequired.indexOf(methodEl.value) !== -1;
                }
            }

            document.querySelectorAll('input[name="payment_timing"]').forEach(function (el) {
                el.addEventListener('change', toggleCreditPaymentFields);
            });
            if (methodEl) {
                methodEl.addEventListener('change', toggleCreditPaymentFields);
            }
            toggleCreditPaymentFields();
        })();
    </script>
    @endif
@endsection
