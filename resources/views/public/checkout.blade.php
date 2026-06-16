@extends('layouts.public')
@section('title', 'Checkout')
@section('content')
    <div class="row mb-4">
        <div class="col-md-8 mx-auto">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">Checkout</h4>
                <a href="{{ route('public.order.cart', $link->token) }}" class="btn btn-outline-secondary btn-sm">Back to Cart</a>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <p class="text-muted">Order subtotal: <strong>RM {{ number_format($subtotal, 2) }}</strong> · COD only</p>
                    <hr>

                    <form action="{{ route('public.order.store', $link->token) }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Name <span class="text-danger">*</span></label>
                                <input type="text" name="walk_in_name" class="form-control" value="{{ old('walk_in_name') }}" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Phone <span class="text-danger">*</span></label>
                                <input type="text" name="walk_in_phone" class="form-control" value="{{ old('walk_in_phone') }}" required>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label>Delivery Address <span class="text-danger">*</span></label>
                                <textarea name="shipping_address" class="form-control" rows="2" required>{{ old('shipping_address') }}</textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Delivery Slot <span class="text-danger">*</span></label>
                                @if ($deliverySlots->isEmpty())
                                    <div class="alert alert-warning mb-0">No delivery slots available.</div>
                                @else
                                    <select name="delivery_slot_id" class="form-select" required>
                                        <option value="">Select slot</option>
                                        @foreach ($deliverySlots as $slot)
                                            <option value="{{ $slot->id }}" {{ old('delivery_slot_id') == $slot->id ? 'selected' : '' }}>
                                                {{ $slot->slot_date->format('d M Y') }} — {{ $slot->time_label }}
                                            </option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary mt-2" {{ $deliverySlots->isEmpty() ? 'disabled' : '' }}>
                            Submit Order (COD)
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
