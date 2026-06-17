@extends('driver.layouts.app')
@section('title', 'Driver Login')
@section('css')
    <style>
        .login-hero {
            background: linear-gradient(135deg, var(--deep) 0%, var(--teal) 100%);
            border-radius: 1.25rem;
            color: #fff;
            padding: 2.25rem 1.75rem;
            box-shadow: 0 12px 30px rgba(2, 62, 125, .3);
            position: relative;
            overflow: hidden;
        }
        .login-hero::after {
            content: ""; position: absolute; right: -40px; top: -40px;
            width: 160px; height: 160px; border-radius: 50%;
            background: rgba(255,255,255,.08);
        }
        .login-hero h1 { font-size: 1.9rem; margin-bottom: .35rem; }
        .login-hero p { opacity: .9; margin: 0; }
    </style>
@endsection
@section('content')

    <div class="row">
        <div class="col-12 col-md-8 col-lg-6 mx-auto">
            <div class="login-hero mb-4 mt-2">
                <div class="d-flex align-items-center gap-2 mb-3" style="font-weight:700; letter-spacing:.05em; text-transform:uppercase; font-size:.8rem; opacity:.85;">
                    <i class="fa fa-truck"></i> Bluesky Live Seafood
                </div>
                <h1 class="display-font">Driver Portal</h1>
                <p>Sign in to view your delivery orders and record payments.</p>
            </div>

            <div class="card driver-card">
                <div class="card-body p-4">
                    <form action="{{ route('driver.login.submit') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="username">Username</label>
                            <input type="text" class="form-control form-control-lg @error('username') is-invalid @enderror"
                                name="username" id="username" value="{{ old('username') }}" autofocus
                                placeholder="Enter your username">
                            @error('username')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">Password</label>
                            <input type="password" class="form-control form-control-lg @error('password') is-invalid @enderror"
                                name="password" id="password" placeholder="Enter your password">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-brand btn-lg w-100 mt-2">
                            <i class="fa fa-sign-in me-1"></i> Login
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
