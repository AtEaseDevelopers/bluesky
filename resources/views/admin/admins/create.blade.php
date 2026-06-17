@extends('layouts.admin')
@section('title', 'Add Admin User')
@section('content')

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="card-title">Add Admin User</h5>
                    <hr>
                    <form action="{{ route('admin.admins.store') }}" method="POST" class="form-wrapper">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="admin_name">Name</label>
                                    <span class="text-danger"> *</span>
                                    <input type="text" class="form-control @error('admin_name') is-invalid @enderror" name="admin_name" id="admin_name" placeholder="Enter name" value="{{ old('admin_name') }}">
                                    @error('admin_name')
                                        <span class="text-danger"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="admin_username">Username</label>
                                    <span class="text-danger"> *</span>
                                    <input type="text" class="form-control @error('admin_username') is-invalid @enderror" name="admin_username" id="admin_username" placeholder="Enter username" value="{{ old('admin_username') }}">
                                    @error('admin_username')
                                        <span class="text-danger"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="admin_email">Email</label>
                                    <span class="text-danger"> *</span>
                                    <input type="email" class="form-control @error('admin_email') is-invalid @enderror" name="admin_email" id="admin_email" placeholder="Enter email" value="{{ old('admin_email') }}">
                                    @error('admin_email')
                                        <span class="text-danger"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="admin_password">Password</label>
                                    <span class="text-danger"> *</span>
                                    <input type="password" class="form-control @error('admin_password') is-invalid @enderror" name="admin_password" id="admin_password" placeholder="Enter password">
                                    @error('admin_password')
                                        <span class="text-danger"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="admin_role">Role</label>
                                    <span class="text-danger"> *</span>
                                    <select name="admin_role" id="admin_role" class="form-select @error('admin_role') is-invalid @enderror" required>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->slug }}" {{ old('admin_role', 'admin') === $role->slug ? 'selected' : '' }}>{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('admin_role')
                                        <span class="text-danger"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="admin_status">Status</label>
                                    <span class="text-danger"> *</span>
                                    <select name="admin_status" id="admin_status" class="form-select @error('admin_status') is-invalid @enderror" required>
                                        <option value="active" {{ old('admin_status', 'active') === 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="inactive" {{ old('admin_status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                    @error('admin_status')
                                        <span class="text-danger"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <a href="{{ route('admin.admins.index') }}" class="btn btn-secondary me-2 mb-1">Back</a>
                            <button type="submit" class="btn btn-primary mb-1">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
