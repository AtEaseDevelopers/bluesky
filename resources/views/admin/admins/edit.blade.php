@extends('layouts.admin')
@section('title', 'Edit Type')
@section('content')

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="card-title">Edit Admin</h5>
                    <hr>
                    <form action="{{ route('admin.admins.update', encrypt($admin->id)) }}" method="POST" class="form-wrapper">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <label class="mb-2" for="admin_name">Name</label>
                                    <span class="text-danger"> *</span>
                                    <input type="text" class="form-control @error('admin_name') is-invalid @enderror" name="admin_name" id="admin_name" placeholder="Enter name" value="{{ $admin->name }}">
                                    @error('admin_name')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                <div class="mb-4">
                                    <label class="mb-2" for="admin_username">Username</label>
                                    <span class="text-danger"> *</span>
                                    <input type="text" class="form-control @error('admin_username') is-invalid @enderror" name="admin_username" id="admin_username" placeholder="Enter username" value="{{ $admin->username }}">
                                    @error('admin_username')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                <div class="mb-4">
                                    <label class="mb-2" for="admin_email">Email</label>
                                    <span class="text-danger"> *</span>
                                    <input type="text" class="form-control @error('admin_email') is-invalid @enderror" name="admin_email" id="admin_email" placeholder="Enter email" value="{{ $admin->email }}">
                                    @error('admin_email')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                <div class="mb-4">
                                    <label class="mb-2" for="admin_name">Password</label>
                                    <span class="text-danger"> *</span>
                                    <input type="text" class="form-control @error('admin_password') is-invalid @enderror" name="admin_password" id="admin_password" placeholder="Enter password" value="">
                                    @error('admin_password')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                                <div class="d-flex justify-content-end">
                                    <a href="{{ route('admin.admins.index') }}" class="btn btn-secondary me-2 mb-1">Back</a>
                                    <button type="submit" class="btn btn-primary mb-1">
                                        Save
                                        <div class="spinner-border spinner-border-sm d-none" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
