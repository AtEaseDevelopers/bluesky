@extends('driver.layouts.app')
@section('title', $customer->name)
@section('content')

    <a href="{{ route('driver.customers.index') }}" class="text-decoration-none d-inline-block mb-3">
        <i class="fa fa-arrow-left me-1"></i> All customers
    </a>

    @php
        $isCredit = $customer->isCreditCustomer();
        $driverPermissions = $driverPermissions ?? [];
        $driverCan = fn (string $permission) => $driverPermissions[$permission] ?? true;
    @endphp

    <div class="card driver-card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <h2 class="display-font mb-0" style="font-size:1.4rem;">{{ $customer->name }}</h2>
                <span class="pill {{ $isCredit ? 'pill-due' : 'pill-paid' }}">{{ $isCredit ? 'Credit' : 'COD' }}</span>
            </div>
            <div class="text-muted-ink mb-1">
                <i class="fa fa-phone me-1"></i>{{ $customer->attn_contact ?? $customer->phone ?? '—' }}
            </div>
            <div class="text-muted-ink mb-3">
                <i class="fa fa-map-marker me-1"></i>{{ $customer->shipping_address ?? $customer->billing_address ?? '—' }}
            </div>
            @if ($isCredit)
                <div class="text-muted-ink mb-3">
                    <i class="fa fa-clock-o me-1"></i>Payment term: {{ $customer->paymentTermLabel() }}
                </div>
            @endif
            <div class="d-flex justify-content-between align-items-center pt-2" style="border-top:1px solid var(--line);">
                <div>
                    <div class="detail-label mb-0">Total Outstanding</div>
                    <span class="fw-bold {{ $outstanding > 0 ? 'text-danger-ink' : '' }}" style="font-size:1.3rem;">RM {{ number_format($outstanding, 2) }}</span>
                </div>
                @if ($overdueCount > 0)
                    <span class="pill pill-unpaid">{{ $overdueCount }} overdue</span>
                @endif
            </div>
        </div>
    </div>

    <h3 class="display-font mb-2" style="font-size:1.1rem;">Invoices</h3>

    @forelse ($invoices as $invoice)
        @php
            $pay = \App\Http\Controllers\Driver\CustomerController::paymentPill($invoice);
            $due = \App\Http\Controllers\Driver\CustomerController::dueBadge($invoice);
            $balance = $invoice->balanceDue();
        @endphp
        <div class="card driver-card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="fw-bold" style="font-size:1.05rem;">
                            {{ $invoice->invoice_number ?? $invoice->do_no ?? ('Order #' . $invoice->id) }}
                        </div>
                        <div class="text-muted-ink">
                            <i class="fa fa-calendar me-1"></i>{{ \Illuminate\Support\Carbon::parse($invoice->do_date ?? $invoice->created_at)->format('d M Y') }}
                        </div>
                    </div>
                    <span class="pill {{ $pay['class'] }}">{{ $pay['label'] }}</span>
                </div>

                <div class="row g-2 text-center pt-2" style="border-top:1px solid var(--line);">
                    <div class="col-4">
                        <div class="detail-label mb-0">Total</div>
                        <span class="fw-bold">RM {{ number_format((float) $invoice->total_price, 2) }}</span>
                    </div>
                    <div class="col-4">
                        <div class="detail-label mb-0">Paid</div>
                        <span class="fw-bold">RM {{ number_format((float) $invoice->paid_amount, 2) }}</span>
                    </div>
                    <div class="col-4">
                        <div class="detail-label mb-0">Balance</div>
                        <span class="fw-bold {{ $balance > 0 ? 'text-danger-ink' : '' }}">RM {{ number_format($balance, 2) }}</span>
                    </div>
                </div>

                @if ($due)
                    <div class="pt-2 mt-2" style="border-top:1px solid var(--line);">
                        <span class="detail-label">Due Date</span>
                        <span class="pill {{ $due['class'] }} ms-1">{{ $due['label'] }}</span>
                    </div>
                @endif

                @if ($driverCan('record_payment') && $invoice->canRecordAdminPayment())
                    <div class="pt-3 mt-2" style="border-top:1px solid var(--line);">
                        <button class="btn btn-accent btn-block-tall w-100" type="button"
                            data-bs-toggle="collapse" data-bs-target="#pay-{{ $invoice->id }}"
                            aria-expanded="false" aria-controls="pay-{{ $invoice->id }}">
                            <i class="fa fa-money me-1"></i> Record Payment
                        </button>
                        <div class="collapse mt-3" id="pay-{{ $invoice->id }}">
                            <form class="js-pay-form" method="POST" enctype="multipart/form-data"
                                action="{{ route('driver.customers.record-payment', [$customer->id, $invoice->id]) }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label" for="method-{{ $invoice->id }}">Payment Method</label>
                                    <select class="form-select js-pay-method" name="payment_method" id="method-{{ $invoice->id }}" required>
                                        <option value="" disabled selected>Select method</option>
                                        @foreach ($paymentMethods as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="amount-{{ $invoice->id }}">Amount Collected (RM)</label>
                                    <input type="number" step="0.01" min="0.01" class="form-control"
                                        name="paid_amount" id="amount-{{ $invoice->id }}"
                                        value="{{ number_format($balance, 2, '.', '') }}"
                                        {{ $isCredit ? '' : 'readonly' }} required>
                                    @unless ($isCredit)
                                        <div class="text-muted-ink mt-1" style="font-size:.9rem;">COD requires the full outstanding balance.</div>
                                    @endunless
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="proof-{{ $invoice->id }}">Payment Proof <span class="text-muted-ink" style="font-weight:500;">(required for QR / Transfer)</span></label>
                                    <input type="file" class="form-control js-pay-proof" name="payment_proof"
                                        id="proof-{{ $invoice->id }}" accept=".jpg,.jpeg,.png,.pdf">
                                </div>
                                <button type="submit" class="btn btn-brand btn-block-tall w-100">
                                    <i class="fa fa-check me-1"></i> Save Payment
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="card driver-card">
            <div class="card-body text-center py-4">
                <i class="fa fa-file-o fa-2x mb-2" style="color: var(--teal);"></i>
                <p class="mb-0 text-muted-ink">No invoices for this customer.</p>
            </div>
        </div>
    @endforelse

@endsection

@if ($driverCan('record_payment'))
@section('script')
    <script>
        (function () {
            var proofRequired = @json($proofRequiredMethods);
            document.querySelectorAll('.js-pay-form').forEach(function (form) {
                var method = form.querySelector('.js-pay-method');
                var proof = form.querySelector('.js-pay-proof');
                if (!method || !proof) {
                    return;
                }
                function toggleProof() {
                    proof.required = proofRequired.indexOf(method.value) !== -1;
                }
                method.addEventListener('change', toggleProof);
                toggleProof();
            });
        })();
    </script>
@endsection
@endif
