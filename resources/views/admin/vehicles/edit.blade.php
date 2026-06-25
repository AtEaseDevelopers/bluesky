@extends('layouts.admin')
@section('title', __('drivers.vehicle_edit'))
@section('content')

    <div class="row">
        <div class="col-md-8">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="mb-4">{{ __('drivers.vehicle_edit') }}</h5>
                    <form action="{{ route('admin.vehicles.update', encrypt($vehicle->id)) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="mb-4">
                            <label class="mb-2" for="vehicle_number">{{ __('drivers.vehicle_number') }}</label>
                            <span class="text-danger"> *</span>
                            <input type="text" class="form-control @error('vehicle_number') is-invalid @enderror" name="vehicle_number" id="vehicle_number" value="{{ old('vehicle_number', $vehicle->vehicle_number) }}" required>
                            @error('vehicle_number')<span class="text-danger"><strong>{{ $message }}</strong></span>@enderror
                        </div>
                        <div class="mb-4">
                            <label class="mb-2" for="description">{{ __('drivers.description') }}</label>
                            <input type="text" class="form-control" name="description" id="description" value="{{ old('description', $vehicle->description) }}">
                        </div>
                        <div class="mb-4">
                            <label class="mb-2" for="is_active">{{ __('drivers.status') }}</label>
                            <select class="form-control" name="is_active" id="is_active">
                                <option value="1" {{ $vehicle->is_active ? 'selected' : '' }}>{{ __('drivers.status_labels.active') }}</option>
                                <option value="0" {{ !$vehicle->is_active ? 'selected' : '' }}>{{ __('drivers.status_labels.inactive') }}</option>
                            </select>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('admin.vehicles.index') }}" class="btn btn-secondary">{{ __('ui.back') }}</a>
                            <button type="submit" class="btn btn-primary">{{ __('ui.save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
