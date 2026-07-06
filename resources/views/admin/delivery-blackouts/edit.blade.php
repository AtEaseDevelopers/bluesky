@extends('layouts.admin')
@section('title', __('delivery_slots.edit_blackout'))
@section('content')
    <div class="card shadow no-border">
        <div class="card-body">
            <h5 class="card-title">{{ __('delivery_slots.edit_blackout') }}</h5>
            <hr>
            <form action="{{ route('admin.delivery-blackouts.update', encrypt($blackout->id)) }}" method="POST">
                @csrf @method('PUT')
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <label>{{ __('delivery_slots.start_date') }} <span class="text-danger">*</span></label>
                        <input type="date" name="start_date" class="form-control" value="{{ old('start_date', $blackout->start_date->format('Y-m-d')) }}" required>
                    </div>
                    <div class="col-md-4 mb-4">
                        <label>{{ __('delivery_slots.end_date') }} <span class="text-danger">*</span></label>
                        <input type="date" name="end_date" class="form-control" value="{{ old('end_date', $blackout->end_date->format('Y-m-d')) }}" required>
                    </div>
                    <div class="col-md-4 mb-4">
                        <label>{{ __('delivery_slots.label') }}</label>
                        <input type="text" name="label" class="form-control" value="{{ old('label', $blackout->label) }}" placeholder="{{ __('delivery_slots.label_placeholder') }}">
                    </div>
                    <div class="col-md-4 mb-4">
                        <label class="d-block">{{ __('delivery_slots.enabled') }}</label>
                        <input type="checkbox" name="is_enabled" value="1" {{ $blackout->is_enabled ? 'checked' : '' }}>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.delivery-slots.index') }}" class="btn btn-secondary">{{ __('ui.back') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('ui.save') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
