@extends('layouts.member')
@section('title', __('ui.profile'))
@section('content')

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow no-border mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-4">{{ __('ui.profile') }}</h5>

                    <form action="{{ route('member.profile.update') }}" method="POST" class="form-wrapper">
                        @csrf
                        <div class="form-group mb-4">
                            <label class="mb-2" for="name">{{ __('customers.customer_name') }}</label>
                            <span class="text-danger"> *</span>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="name"
                                value="{{ old('name', $customer->name) }}" required>
                            @error('name')
                                <span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="form-group mb-4">
                            <label class="mb-2" for="email">{{ __('user.profile.email_address') }}</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" id="email"
                                value="{{ old('email', $customer->email) }}">
                            @error('email')
                                <span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="form-group mb-4">
                            <label class="mb-2">{{ __('customers.category') }}</label>
                            <p class="form-control-plaintext mb-0">{{ $customer->category ?: '—' }}</p>
                            <small class="text-muted">{{ __('user.profile.category_readonly') }}</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="attn_name">{{ __('orders.attn_name') }}</label>
                                    <input type="text" class="form-control @error('attn_name') is-invalid @enderror" name="attn_name" id="attn_name"
                                        value="{{ old('attn_name', $customer->attn_name) }}" placeholder="{{ __('orders.attn_name_placeholder') }}">
                                    @error('attn_name')
                                        <span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="attn_contact">{{ __('orders.attn_contact') }}</label>
                                    <input type="text" class="form-control @error('attn_contact') is-invalid @enderror" name="attn_contact" id="attn_contact"
                                        value="{{ old('attn_contact', $customer->attn_contact) }}" placeholder="{{ __('orders.attn_contact_placeholder') }}">
                                    @error('attn_contact')
                                        <span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        @php
                            $selectedContactMethod = old('contact_method', $customer->contact_method ?? 'whatsapp');
                        @endphp
                        <div class="form-group mb-4">
                            <label class="mb-2 d-block">{{ __('orders.contact_using') }}</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="contact_method" id="contact_method_whatsapp" value="whatsapp"
                                    {{ $selectedContactMethod === 'whatsapp' ? 'checked' : '' }}>
                                <label class="form-check-label" for="contact_method_whatsapp">{{ __('orders.contact_method.whatsapp') }}</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="contact_method" id="contact_method_wechat" value="wechat"
                                    {{ $selectedContactMethod === 'wechat' ? 'checked' : '' }}>
                                <label class="form-check-label" for="contact_method_wechat">{{ __('orders.contact_method.wechat') }}</label>
                            </div>
                            @error('contact_method')
                                <span class="text-danger d-block" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="form-group mb-4" id="wechatIdGroup" style="{{ $selectedContactMethod === 'wechat' ? '' : 'display:none;' }}">
                            <label class="mb-2" for="wechat_id">{{ __('orders.wechat_id') }}</label>
                            <span class="text-danger wechat-required-mark" style="{{ $selectedContactMethod === 'wechat' ? '' : 'display:none;' }}"> *</span>
                            <input type="text" class="form-control @error('wechat_id') is-invalid @enderror" name="wechat_id" id="wechat_id"
                                value="{{ old('wechat_id', $customer->wechat_id) }}" placeholder="{{ __('orders.wechat_id_placeholder') }}">
                            @error('wechat_id')
                                <span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="form-group mb-4">
                            <label class="mb-2" for="billing_address">{{ __('customers.billing_address') }}</label>
                            <span class="text-danger"> *</span>
                            <textarea class="form-control @error('billing_address') is-invalid @enderror" name="billing_address" id="billing_address"
                                rows="3" placeholder="{{ __('customers.enter_billing_address') }}" required>{{ old('billing_address', $customer->billing_address) }}</textarea>
                            @error('billing_address')
                                <span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="form-group mb-4">
                            <label class="mb-2" for="shipping_address">{{ __('customers.shipping_address') }}</label>
                            <textarea class="form-control @error('shipping_address') is-invalid @enderror" name="shipping_address" id="shipping_address"
                                rows="3" placeholder="{{ __('customers.enter_shipping_address') }}">{{ old('shipping_address', $customer->shipping_address) }}</textarea>
                            @error('shipping_address')
                                <span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="form-group mb-4">
                            <label class="mb-2">{{ __('orders.payment_method') }}</label>
                            @if (count($paymentMethods))
                                <ul class="mb-0 ps-3">
                                    @foreach ($paymentMethods as $paymentMethod)
                                        @php
                                            $paymentLabelKey = 'user.payment_method.' . $paymentMethod;
                                            $paymentLabel = __($paymentLabelKey);
                                        @endphp
                                        <li>{{ $paymentLabel !== $paymentLabelKey ? $paymentLabel : $paymentMethod }}</li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="form-control-plaintext mb-0 text-muted">—</p>
                            @endif
                            <small class="text-muted">{{ __('user.profile.payment_methods_readonly') }}</small>
                        </div>

                        <button type="submit" class="btn btn-primary px-4">
                            {{ __('user.profile.save_details') }}
                            <div class="spinner-border spinner-border-sm d-none" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="card-title mb-4">{{ __('ui.change_password') }}</h5>
                    <form action="{{ route('member.update.password') }}" method="POST" class="form-wrapper">
                        @csrf
                        <div class="form-group mb-4">
                            <label class="mb-2" for="password">{{ __('ui.auth.password') }}</label>
                            <span class="text-danger"> *</span>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" id="password">
                            @error('password')
                                <span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="form-group mb-4">
                            <label class="mb-2" for="password_confirmation">{{ __('user.profile.confirm_password') }}</label>
                            <span class="text-danger"> *</span>
                            <input type="password" class="form-control @error('password_confirmation') is-invalid @enderror"
                                name="password_confirmation" id="password_confirmation">
                            @error('password_confirmation')
                                <span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary px-4">
                            {{ __('ui.change_password') }}
                            <div class="spinner-border spinner-border-sm d-none" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('script')
    <script>
        $(document).ready(function () {
            function toggleWechatIdField() {
                var isWechat = $('#contact_method_wechat').is(':checked');
                $('#wechatIdGroup').toggle(isWechat);
                $('.wechat-required-mark').toggle(isWechat);
            }

            $('input[name="contact_method"]').on('change', toggleWechatIdField);
            toggleWechatIdField();
        });
    </script>
@endsection
