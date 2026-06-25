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

    {{-- Order items --}}
    <div class="card driver-card mb-3">
        <div class="card-body">
            <h5 class="display-font mb-3" style="font-size:1.15rem;">{{ __('driver_portal.deliveries.order_items') }}</h5>
            @forelse ($order->products as $item)
                <div class="d-flex justify-content-between py-2" style="border-bottom:1px solid var(--line);">
                    <div>
                        <div>{{ $item->product_name }}</div>
                        <div class="text-muted-ink" style="font-size:.9rem;">
                            @php
                                $qtyText = (string) $item->quantity;
                                if ($item->weight) {
                                    $qtyText .= ' · ' . $item->weight;
                                }
                            @endphp
                            {{ __('driver_portal.deliveries.qty', ['qty' => $qtyText]) }}
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
        </div>
    </div>

    {{-- Payment status (COD only) --}}
    @if ($order->isCodCustomer() && ($driverCan('record_payment') || $driverCan('payment_proof')))
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
@if ($order->isCodCustomer() && $driverCan('record_payment'))
@section('script')
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
@endsection
@endif
