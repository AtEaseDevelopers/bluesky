@extends('layouts.admin')
@section('title', 'Add Lorry')
@section('content')

    <div class="row">
        <div class="col-md-12">
            <div class="card shadow no-border mb-4">
                <div class="card-body">
                    <h5 class="mb-4">Add Driver / Lorry</h5>
                    <form action="{{ route('admin.lorry.store') }}" method="POST" class="form-wrapper">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="lorry_number">Lorry Number</label>
                                    <span class="text-danger"> *</span>
                                    <input type="text" class="form-control @error('lorry_number') is-invalid @enderror" name="lorry_number" id="lorry_number" placeholder="Enter lorry number" value="{{ old('lorry_number') }}" required>
                                    @error('lorry_number')
                                        <span class="text-danger"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="name">Driver Name</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="name" placeholder="Driver name" value="{{ old('name') }}">
                                    @error('name')
                                        <span class="text-danger"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="phone">Mobile Phone</label>
                                    <input type="text" class="form-control @error('phone') is-invalid @enderror" name="phone" id="phone" placeholder="For driver app login" value="{{ old('phone') }}">
                                    @error('phone')
                                        <span class="text-danger"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="pin">App PIN</label>
                                    <input type="password" class="form-control @error('pin') is-invalid @enderror" name="pin" id="pin" placeholder="4+ digits for driver app" minlength="4">
                                    @error('pin')
                                        <span class="text-danger"><strong>{{ $message }}</strong></span>
                                    @enderror
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
