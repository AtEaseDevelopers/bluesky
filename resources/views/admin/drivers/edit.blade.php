@extends('layouts.admin')
@section('title', 'Edit Lorry')
@section('content')

    <div class="row">
        <div class="col-md-12">
            <div class="card shadow no-border mb-4">
                <div class="card-body">
                    <h5 class="mb-4">Edit Driver / Lorry</h5>
                    <form action="{{ route('admin.lorry.update', encrypt($driver->id)) }}" method="POST" class="form-wrapper">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="lorry_number">Lorry Number</label>
                                    <span class="text-danger"> *</span>
                                    <input type="text" class="form-control @error('lorry_number') is-invalid @enderror" name="lorry_number" id="lorry_number" value="{{ old('lorry_number', $driver->lorry_number) }}" required>
                                    @error('lorry_number')
                                        <span class="text-danger"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="name">Driver Name</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="name" value="{{ old('name', $driver->name) }}">
                                    @error('name')
                                        <span class="text-danger"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="phone">Mobile Phone</label>
                                    <input type="text" class="form-control @error('phone') is-invalid @enderror" name="phone" id="phone" value="{{ old('phone', $driver->phone) }}">
                                    @error('phone')
                                        <span class="text-danger"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="pin">New App PIN</label>
                                    <input type="password" class="form-control @error('pin') is-invalid @enderror" name="pin" id="pin" placeholder="Leave blank to keep current PIN" minlength="4">
                                    @error('pin')
                                        <span class="text-danger"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $driver->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">Active (can use driver app)</label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <a href="{{ route('admin.lorry.index') }}" class="btn btn-secondary me-2 mb-1">Back</a>
                            <button type="submit" class="btn btn-primary mb-1">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
