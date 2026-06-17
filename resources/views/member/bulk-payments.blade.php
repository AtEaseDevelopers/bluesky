@extends('layouts.member')
@section('title', 'Bulk Payment')
@section('content')

    <div class="row mb-5">
        <div class="col-lg-8">
            <div class="card no-border shadow mb-4">
                <div class="card-body">
                    <h5 class="mb-3">Bulk Payment</h5>
                    <p class="text-muted">Select multiple invoices and submit one payment. We will knock off your selected orders based on the payment amount received.</p>

                    <form action="{{ route('member.bulk-payments.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th width="40"><input type="checkbox" id="selectAllOrders"></th>
                                        <th>Order</th>
                                        <th>Date</th>
                                        <th>Invoice</th>
                                        <th class="text-end">Balance Due (RM)</th>
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
                                            <td colspan="5" class="text-center text-muted">No outstanding invoices.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($orders->count())
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="mb-2">Payment Method</label>
                                        <select name="payment_method" class="form-select" required>
                                            @foreach ($paymentMethods as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="mb-2">Payment Amount (RM)</label>
                                        <input type="number" step="0.01" min="0.01" name="amount" id="bulkPaymentAmount" class="form-control" value="{{ old('amount') }}" required>
                                        <small class="text-muted">Selected balance: RM <span id="selectedBalance">0.00</span>. Partial or overpayments are supported.</small>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="mb-2">Payment Proof</label>
                                        <input type="file" name="payment_proof" class="form-control" accept="{{ \App\OrderPayment::proofAcceptAttribute() }}" required>
                                        <small class="text-muted">{{ \App\OrderPayment::proofHelpText() }}</small>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="mb-2">Notes</label>
                                        <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Submit Bulk Payment</button>
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
