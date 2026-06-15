@extends('layouts.admin')
@section('title', 'Edit Area')
@section('content')

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="card-title">Edit Area</h5>
                    <hr>
                    <form action="{{ route('admin.areas.update', encrypt($area->id)) }}" method="POST" class="form-wrapper">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <label class="mb-2" for="remark">Area Name</label>
                                    <span class="text-danger"> *</span>
                                    <input type="text" class="form-control @error('area_name') is-invalid @enderror" name="area_name" id="area_name" placeholder="Enter area name" value="{{ $area->area_name }}">
                                    @error('area_name')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                                <div class="d-flex justify-content-end">
                                    <a href="{{ route('admin.areas.index') }}" class="btn btn-secondary me-2 mb-1">Back</a>
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
