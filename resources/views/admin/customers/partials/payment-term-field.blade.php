@php
    $selectedTerm = old('payment_term_days', $selectedPaymentTermDays ?? 30);
    $currentType = old('customer_type', $customerType ?? 'cod');
@endphp
<div class="mb-4 {{ $currentType === 'credit' ? '' : 'd-none' }}" id="payment_term_wrap">
    <label class="mb-2" for="payment_term_days">{{ __('customers.payment_term') }}</label>
    <select name="payment_term_days" id="payment_term_days" class="form-select @error('payment_term_days') is-invalid @enderror">
        @foreach (App\User::paymentTermOptions() as $days => $label)
            <option value="{{ $days }}" {{ (int) $selectedTerm === (int) $days ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
    <small class="text-muted d-block">{{ __('customers.payment_term_help') }}</small>
    @error('payment_term_days')
        <span class="text-danger"><strong>{{ $message }}</strong></span>
    @enderror
</div>
