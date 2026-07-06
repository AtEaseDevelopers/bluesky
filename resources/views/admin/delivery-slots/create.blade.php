@extends('layouts.admin')
@section('title', __('delivery_slots.add_slot'))
@section('content')
    <div class="card shadow no-border">
        <div class="card-body">
            <h5 class="card-title">{{ __('delivery_slots.add_slot') }}</h5>
            <p class="text-muted">{{ __('delivery_slots.daily_slots_help') }}</p>
            <hr>
            <form action="{{ route('admin.delivery-slots.store') }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <label>{{ __('delivery_slots.start_time') }} <span class="text-danger">*</span></label>
                        <input type="time" name="time_start" class="form-control" value="{{ old('time_start', '09:00') }}" required>
                    </div>
                    <div class="col-md-4 mb-4">
                        <label>{{ __('delivery_slots.end_time') }} <span class="text-danger">*</span></label>
                        <input type="time" name="time_end" class="form-control" value="{{ old('time_end', '12:00') }}" required>
                    </div>
                    <div class="col-md-4 mb-4">
                        <label>{{ __('delivery_slots.max_orders') }}</label>
                        <input type="number" name="max_orders" class="form-control" min="1" placeholder="{{ __('delivery_slots.max_orders_placeholder') }}">
                        <small class="text-muted">{{ __('delivery_slots.max_orders_help') }}</small>
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
