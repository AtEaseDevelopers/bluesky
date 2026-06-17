@extends('layouts.member')
@section('title', 'Checkout')
@section('css')

    <style>
        .payment-instructions {
            background-color: #f9f9f9;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 10px;
            margin-top: 20px;
        }

        .payment-instructions h2 {
            color: #333;
        }

        .bank-details p {
            margin-bottom: 10px;
        }

        .bank-details p strong {
            margin-right: 10px;
        }
    </style>

@endsection
@section('content')

    <div class="row mb-5">
        <div class="col-md-8">
            <div class="card no-border shadow">
                <div class="card-body">
                    <h5 class="mb-4">Checkout</h5>
                    <form action="" method="POST" enctype="multipart/form-data" class="form-wrapper">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="attn_name">Attn. Name @if($isGuest ?? false)<span class="text-danger ml-1">*</span>@endif</label>
                                    <input type="text" class="form-control" name="attn_name" id="attn_name" value="{{ old('attn_name')? : $customer->attn_name }}" placeholder="Enter Attn. Name @unless($isGuest ?? false)(optional)@endunless" @if($isGuest ?? false) required @endif>
                                    @error('attn_name')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $errors->first('attn_name') }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="attn_contact">Attn. Contact @if($isGuest ?? false)<span class="text-danger ml-1">*</span>@endif</label>
                                    <input type="text" class="form-control" name="attn_contact" id="attn_contact" value="{{ old('attn_contact')? : $customer->attn_contact }}" placeholder="Enter Attn. Contact @unless($isGuest ?? false)(optional)@endunless" @if($isGuest ?? false) required @endif>
                                    @error('attn_contact')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $errors->first('attn_contact') }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <h6 class="card-subtitle my-3 text-body-secondary">{{ ($isGuest ?? false) ? 'Delivery Info' : 'Billing Info' }}</h6>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="billing_address">{{ ($isGuest ?? false) ? 'Delivery Address' : 'Billing Address' }}<span class="text-danger ml-1">*</span></label>
                                    <textarea id="billing_address" name="billing_address" value="{{ old('billing_address')? : $customer->billing_address }}" class="form-control" rows="3" placeholder="Enter your billing address" required>{{ old('billing_address')? : $customer->billing_address }}</textarea>
                                    @error('billing_address')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $errors->first('billing_address') }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        @unless($isGuest ?? false)
                        <h6 class="card-subtitle my-3 text-body-secondary">Delivery Slot</h6>
                        <div class="row">
                            <div class="col-md-12">
                                @if ($deliverySlots->isEmpty())
                                    <div class="alert alert-warning">
                                        No delivery slots are currently available. Please contact us to place your order.
                                    </div>
                                @else
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="delivery_slot_id">Select Delivery Date & Time <span class="text-danger">*</span></label>
                                    <select name="delivery_slot_id" id="delivery_slot_id" class="form-select" required>
                                        <option value="">Choose a slot...</option>
                                        @foreach ($deliverySlots as $slot)
                                            <option value="{{ $slot->id }}" {{ old('delivery_slot_id') == $slot->id ? 'selected' : '' }}>
                                                {{ $slot->slot_date->format('d M Y') }} — {{ $slot->time_label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('delivery_slot_id')
                                        <span class="text-danger"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                @endif
                            </div>
                        </div>

                        <h6 class="card-subtitle my-3 text-body-secondary">Shipping Info</h6>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="shipping_address">Shipping Address</label>
                                    <textarea id="shipping_address" name="shipping_address" value="{{ old('shipping_address')? : $customer->shipping_address }}" class="form-control" rows="3" placeholder="Enter your shipping address" >{{ old('shipping_address')? : $customer->shipping_address }}</textarea>
                                    @error('shipping_address')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $errors->first('shipping_address') }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="row" id="transferSlipGroup" style="display: none;">
                            <div class="col-md-12">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="transfer_slip">Upload Transfer Slip<span class="text-danger ml-1">*</span></label>
                                    <input type="file" id="transfer_slip" name="transfer_slip" class="form-control" accept="image/*">
                                    @error('transfer_slip')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $errors->first('transfer_slip') }}</strong>
                                        </span>
                                    @enderror
            
                                    <div class="payment-instructions">
                                        <h2>Payment Instructions</h2>
                                        <p>Please make a bank transfer to the following account:</p>
                                        <div class="bank-details">
                                            <p><strong>Bank:</strong> ABC Bank</p>
                                            <p><strong>Account Number:</strong> XXXX-XXXX-XX</p>
                                            <p><strong>Account Holder Name:</strong> {{ config('app.name') }} Sdn Bhd</p>
                                            <p><strong>Amount:</strong> RM{{ number_format($total, 2) }}</p>
                                            <p><strong>Reference:</strong> Your Company Name</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endunless
                        @if($isGuest ?? false)
                            <div class="alert alert-info mt-2 mb-0">
                                <i class="fa fa-money" aria-hidden="true"></i>
                                <strong>Cash on Delivery.</strong> Pay our driver when your order arrives. The final amount may adjust based on the actual weighed seafood.
                            </div>
                        @endif
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-end">
                                    <a href="{{ $portal['cart_url'] }}" class="btn btn-outline-primary me-3 mb-1 px-3">My Cart</a>
                                    <button type="submit" class="btn btn-primary mb-1 px-3" {{ (!($isGuest ?? false) && isset($deliverySlots) && $deliverySlots->isEmpty()) ? 'disabled' : '' }}>
                                        Place Order
                                        <div class="spinner-border spinner-border-sm d-none" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card no-border shadow">
                <div class="card-body">
                    <h5 class="mb-4">Order Summary</h5>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price (RM)</th>
                                <th>Qty/Weight</th>
                                <th>Total (RM)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($products as $product)
                                <tr>
                                    <td>
                                        <strong>{{ $product->name }}<br /></strong>
                                        @foreach($product->options as $opt => $opt_itm)
                                            {{ $opt }}: {{ $opt_itm }}<br />
                                        @endforeach
                                        @if($product->remark)
                                            Remark: {{ $product->remark }}<br />
                                        @endif
    
                                    </td>
                                    <td align="right">
                                        @if ($user->price_permission)
                                            {{ $product->unit_price }}
                                        @else
                                        -
                                        @endif
                                    </td>
                                    <td>{{ $product->quantity ?? ($product->weight . ' KG') }}</td>
                                    <td align="right">
                                        @if ($user->price_permission)
                                            {{ number_format($product->unit_price * $product->quantity, 2) }}
                                        @else
                                        -
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        @if ($user->price_permission)
                            <tfoot>
                                @if ($user->isCreditCustomer() && $available_credit > 0)
                                    <tr>
                                        <td colspan="4">
                                            <span class="badge bg-success">Credit balance RM {{ number_format($available_credit, 2) }} will be applied automatically</span>
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td>Total</td>
                                    <td colspan="3" align="right"><strong><span id="total-price-value">{{ number_format($total, 2) }}</span></strong></td>
                                </tr>
                                @if ($user->isCreditCustomer() && $available_credit > 0)
                                    <tr>
                                        <td colspan="3">Est. after credit</td>
                                        <td align="right"><strong>RM {{ number_format(max(0, $total - $available_credit), 2) }}</strong></td>
                                    </tr>
                                @endif
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('script')

    <script>
        $(document).ready(function() {
            // Function to show/hide transfer_slip based on payment_method
            function toggleTransferSlip() {
                var paymentMethod = $('#payment_method').val();
                // Show transferSlipGroup only for 'bank-transfer' payment method
                if (paymentMethod === 'bank-transfer') {
                    $('#transferSlipGroup').show().attr('required', true);
                    $('#transfer_slip').attr('required', true);
                } else {
                    $('#transferSlipGroup').hide().removeAttr('required');
                    $('#transfer_slip').removeAttr('required');
                }
            }

            // Call toggleTransferSlip on page load
            toggleTransferSlip();

            // Bind toggleTransferSlip to payment_method change event
            $('#payment_method').change(function() {
                toggleTransferSlip();
            });
        });
    </script>

@endsection
