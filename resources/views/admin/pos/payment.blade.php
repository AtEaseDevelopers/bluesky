@extends('layouts.pos')
@section('title', __('customers.pos.make_payment'))
@section('content')

    <div class="row mb-5">
        <div class="col-lg-8">
            <div class="card shadow no-border">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                        <h5 class="mb-0">{{ __('customers.pos.make_payment') }} — #{{ $order->id }}</h5>
                        <span class="badge bg-secondary">{{ __('customers.pos.balance_due') }} RM {{ number_format($balanceDue, 2) }}</span>
                    </div>

                    <form action="{{ route('admin.pos.payment.store', $order->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="row g-3 align-items-end payment-line">
                            <div class="col-md-4">
                                <label class="mb-2">{{ __('orders.method') }}</label>
                                <select name="payments[0][payment_method]" class="form-select" required>
                                    @foreach ($paymentMethods as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="mb-2">{{ __('orders.amount_rm') }}</label>
                                <input type="number" step="0.01" min="0.01" name="payments[0][amount]" class="form-control"
                                    value="{{ number_format($balanceDue, 2, '.', '') }}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="mb-2">{{ __('orders.proof') }}</label>
                                <input type="file" name="payments[0][payment_proof]" class="form-control" accept="image/*,.pdf">
                            </div>
                            <div class="col-md-2">
                                <label class="mb-2">{{ __('orders.notes') }}</label>
                                <input type="text" name="payments[0][notes]" class="form-control" placeholder="{{ __('orders.optional') }}">
                            </div>
                        </div>

                        @if ($requiresExactPayment)
                            <p class="text-muted small mt-3 mb-0">{{ __('customers.pos.exact_payment_required') }}</p>
                        @endif

                        <div class="d-flex justify-content-between flex-wrap gap-2 mt-4">
                            <a href="{{ route('admin.pos.index') }}" class="btn btn-secondary">{{ __('customers.pos.skip_payment') }}</a>
                            <button type="submit" class="btn btn-success">{{ __('customers.pos.complete_payment') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
