@extends('layouts.member')
@section('title', 'Profile')
@section('css')

    <link rel="stylesheet" href="{{ asset('assets/css/select2.min.css') }}" />

@endsection
@section('content')

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow no-border">
                <div class="card-body">
                    <div class="mb-4">
                        <h5 class="card-title">Profile</h5>
                    </div>
                    <div class="form-group mb-4">
                        <label class="mb-2" for="name">Name</label>
                        <span class="text-danger"> *</span>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="name" value="{{ $customer->name }}">
                        @error('name')
                            <span class="text-danger" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                    <div class="form-group mb-4">
                        <label class="mb-2" for="email">Email Address</label>
                        <span class="text-danger"> *</span>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" id="email" value="{{ $customer->email }}">
                        @error('email')
                            <span class="text-danger" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                    <div class="form-group mb-4">
                        <label class="mb-2" for="category">Category</label>
                        <span class="text-danger"> *</span>
                        <input type="text" class="form-control @error('category') is-invalid @enderror" name="category" id="category" value="{{ $customer->category }}">
                        @error('category')
                            <span class="text-danger" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                    <div class="form-group mb-4">
                        <label class="mb-2" for="">Attn.</label>
                        <p>{{ $customer->attn_name }}, {{ $customer->attn_contact }}</p>
                    </div>
                    <div class="form-group mb-4">
                        <label class="mb-2" for="">Billing Address</label>
                        <p>{{ $customer->billing_address }}, {{ $customer->billing_postcode }}, {{ $customer->billing_state }}</p>
                    </div>
                    <div class="form-group mb-4">
                        <label class="mb-2" for="">Shipping Address</label>
                        <p>{{ $customer->shipping_address }}, {{ $customer->shipping_postcode }}, {{ $customer->shipping_state }}</p>
                    </div>
                    <div class="form-group mb-4">
                        <label class="mb-2" for="">Payment Method</label>
                        <p>
                            @foreach ($customer->payment_method as $payment_method)
                                {{ $payment_method ? __('user.payment_method.'.$payment_method) : '' }}<br />
                            @endforeach
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow no-border">
                <div class="card-body">
                    <div class="mb-4">
                        <h5 class="card-title">Change Password</h5>
                    </div>
                    <form action="{{ route('member.update.password') }}" method="POST" class="form-wrapper">
                        @csrf
                        <div class="form-group mb-4">
                            <label class="mb-2" for="password">Password</label>
                            <span class="text-danger"> *</span>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" id="password">
                            @error('password')
                                <span class="text-danger" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="form-group mb-4">
                            <label class="mb-2" for="password_confirmation">Confirm Password</label>
                            <span class="text-danger"> *</span>
                            <input type="password" class="form-control @error('password_confirmation') is-invalid @enderror" name="password_confirmation" id="password_confirmation">
                            @error('password_confirmation')
                                <span class="text-danger" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="form-group mb-3">
                            <button type="submit" class="btn btn-primary px-4">
                                Change Password
                                <div class="spinner-border spinner-border-sm d-none" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('script')

    <script src="{{ asset('assets/js/select2.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            $('#customerPaymentMethod').select2();
        });
    </script>

@endsection
