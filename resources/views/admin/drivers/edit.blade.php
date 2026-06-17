@extends('layouts.admin')
@section('title', 'Edit Lorry')
@section('content')

    <div class="row">
        <div class="col-md-12">
            <div class="card shadow no-border mb-4">
                <div class="card-body">
                    <h5 class="mb-4">Edit Lorry</h5>
                    <form action="{{ route('admin.lorry.update', encrypt($driver->id)) }}" method="POST" class="form-wrapper">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="name">Driver Name</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="name" placeholder="Enter driver name" value="{{ old('name', $driver->name) }}">
                                    @error('name')<span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="phone">Phone</label>
                                    <input type="text" class="form-control @error('phone') is-invalid @enderror" name="phone" id="phone" placeholder="Enter phone number" value="{{ old('phone', $driver->phone) }}">
                                    @error('phone')<span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="lorry_number">Lorry Number</label>
                                    <span class="text-danger"> *</span>
                                    <input type="text" class="form-control @error('lorry_number') is-invalid @enderror" name="lorry_number" id="lorry_number" placeholder="Enter driver lorry number" value="{{ old('lorry_number', $driver->lorry_number) }}">
                                    @error('lorry_number')<span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="is_active">Status</label>
                                    <select class="form-control @error('is_active') is-invalid @enderror" name="is_active" id="is_active">
                                        <option value="1" {{ old('is_active', $driver->is_active ? '1' : '0') == '1' ? 'selected' : '' }}>Active</option>
                                        <option value="0" {{ old('is_active', $driver->is_active ? '1' : '0') == '0' ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                    @error('is_active')<span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>@enderror
                                </div>
                            </div>
                        </div>

                        <hr>
                        <h6 class="mb-3">Login Credentials</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="username">Username</label>
                                    <span class="text-danger"> *</span>
                                    <input type="text" class="form-control @error('username') is-invalid @enderror" name="username" id="username" placeholder="Enter login username" value="{{ old('username', $driver->username) }}" autocomplete="off">
                                    @error('username')<span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="password">Password</label>
                                    <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" id="password" placeholder="Leave blank to keep current password" autocomplete="new-password">
                                    @error('password')<span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>@enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-end">
                                    <a href="{{ route('admin.lorry.index') }}" class="btn btn-secondary me-2 mb-1">Back</a>
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
