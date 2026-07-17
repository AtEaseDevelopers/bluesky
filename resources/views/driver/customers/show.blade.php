@extends('driver.layouts.app')
@section('title', $customer->name)
@section('content')

    <a href="{{ route('driver.customers.index') }}" class="text-decoration-none d-inline-block mb-3">
        <i class="fa fa-arrow-left me-1"></i> {{ __('driver_portal.customers.all_customers') }}
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
                <span class="pill {{ $isCredit ? 'pill-due' : 'pill-paid' }}">{{ $isCredit ? __('driver_portal.customers.credit') : __('driver_portal.customers.cod') }}</span>
            </div>
            <div class="text-muted-ink mb-1">
                <i class="fa fa-phone me-1"></i>{{ $customer->attn_contact ?? $customer->phone ?? '—' }}
            </div>
            <div class="text-muted-ink mb-3">
                <i class="fa fa-map-marker me-1"></i>{{ $customer->shipping_address ?? $customer->billing_address ?? '—' }}
            </div>
            @if ($isCredit)
                <div class="text-muted-ink mb-3">
                    <i class="fa fa-clock-o me-1"></i>{{ __('driver_portal.customers.payment_term', ['term' => $customer->paymentTermLabel()]) }}
                </div>
            @endif
            <div class="d-flex justify-content-between align-items-center pt-2" style="border-top:1px solid var(--line);">
                <div>
                    <div class="detail-label mb-0">{{ __('driver_portal.customers.total_outstanding') }}</div>
                    <span class="fw-bold {{ $outstanding > 0 ? 'text-danger-ink' : '' }}" style="font-size:1.3rem;">RM {{ number_format($outstanding, 2) }}</span>
                </div>
                @if ($overdueCount > 0)
                    <span class="pill pill-unpaid">{{ trans_choice('driver_portal.customers.overdue', $overdueCount, ['count' => $overdueCount]) }}</span>
                @endif
            </div>
        </div>
    </div>

    <h3 class="display-font mb-2" style="font-size:1.1rem;">{{ __('driver_portal.customers.invoices_heading') }}</h3>

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
                            {{ $invoice->invoice_number ?? $invoice->do_no ?? __('driver_portal.deliveries.order_number', ['id' => $invoice->id]) }}
                        </div>
                        <div class="text-muted-ink">
                            <i class="fa fa-calendar me-1"></i>{{ \Illuminate\Support\Carbon::parse($invoice->do_date ?? $invoice->created_at)->format('d M Y') }}
                        </div>
                    </div>
                    <span class="pill {{ $pay['class'] }}">{{ $pay['label'] }}</span>
                </div>

                <div class="row g-2 text-center pt-2" style="border-top:1px solid var(--line);">
                    <div class="col-4">
                        <div class="detail-label mb-0">{{ __('driver_portal.deliveries.total') }}</div>
                        <span class="fw-bold">RM {{ number_format((float) $invoice->total_price, 2) }}</span>
                    </div>
                    <div class="col-4">
                        <div class="detail-label mb-0">{{ __('driver_portal.deliveries.paid') }}</div>
                        <span class="fw-bold">RM {{ number_format((float) $invoice->paid_amount, 2) }}</span>
                    </div>
                    <div class="col-4">
                        <div class="detail-label mb-0">{{ __('driver_portal.deliveries.balance') }}</div>
                        <span class="fw-bold {{ $balance > 0 ? 'text-danger-ink' : '' }}">RM {{ number_format($balance, 2) }}</span>
                    </div>
                </div>

                @if ($due)
                    <div class="pt-2 mt-2" style="border-top:1px solid var(--line);">
                        <span class="detail-label">{{ __('driver_portal.customers.due_date') }}</span>
                        <span class="pill {{ $due['class'] }} ms-1">{{ $due['label'] }}</span>
                    </div>
                @endif

                @if ($driverCan('record_payment') && $invoice->canRecordAdminPayment())
                    <div class="pt-3 mt-2" style="border-top:1px solid var(--line);">
                        <button class="btn btn-accent btn-block-tall w-100" type="button"
                            data-bs-toggle="collapse" data-bs-target="#pay-{{ $invoice->id }}"
                            aria-expanded="false" aria-controls="pay-{{ $invoice->id }}">
                            <i class="fa fa-money me-1"></i> {{ __('driver_portal.deliveries.record_payment') }}
                        </button>
                        <div class="collapse mt-3" id="pay-{{ $invoice->id }}">
                            <form class="js-pay-form" method="POST" enctype="multipart/form-data"
                                action="{{ route('driver.customers.record-payment', [$customer->id, $invoice->id]) }}">
                                @csrf
                                @if ($isCredit)
                                    @php $defaultPaymentTiming = old('payment_timing', 'pay_now'); @endphp
                                    <div class="mb-3">
                                        <label class="form-label d-block">{{ __('driver_portal.deliveries.payment_timing_label') }}</label>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input js-pay-timing" type="radio" name="payment_timing" id="pay-now-{{ $invoice->id }}" value="pay_now" {{ $defaultPaymentTiming === 'pay_now' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="pay-now-{{ $invoice->id }}">{{ __('driver_portal.deliveries.pay_now') }}</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input js-pay-timing" type="radio" name="payment_timing" id="pay-later-{{ $invoice->id }}" value="pay_later" {{ $defaultPaymentTiming === 'pay_later' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="pay-later-{{ $invoice->id }}">{{ __('driver_portal.deliveries.pay_later') }}</label>
                                        </div>
                                    </div>
                                    <div class="js-pay-now-fields" style="{{ $defaultPaymentTiming === 'pay_now' ? '' : 'display:none;' }}">
                                @endif
                                <div class="mb-3">
                                    <label class="form-label" for="method-{{ $invoice->id }}">{{ __('driver_portal.deliveries.payment_method') }}</label>
                                    <select class="form-select js-pay-method" name="payment_method" id="method-{{ $invoice->id }}" {{ $isCredit ? '' : 'required' }}>
                                        <option value="" disabled selected>{{ __('driver_portal.deliveries.select_method') }}</option>
                                        @foreach ($paymentMethods as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="amount-{{ $invoice->id }}">{{ __('driver_portal.deliveries.amount_collected') }}</label>
                                    <input type="number" step="0.01" min="0.01" class="form-control js-pay-amount"
                                        name="paid_amount" id="amount-{{ $invoice->id }}"
                                        value="{{ number_format($balance, 2, '.', '') }}"
                                        {{ $isCredit ? '' : 'readonly required' }}>
                                    @unless ($isCredit)
                                        <div class="text-muted-ink mt-1" style="font-size:.9rem;">{{ __('driver_portal.customers.cod_full_balance') }}</div>
                                    @endunless
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="proof-{{ $invoice->id }}">{{ __('driver_portal.deliveries.payment_proof') }} <span class="text-muted-ink" style="font-weight:500;">{{ __('driver_portal.deliveries.payment_proof_hint') }}</span></label>
                                    <input type="file" class="form-control js-pay-proof" name="payment_proof"
                                        id="proof-{{ $invoice->id }}" accept=".jpg,.jpeg,.png,.pdf">
                                </div>
                                @if ($isCredit)
                                    </div>
                                    <div class="alert alert-light border mb-3 js-pay-later-info" style="{{ $defaultPaymentTiming === 'pay_later' ? '' : 'display:none;' }}">
                                        <i class="fa fa-info-circle me-1"></i> {{ __('driver_portal.deliveries.pay_later_help') }}
                                    </div>
                                @endif
                                <button type="submit" class="btn btn-brand btn-block-tall w-100">
                                    <i class="fa fa-check me-1"></i> {{ __('driver_portal.deliveries.save_payment') }}
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
                <p class="mb-0 text-muted-ink">{{ __('driver_portal.customers.no_invoices') }}</p>
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
                var amount = form.querySelector('.js-pay-amount');
                var payNowFields = form.querySelector('.js-pay-now-fields');
                var payLaterInfo = form.querySelector('.js-pay-later-info');
                var timingInputs = form.querySelectorAll('.js-pay-timing');

                function selectedTiming() {
                    var selected = form.querySelector('.js-pay-timing:checked');
                    return selected ? selected.value : 'pay_now';
                }

                function toggleFields() {
                    var isCreditForm = timingInputs.length > 0;
                    if (!isCreditForm) {
                        if (method && proof) {
                            proof.required = proofRequired.indexOf(method.value) !== -1;
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
                    if (method) {
                        method.required = payNow;
                    }
                    if (amount) {
                        amount.required = payNow;
                    }
                    if (proof && method) {
                        proof.required = payNow && proofRequired.indexOf(method.value) !== -1;
                    }
                }

                timingInputs.forEach(function (input) {
                    input.addEventListener('change', toggleFields);
                });
                if (method) {
                    method.addEventListener('change', toggleFields);
                }
                toggleFields();
            });
        })();
    </script>
@endsection
@endif
