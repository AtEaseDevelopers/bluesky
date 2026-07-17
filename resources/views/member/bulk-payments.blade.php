@extends('layouts.member')
@section('title', __('orders.member.bulk_payment.title'))
@section('content')

    <div class="row mb-5">
        <div class="col-lg-8">
            <div class="card no-border shadow mb-4">
                <div class="card-body">
                    <h5 class="mb-3">{{ __('orders.member.bulk_payment.title') }}</h5>
                    <p class="text-muted">{{ __('orders.member.bulk_payment.intro') }}</p>

                    <form action="{{ route('member.bulk-payments.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th width="40"><input type="checkbox" id="selectAllOrders"></th>
                                        <th>{{ __('orders.member.bulk_payment.order') }}</th>
                                        <th>{{ __('orders.member.bulk_payment.date') }}</th>
                                        <th>{{ __('orders.member.bulk_payment.invoice') }}</th>
                                        <th class="text-end">{{ __('orders.member.bulk_payment.balance_due_rm') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($orders as $order)
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="order-checkbox" name="order_ids[]" value="{{ $order->id }}" data-balance="{{ number_format($order->balanceDue(), 2, '.', '') }}">
                                            </td>
                                            <td>#{{ $order->id }}</td>
                                            <td>{{ $order->created_at->format('d M Y') }}</td>
                                            <td>{{ $order->invoice_number ?: '-' }}</td>
                                            <td class="text-end">{{ number_format($order->balanceDue(), 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">{{ __('orders.member.bulk_payment.no_outstanding') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($orders->count())
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="mb-2">{{ __('orders.payment_method') }}</label>
                                        <select name="payment_method" class="form-select" required>
                                            @foreach ($paymentMethods as $key => $label)
                                                @php
                                                    $methodLabel = __('user.payment_method.' . $key);
                                                    if ($methodLabel === 'user.payment_method.' . $key) {
                                                        $methodLabel = $label;
                                                    }
                                                @endphp
                                                <option value="{{ $key }}">{{ $methodLabel }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="mb-2">{{ __('orders.member.bulk_payment.payment_amount_rm') }}</label>
                                        <input type="number" step="0.01" min="0.01" name="amount" id="bulkPaymentAmount" class="form-control" value="{{ old('amount') }}" required>
                                        <small class="text-muted">{{ __('orders.member.bulk_payment.selected_balance_label') }} RM <span id="selectedBalance">0.00</span>. {{ __('orders.member.bulk_payment.partial_overpayment_help') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="mb-2">{{ __('orders.member.upload_payment_proof') }}</label>
                                        <input type="file" name="payment_proof" class="form-control" accept="{{ \App\OrderPayment::proofAcceptAttribute() }}" required>
                                        <small class="text-muted">{{ \App\OrderPayment::proofHelpText() }}</small>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="mb-2">{{ __('orders.member.bulk_payment.notes') }}</label>
                                        <textarea name="notes" class="form-control" rows="2" placeholder="{{ __('orders.member.notes_placeholder') }}">{{ old('notes') }}</textarea>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">{{ __('orders.member.bulk_payment.submit') }}</button>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('script')
<script>
    function updateSelectedBalance() {
        let total = 0;
        document.querySelectorAll('.order-checkbox:checked').forEach(function (el) {
            total += parseFloat(el.dataset.balance || 0);
        });
        document.getElementById('selectedBalance').textContent = total.toFixed(2);
        const amountInput = document.getElementById('bulkPaymentAmount');
        if (amountInput && !amountInput.dataset.manual) {
            amountInput.value = total > 0 ? total.toFixed(2) : '';
        }
    }

    document.getElementById('selectAllOrders')?.addEventListener('change', function () {
        document.querySelectorAll('.order-checkbox').forEach(function (el) {
            el.checked = this.checked;
        }, this);
        updateSelectedBalance();
    });

    document.querySelectorAll('.order-checkbox').forEach(function (el) {
        el.addEventListener('change', updateSelectedBalance);
    });

    document.getElementById('bulkPaymentAmount')?.addEventListener('input', function () {
        this.dataset.manual = '1';
    });
</script>
@endsection
