@extends('layouts.admin')
@section('title', __('delivery_slots.add_blackout'))
@section('content')
    <div class="card shadow no-border">
        <div class="card-body">
            <h5 class="card-title">{{ __('delivery_slots.add_blackout') }}</h5>
            <p class="text-muted">{{ __('delivery_slots.blackouts_help') }}</p>
            <hr>
            <form action="{{ route('admin.delivery-blackouts.store') }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <label>{{ __('delivery_slots.start_date') }} <span class="text-danger">*</span></label>
                        <input type="date" name="start_date" class="form-control" value="{{ old('start_date') }}" required>
                    </div>
                    <div class="col-md-4 mb-4">
                        <label>{{ __('delivery_slots.end_date') }} <span class="text-danger">*</span></label>
                        <input type="date" name="end_date" class="form-control" value="{{ old('end_date') }}" required>
                    </div>
                    <div class="col-md-4 mb-4">
                        <label>{{ __('delivery_slots.label') }}</label>
                        <input type="text" name="label" class="form-control" value="{{ old('label') }}" placeholder="{{ __('delivery_slots.label_placeholder') }}">
                    </div>
                    <div class="col-md-4 mb-4">
                        <label class="d-block">{{ __('delivery_slots.enabled') }}</label>
                        <input type="checkbox" name="is_enabled" value="1" checked>
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
