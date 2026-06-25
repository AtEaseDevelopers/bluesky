@extends('layouts.admin')
@section('title', __('customers.invite'))
@section('content')

    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="card-title">{{ __('customers.invite_title') }}</h5>
                    <p class="text-muted">{{ __('customers.invite_help') }}</p>
                    <hr>

                    <form action="{{ route('admin.customers.invite.store') }}" method="POST" class="form-wrapper">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="customer_type">{{ __('customers.customer_type') }}</label>
                                    <span class="text-danger">*</span>
                                    <select name="customer_type" id="customer_type" class="form-select @error('customer_type') is-invalid @enderror" required>
                                        <option value="">{{ __('customers.choose_customer_type') }}</option>
                                        <option value="cod" {{ old('customer_type') === 'cod' ? 'selected' : '' }}>{{ __('customers.customer_type_cod_option') }}</option>
                                        <option value="credit" {{ old('customer_type') === 'credit' ? 'selected' : '' }}>{{ __('customers.customer_type_credit_option') }}</option>
                                    </select>
                                    @error('customer_type')
                                        <span class="text-danger"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="category">{{ __('customers.category') }}</label>
                                    <span class="text-danger">*</span>
                                    <select name="category" id="category" class="form-select @error('category') is-invalid @enderror" required>
                                        <option value="">{{ __('customers.choose_category') }}</option>
                                        @foreach ($category_list as $category)
                                            <option value="{{ $category->category }}" {{ old('category') === $category->category ? 'selected' : '' }}>
                                                {{ $category->category }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('category')
                                        <span class="text-danger"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                @include('admin.customers.partials.payment-term-field', [
                                    'customerType' => old('customer_type'),
                                    'selectedPaymentTermDays' => old('payment_term_days', 30),
                                ])
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="area_id">{{ __('customers.area') }}</label>
                                    <select name="area_id" id="area_id" class="form-select">
                                        <option value="">{{ __('customers.optional') }}</option>
                                        @foreach ($areas as $area)
                                            <option value="{{ $area->id }}" {{ old('area_id') == $area->id ? 'selected' : '' }}>{{ $area->area_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <label class="mb-2" for="remark">{{ __('customers.internal_remark') }}</label>
                                    <textarea name="remark" id="remark" class="form-control" rows="2" placeholder="{{ __('customers.internal_remark_placeholder') }}">{{ old('remark') }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <a href="{{ route('admin.customers') }}" class="btn btn-secondary">{{ __('ui.back') }}</a>
                            <button type="submit" class="btn btn-primary">{{ __('customers.generate_registration_link') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const customerType = document.getElementById('customer_type');
            const paymentTermWrap = document.getElementById('payment_term_wrap');

            function syncPaymentTermField() {
                if (!customerType || !paymentTermWrap) {
                    return;
                }

                paymentTermWrap.classList.toggle('d-none', customerType.value !== 'credit');
            }

            customerType?.addEventListener('change', syncPaymentTermField);
            syncPaymentTermField();
        });
    </script>
@endsection
