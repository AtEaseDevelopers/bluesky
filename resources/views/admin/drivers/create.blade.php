@extends('layouts.admin')
@section('title', 'Add New Lorry')
@section('content')

    <div class="row">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="mb-4">Add New Lorry</h5>
                    <form action="{{ route('admin.lorry.store') }}" method="POST" class="form-wrapper">
                        @csrf
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <label class="mb-2" for="remark">Lorry Number</label>
                                    <span class="text-danger"> *</span>
                                    <input type="text" class="form-control @error('lorry_number') is-invalid @enderror" name="lorry_number" id="lorry_number" placeholder="Enter lorry number" value="{{ old('lorry_number') }}">
                                    @error('lorry_number')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
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
