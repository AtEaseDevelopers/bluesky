@extends('driver.layouts.app')
@section('title', $order->do_no ?? ('Order #' . $order->id))
@section('content')

    @php
        $statusLabels = [
            'processing' => 'Processing',
            'delivering' => 'In Route',
            'completed'  => 'Delivered',
            'cancelled'  => 'Cancelled',
        ];
        $total = (float) $order->total_price;
        $paid = (float) $order->paid_amount;
        $balance = $total - $paid;
        if ($paid <= 0) { $payLabel = 'Unpaid'; $payClass = 'pill-unpaid'; }
        elseif ($balance > 0.001) { $payLabel = 'Partial'; $payClass = 'pill-partial'; }
        else { $payLabel = 'Paid'; $payClass = 'pill-paid'; }
    @endphp

    <a href="{{ route('driver.orders.index') }}" class="btn btn-link ps-0 mb-2" style="text-decoration:none; font-weight:600;">
        <i class="fa fa-arrow-left me-1"></i> Back to deliveries
    </a>

    <div class="card driver-card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
                <h2 class="display-font mb-0" style="font-size:1.5rem;">{{ $order->do_no ?? ('Order #' . $order->id) }}</h2>
                <span class="pill pill-{{ $order->status }}">{{ $statusLabels[$order->status] ?? ucfirst($order->status) }}</span>
            </div>
            @if ($order->do_date)
                <div class="text-muted-ink mt-1"><i class="fa fa-calendar me-1"></i>{{ \Illuminate\Support\Carbon::parse($order->do_date)->format('d M Y') }}</div>
            @endif

            <hr style="border-color:var(--line);">
            <div class="row g-3">
                <div class="col-6">
                    <div class="detail-label">Customer</div>
                    <div>{{ $order->attn_name ?? optional($order->customer)->name ?? '—' }}</div>
                </div>
                <div class="col-6">
                    <div class="detail-label">Contact</div>
                    <div>
                        @php $contact = $order->attn_contact ?? optional($order->customer)->phone; @endphp
                        @if ($contact)
                            <a href="tel:{{ $contact }}" class="fw-semibold"><i class="fa fa-phone me-1"></i>{{ $contact }}</a>
                        @else — @endif
                    </div>
                </div>
                <div class="col-12">
                    <div class="detail-label">Delivery Address</div>
                    <div>{{ $order->shipping_address ?? $order->billing_address ?? '—' }}
                        {{ $order->shipping_postcode ?? $order->billing_postcode }}
                        {{ $order->shipping_state ?? $order->billing_state }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Order items --}}
    <div class="card driver-card mb-3">
        <div class="card-body">
            <h5 class="display-font mb-3" style="font-size:1.15rem;">Order Items</h5>
            @forelse ($order->products as $item)
                <div class="d-flex justify-content-between py-2" style="border-bottom:1px solid var(--line);">
                    <div>
                        <div>{{ $item->product_name }}</div>
                        <div class="text-muted-ink" style="font-size:.9rem;">
                            Qty: {{ $item->quantity }}@if($item->weight) · {{ $item->weight }}@endif
                        </div>
                    </div>
                    <div class="text-end fw-semibold">RM {{ number_format((float) $item->price, 2) }}</div>
                </div>
            @empty
                <div class="text-muted-ink">No items.</div>
            @endforelse

            <div class="d-flex justify-content-between mt-3 fw-bold" style="font-size:1.1rem;">
                <div>Total Amount</div>
                <div>RM {{ number_format($total, 2) }}</div>
            </div>
        </div>
    </div>

    {{-- Payment status --}}
    <div class="card driver-card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="display-font mb-0" style="font-size:1.15rem;">Payment</h5>
                <span class="pill {{ $payClass }}">{{ $payLabel }}</span>
            </div>
            <div class="row g-2 text-center">
                <div class="col-4"><div class="detail-label">Total</div><div class="fw-semibold">RM {{ number_format($total, 2) }}</div></div>
                <div class="col-4"><div class="detail-label">Paid</div><div class="fw-semibold">RM {{ number_format($paid, 2) }}</div></div>
                <div class="col-4"><div class="detail-label">Balance</div><div class="fw-semibold">RM {{ number_format(max($balance, 0), 2) }}</div></div>
            </div>
            @if ($order->payment_method && $order->payment_collected_at)
                <hr style="border-color:var(--line);">
                <div class="text-muted-ink" style="font-size:.92rem;">
                    {{ \App\Http\Controllers\Driver\DeliveryOrderController::$payment_methods[$order->payment_method] ?? ucfirst($order->payment_method) }}
                    · {{ \Illuminate\Support\Carbon::parse($order->payment_collected_at)->format('d M Y, h:i A') }}
                </div>
                @if ($order->payment_proof)
                    <div class="mt-2">
                        <a href="{{ route('driver.orders.payment-proof', $order->id) }}" target="_blank" class="btn btn-sm btn-outline-brand">
                            <i class="fa fa-file me-1"></i> View Payment Proof
                        </a>
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- Update delivery status --}}
    <div class="card driver-card mb-3">
        <div class="card-body">
            <h5 class="display-font mb-3" style="font-size:1.15rem;">Update Delivery Status</h5>
            <form action="{{ route('driver.orders.update-status', $order->id) }}" method="POST">
                @csrf
                <div class="d-flex gap-2">
                    @foreach ($driverStatuses as $value => $label)
                        <button type="submit" name="status" value="{{ $value }}"
                            class="btn btn-block-tall flex-fill {{ $order->status === $value ? 'btn-brand' : 'btn-outline-brand' }}">
                            @if ($value === 'delivering') <i class="fa fa-truck me-1"></i> @else <i class="fa fa-check-circle me-1"></i> @endif
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </form>
        </div>
    </div>

    {{-- Record payment --}}
    <div class="card driver-card mb-3">
        <div class="card-body">
            <h5 class="display-font mb-3" style="font-size:1.15rem;">Record Payment</h5>
            <form action="{{ route('driver.orders.record-payment', $order->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="payment_method">Payment Method</label>
                    <select class="form-select @error('payment_method') is-invalid @enderror" name="payment_method" id="payment_method" required>
                        <option value="" disabled {{ old('payment_method', $order->payment_method) ? '' : 'selected' }}>Select method</option>
                        @foreach ($paymentMethods as $value => $label)
                            <option value="{{ $value }}" {{ old('payment_method', $order->payment_method) === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('payment_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="paid_amount">Amount Collected (RM)</label>
                    <input type="number" step="0.01" min="0" class="form-control @error('paid_amount') is-invalid @enderror"
                        name="paid_amount" id="paid_amount" value="{{ old('paid_amount', $order->paid_amount ?: number_format($total, 2, '.', '')) }}" required>
                    @error('paid_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3" id="proof-wrapper">
                    <label class="form-label" for="payment_proof">Payment Proof <span class="text-muted-ink" style="font-weight:500;">(required for QR / Transfer)</span></label>
                    <input type="file" class="form-control @error('payment_proof') is-invalid @enderror"
                        name="payment_proof" id="payment_proof" accept=".jpg,.jpeg,.png,.pdf">
                    @error('payment_proof')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-accent btn-block-tall w-100">
                    <i class="fa fa-check me-1"></i> Save Payment
                </button>
            </form>
        </div>
    </div>

@endsection
@section('script')
    <script>
        (function () {
            var proofRequired = @json($proofRequiredMethods);
            var methodEl = document.getElementById('payment_method');
            var proofInput = document.getElementById('payment_proof');

            function toggleProof() {
                proofInput.required = proofRequired.indexOf(methodEl.value) !== -1;
            }
            methodEl.addEventListener('change', toggleProof);
            toggleProof();
        })();
    </script>
@endsection
