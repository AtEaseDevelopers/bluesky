@extends('layouts.app')
@section('title', 'Customer Portal Registration')
@section('content')

    <div class="row my-5">
        <div class="col-md-6 mx-auto">
            <div class="text-center">
                <img src="{{ asset('assets/images/logo.png') }}" alt="{{ config('app.name') }}" class="mb-3" style="width: 120px;">
            </div>
            <div class="card no-border shadow">
                <div class="card-body">
                    <h4 class="mb-2">Create Your Customer Account</h4>
                    <p class="text-muted mb-3">You have been invited to register for the {{ config('app.name') }} customer portal.</p>

                    <div class="mb-4">
                        <span class="badge bg-{{ $customer->customer_type === 'credit' ? 'info' : 'secondary' }} text-dark me-2">
                            {{ $customer->customer_type === 'credit' ? 'Credit Customer' : 'COD Customer' }}
                        </span>
                        @if ($customer->category)
                            <span class="badge bg-light text-dark border">{{ ucfirst($customer->category) }}</span>
                        @endif
                    </div>

                    <form action="{{ url('/register/' . $token) }}" method="POST" class="form-wrapper">
                        @csrf
                        <div class="form-group mb-4">
                            <label class="mb-2" for="name">Business / Customer Name</label>
                            <span class="text-danger">*</span>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="name" value="{{ old('name') }}" required>
                            @error('name')
                                <span class="text-danger"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="attn_name">Contact Name</label>
                                    <input type="text" class="form-control" name="attn_name" id="attn_name" value="{{ old('attn_name') }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="attn_contact">Contact Phone</label>
                                    <span class="text-danger">*</span>
                                    <input type="text" class="form-control @error('attn_contact') is-invalid @enderror" name="attn_contact" id="attn_contact" value="{{ old('attn_contact') }}" required>
                                    @error('attn_contact')
                                        <span class="text-danger"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-4">
                            <label class="mb-2" for="email">Login Email</label>
                            <span class="text-danger">*</span>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" id="email" value="{{ old('email') }}" required>
                            @error('email')
                                <span class="text-danger"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="form-group mb-4">
                            <label class="mb-2" for="billing_address">Billing Address</label>
                            <span class="text-danger">*</span>
                            <textarea class="form-control @error('billing_address') is-invalid @enderror" name="billing_address" id="billing_address" rows="2" required>{{ old('billing_address') }}</textarea>
                            @error('billing_address')
                                <span class="text-danger"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="form-group mb-4">
                            <label class="mb-2" for="shipping_address">Shipping Address</label>
                            <textarea class="form-control" name="shipping_address" id="shipping_address" rows="2">{{ old('shipping_address') }}</textarea>
                        </div>
                        <div class="form-group mb-4">
                            <label class="mb-2" for="password">Password</label>
                            <span class="text-danger">*</span>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" id="password" required>
                            @error('password')
                                <span class="text-danger"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="form-group mb-4">
                            <label class="mb-2" for="password_confirmation">Confirm Password</label>
                            <span class="text-danger">*</span>
                            <input type="password" class="form-control" name="password_confirmation" id="password_confirmation" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Register & Start Ordering</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
