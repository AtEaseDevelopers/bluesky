@extends('layouts.admin')
@section('title', 'Review Order #' . $order->id)
@section('content')

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="card-title mb-0">
                            {{ $order->status === Order::$status['pending'] ? 'Review Pending Order' : 'Adjust Order' }} #{{ $order->id }}
                        </h5>
                        <a href="{{ route('admin.orders.summary', $order->id) }}" class="btn btn-secondary">Back</a>
                    </div>
                    <p><strong>Customer:</strong> {{ $customerName }}</p>
                    <hr>

                    <form action="{{ route('admin.orders.review.store', $order->id) }}" method="POST" class="form-wrapper" id="review-form">
                        @csrf
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered" id="review-items-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Unit Price (RM)</th>
                                        <th>Est. Qty</th>
                                        <th>Actual Qty</th>
                                        <th>Actual Weight (kg)</th>
                                        <th class="text-end">Line Total (RM)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($products as $product)
                                        <tr class="review-line" data-unit-price="{{ $product->unit_price }}">
                                            <td>{{ $product->product_name }}</td>
                                            <td>{{ number_format($product->unit_price, 2) }}</td>
                                            <td>{{ $product->quantity }}</td>
                                            <td>
                                                <input type="number" step="0.001" min="0.001" class="form-control line-qty"
                                                    name="line_items[{{ $product->id }}][quantity]" value="{{ $product->quantity }}" required>
                                            </td>
                                            <td>
                                                <input type="number" step="0.001" min="0" class="form-control line-weight"
                                                    name="line_items[{{ $product->id }}][weight]" value="{{ $product->product_weight ?? $product->weight }}">
                                            </td>
                                            <td class="line-total text-end">{{ number_format($product->price, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="5" class="text-end"><strong>Subtotal</strong></td>
                                        <td class="text-end"><strong id="review-subtotal">0.00</strong></td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="text-end">
                                            <label for="delivery_fee" class="mb-0"><strong>Delivery Fee (RM)</strong> <span class="text-danger">*</span></label>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" name="delivery_fee" id="delivery_fee" class="form-control text-end"
                                                value="{{ old('delivery_fee', number_format($order->delivery_fee, 2, '.', '')) }}" required>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="text-end"><strong>Amount Adjustment (RM)</strong></td>
                                        <td>
                                            @if ($canAdjustAmount)
                                                <input type="number" step="0.01" name="amount_adjustment" id="amount_adjustment" class="form-control text-end"
                                                    value="{{ old('amount_adjustment', number_format($order->amount_adjustment, 2, '.', '')) }}">
                                            @else
                                                <input type="number" step="0.01" id="amount_adjustment" class="form-control text-end"
                                                    value="{{ number_format($order->amount_adjustment, 2, '.', '') }}" readonly>
                                                <input type="hidden" name="amount_adjustment" value="{{ number_format($order->amount_adjustment, 2, '.', '') }}">
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="text-end"><strong>Grand Total (RM)</strong></td>
                                        <td class="text-end"><strong id="review-grand-total">0.00</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                            <small class="text-muted">Enter 0 for delivery fee if none applies. Set delivery fee here together with quantity adjustments.</small>
                        </div>

                        <div class="row">
                            @if ($isCreditCustomer)
                                <div class="col-md-4">
                                    <div class="mb-4">
                                        <label class="mb-2">Payment Due Date</label>
                                        <input type="date" name="payment_due_date" class="form-control"
                                            value="{{ old('payment_due_date', optional($order->payment_due_date)->format('Y-m-d')) }}">
                                        <small class="text-muted">Credit customer only. Defaults to 30 days from today if left blank when sending to customer.</small>
                                    </div>
                                </div>
                            @endif
                            <div class="col-md-4">
                                <div class="mb-4">
                                    <label class="mb-2">Assign Driver</label>
                                    <select name="driver_id" class="form-select">
                                        <option value="">None</option>
                                        @foreach ($drivers as $id => $lorry)
                                            <option value="{{ $id }}" {{ $order->driver_id == $id ? 'selected' : '' }}>{{ $lorry }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="mb-4">
                                    <label class="mb-2">Adjustment Remark</label>
                                    <input type="text" name="adjustment_remark" class="form-control" value="{{ old('adjustment_remark', $order->adjustment_remark) }}">
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" name="send_to_customer" value="0" class="btn btn-secondary">Save Adjustments</button>
                            @if ($order->status === Order::$status['pending'])
                                <button type="submit" name="send_to_customer" value="1" class="btn btn-primary">Send to Customer Reviewing</button>
                            @else
                                <button type="submit" name="send_to_customer" value="1" class="btn btn-primary">Save & Update Customer Invoice</button>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('script')
    <script>
        function recalculateReviewTotals() {
            var subtotal = 0;

            document.querySelectorAll('#review-items-table tbody tr.review-line').forEach(function (row) {
                var unitPrice = parseFloat(row.dataset.unitPrice) || 0;
                var qtyInput = row.querySelector('.line-qty');
                var qty = parseFloat(qtyInput.value) || 0;
                var lineTotal = unitPrice * qty;

                row.querySelector('.line-total').textContent = lineTotal.toFixed(2);
                subtotal += lineTotal;
            });

            var deliveryFee = parseFloat(document.getElementById('delivery_fee').value) || 0;
            var adjustment = parseFloat(document.getElementById('amount_adjustment').value) || 0;
            var grandTotal = Math.max(0, subtotal + deliveryFee + adjustment);

            document.getElementById('review-subtotal').textContent = subtotal.toFixed(2);
            document.getElementById('review-grand-total').textContent = grandTotal.toFixed(2);
        }

        document.querySelectorAll('.line-qty, #delivery_fee, #amount_adjustment').forEach(function (el) {
            el.addEventListener('input', recalculateReviewTotals);
        });

        recalculateReviewTotals();
    </script>
@endsection
