@extends('layouts.admin')
@section('title', $order->status === Order::$status['pending'] ? __('orders.review_title_pending', ['id' => $order->id]) : __('orders.review_title_adjust', ['id' => $order->id]))
@section('content')

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="card-title mb-0">
                            {{ $order->status === Order::$status['pending'] ? __('orders.review_title_pending', ['id' => $order->id]) : __('orders.review_title_adjust', ['id' => $order->id]) }}
                        </h5>
                        <a href="{{ route('admin.orders.summary', $order->id) }}" class="btn btn-secondary">{{ __('ui.back') }}</a>
                    </div>
                    <p><strong>{{ __('orders.customer_label') }}</strong> {{ $customerName }}</p>
                    <hr>

                    <form action="{{ route('admin.orders.review.store', $order->id) }}" method="POST" class="form-wrapper" id="review-form">
                        @csrf
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered" id="review-items-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('orders.product') }}</th>
                                        <th>{{ __('orders.unit_price') }}</th>
                                        <th>{{ __('orders.est_qty') }}</th>
                                        <th>{{ __('orders.actual_qty') }}</th>
                                        <th>{{ __('orders.actual_weight') }}</th>
                                        <th class="text-end">{{ __('orders.line_total') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($products as $product)
                                        @php
                                            $sellIn = $product->sell_in ?? \App\Product::SELL_IN_WEIGHT;
                                            $needsQty = in_array($sellIn, [\App\Product::SELL_IN_QTY, \App\Product::SELL_IN_QTY_BILL_WEIGHT], true);
                                            $needsWeight = in_array($sellIn, [\App\Product::SELL_IN_WEIGHT, \App\Product::SELL_IN_QTY_BILL_WEIGHT], true);
                                            $estLabel = match ($sellIn) {
                                                \App\Product::SELL_IN_QTY => $product->quantity ?? '-',
                                                \App\Product::SELL_IN_QTY_BILL_WEIGHT => trim(($product->quantity ?? '-') . ' / ' . ($product->weight ?? $product->product_weight ?? '-')),
                                                default => $product->weight ?? $product->product_weight ?? '-',
                                            };
                                            $catalogProduct = \App\Product::find($product->product_id);
                                            $reviewQty = $needsQty ? $product->quantity : null;
                                            $reviewWeight = $needsWeight ? ($product->weight ?? $product->product_weight) : null;
                                            $initialLineTotal = $catalogProduct
                                                ? $catalogProduct->calculateLinePrice((float) $product->unit_price, $reviewQty !== null ? (float) $reviewQty : null, $reviewWeight !== null ? (float) $reviewWeight : null)
                                                : (float) $product->price;
                                        @endphp
                                        <tr class="review-line" data-unit-price="{{ $product->unit_price }}" data-sell-in="{{ $sellIn }}">
                                            <td>{{ $product->product_name }}</td>
                                            <td>{{ number_format($product->unit_price, 2) }}</td>
                                            <td>{{ $estLabel }}</td>
                                            <td>
                                                @if ($needsQty)
                                                    <input type="number" step="0.001" min="0.001" class="form-control line-qty"
                                                        name="line_items[{{ $product->id }}][quantity]" value="{{ $product->quantity }}" required>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($needsWeight)
                                                    <input type="number" step="0.001" min="0.001" class="form-control line-weight"
                                                        name="line_items[{{ $product->id }}][weight]" value="{{ $product->weight ?? $product->product_weight }}" required>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="line-total text-end">{{ number_format($initialLineTotal, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="5" class="text-end"><strong>{{ __('orders.subtotal') }}</strong></td>
                                        <td class="text-end"><strong id="review-subtotal">0.00</strong></td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="text-end">
                                            <label for="delivery_fee" class="mb-0"><strong>{{ __('orders.delivery_fee_rm') }}</strong> <span class="text-danger">*</span></label>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" name="delivery_fee" id="delivery_fee" class="form-control text-end"
                                                value="{{ old('delivery_fee', number_format($order->delivery_fee, 2, '.', '')) }}" required>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="text-end"><strong>{{ __('orders.amount_adjustment') }}</strong></td>
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
                                        <td colspan="5" class="text-end"><strong>{{ __('orders.grand_total_rm') }}</strong></td>
                                        <td class="text-end"><strong id="review-grand-total">0.00</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                            <small class="text-muted">{{ __('orders.delivery_fee_hint') }}</small>
                        </div>

                        <div class="row">
                            @if ($isCreditCustomer)
                                <div class="col-md-4">
                                    <div class="mb-4">
                                        <label class="mb-2">{{ __('orders.payment_due_date') }}</label>
                                        <input type="date" name="payment_due_date" class="form-control"
                                            value="{{ old('payment_due_date', optional($order->payment_due_date)->format('Y-m-d')) }}">
                                        <small class="text-muted">{{ __('orders.payment_due_date_credit_default') }}</small>
                                    </div>
                                </div>
                            @endif
                            @if (!$isPosOrder)
                            <div class="col-md-4">
                                <div class="mb-4">
                                    <label class="mb-2">{{ __('orders.fulfillment_type') }}</label>
                                    <select name="fulfillment_type" id="fulfillment_type" class="form-select">
                                        <option value="delivery" {{ old('fulfillment_type', $order->fulfillment_type ?? 'delivery') === 'delivery' ? 'selected' : '' }}>{{ __('orders.fulfillment_delivery') }}</option>
                                        <option value="pickup" {{ old('fulfillment_type', $order->fulfillment_type ?? 'delivery') === 'pickup' ? 'selected' : '' }}>{{ __('orders.fulfillment_pickup') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4" id="review-driver-wrap">
                                <div class="mb-4">
                                    <label class="mb-2">{{ __('orders.assign_driver') }}</label>
                                    <select name="driver_id" id="driver_id" class="form-select">
                                        <option value="">{{ __('orders.none') }}</option>
                                        @foreach ($drivers as $id => $label)
                                            <option value="{{ $id }}" {{ (string) old('driver_id', $order->driver_id) === (string) $id ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            @else
                                <input type="hidden" name="fulfillment_type" value="pickup">
                            @endif
                            <div class="col-md-8">
                                <div class="mb-4">
                                    <label class="mb-2">{{ __('orders.adjustment_remark') }}</label>
                                    <input type="text" name="adjustment_remark" class="form-control" value="{{ old('adjustment_remark', $order->adjustment_remark) }}">
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" name="send_to_customer" value="0" class="btn btn-secondary">{{ __('orders.save_adjustments') }}</button>
                            @if ($order->status === Order::$status['pending'] || $order->status === Order::$status['customer_reviewing'])
                                <button type="submit" name="send_to_customer" value="1" class="btn btn-primary">{{ __('orders.save_and_move_to_packing') }}</button>
                            @else
                                <button type="submit" name="send_to_customer" value="1" class="btn btn-primary">{{ __('orders.save_update_customer_invoice') }}</button>
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
        function reviewBillAmount(row) {
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

        function recalculateReviewTotals() {
            var subtotal = 0;

            document.querySelectorAll('#review-items-table tbody tr.review-line').forEach(function (row) {
                var unitPrice = parseFloat(row.dataset.unitPrice) || 0;
                var lineTotal = unitPrice * reviewBillAmount(row);

                row.querySelector('.line-total').textContent = lineTotal.toFixed(2);
                subtotal += lineTotal;
            });

            var deliveryFee = parseFloat(document.getElementById('delivery_fee').value) || 0;
            var adjustment = parseFloat(document.getElementById('amount_adjustment').value) || 0;
            var grandTotal = Math.max(0, subtotal + deliveryFee + adjustment);

            document.getElementById('review-subtotal').textContent = subtotal.toFixed(2);
            document.getElementById('review-grand-total').textContent = grandTotal.toFixed(2);
        }

        document.querySelectorAll('.line-qty, .line-weight, #delivery_fee, #amount_adjustment').forEach(function (el) {
            el.addEventListener('input', recalculateReviewTotals);
        });

        recalculateReviewTotals();

        function toggleReviewDriverField() {
            var fulfillmentEl = document.getElementById('fulfillment_type');
            if (!fulfillmentEl) {
                return;
            }

            var isPickup = fulfillmentEl.value === 'pickup';
            document.getElementById('review-driver-wrap').style.display = isPickup ? 'none' : '';
            document.getElementById('driver_id').disabled = isPickup;
        }

        var fulfillmentTypeEl = document.getElementById('fulfillment_type');
        if (fulfillmentTypeEl) {
            fulfillmentTypeEl.addEventListener('change', toggleReviewDriverField);
            toggleReviewDriverField();
        }
    </script>
@endsection
