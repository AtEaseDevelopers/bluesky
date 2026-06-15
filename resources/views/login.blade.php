@extends('layouts.app')
@section('title', 'Login')
@section('content')

    <div class="row my-5">
        <div class="col-md-4 mx-auto">
            <div class="text-center">
                <img src="{{ asset('assets/images/logo.png') }}" alt="{{ env('APP_NAME') }}" class="mb-3" style="width: 120px;">
            </div>
            <div class="card no-border shadow">
                <div class="card-body">
                    <div class="mb-5">
                        <h3>{{ env('APP_NAME') }}</h3>
                        <p>Sign-in to your user account.</p>
                        @if (session('error'))
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <strong>Warning!</strong> {{ session('error') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif
                    </div>
                    <form action="{{ route('login.submit') }}" method="POST" class="form-wrapper">
                        @csrf
                        <div class="form-group mb-4">
                            <label class="mb-2" for="email">Email</label>
                            <span class="text-danger"> *</span>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" autofocus name="email" id="email" value="{{ old('email')? : '' }}" placeholder="Enter your email">
                            @error ('email')
                                <span class="text-danger" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="form-group mb-4">
                            <label class="mb-2" for="password">Password</label>
                            <span class="text-danger"> *</span>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" id="password" placeholder="Enter your password">
                            @error ('password')
                                <span class="text-danger" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="form-group mb-3">
                            <button type="submit" class="btn btn-primary w-100">
                                Login
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