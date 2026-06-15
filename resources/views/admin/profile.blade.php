@extends('layouts.admin')
@section('title', 'Profile')
@section('content')

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-transparent">
                    <h5 class="card-title fw-bold my-2">Edit Profile</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.profile-update') }}" method="POST" class="form-wrapper">
                        @csrf
                        <input type="hidden" name="type" value="profile">
                        <div class="form-group mb-4">
                            <label class="mb-1">Name</label>
                            <span class="text-danger"> *</span>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ $user->name }}" required>
                            @error('name')
                                <span class="invalid-feedback d-block" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="form-group mb-4">
                            <label class="mb-1">Username</label>
                            <span class="text-danger"> * unique username</span>
                            <input type="text" class="form-control @error('username') is-invalid @enderror" name="username" id="username" value="{{ $user->username }}" required>
                            @error('username')
                                <span class="invalid-feedback d-block" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="form-group mb-4">
                            <label class="mb-1">Email Address</label>
                            <span class="text-danger"> * unique email address</span>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" id="email" value="{{ $user->email }}" required>
                            @error('email')
                                <span class="invalid-feedback d-block" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                Submit
                                <div class="spinner-border d-none spinner-border-sm mx-2" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-transparent">
                    <h5 class="card-title fw-bold my-2">Change Password</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.profile-update') }}" method="POST" class="form-wrapper">
                        @csrf
                        <input type="hidden" name="type" value="password">
                        <div class="form-group mb-4">
                            <label class="mb-1">Old Password</label>
                            <span class="text-danger"> *</span>
                            <input type="password" class="form-control @error('old_password') is-invalid @enderror" name="old_password" id="old_password" required>
                            @error('old_password')
                                <span class="invalid-feedback d-block" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="form-group mb-4">
                            <label class="mb-1">Password</label>
                            <span class="text-danger"> *</span>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" id="password" required>
                            @error('password')
                                <span class="invalid-feedback d-block" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="form-group mb-4">
                            <label class="mb-1">Confirm Password</label>
                            <span class="text-danger"> *</span>
                            <input type="password" class="form-control @error('password_confirmation') is-invalid @enderror" name="password_confirmation" id="password_confirmation" required>
                            @error('password_confirmation')
                                <span class="invalid-feedback d-block" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                Submit
                                <div class="spinner-border d-none spinner-border-sm mx-2" role="status">
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