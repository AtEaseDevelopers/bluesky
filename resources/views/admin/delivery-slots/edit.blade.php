@extends('layouts.admin')
@section('title', 'Edit Delivery Slot')
@section('content')
    <div class="card shadow no-border">
        <div class="card-body">
            <h5 class="card-title">Edit Delivery Slot</h5>
            <hr>
            <form action="{{ route('admin.delivery-slots.update', encrypt($slot->id)) }}" method="POST">
                @csrf @method('PUT')
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <label>Date <span class="text-danger">*</span></label>
                        <input type="date" name="slot_date" class="form-control" value="{{ old('slot_date', $slot->slot_date->format('Y-m-d')) }}" required>
                    </div>
                    <div class="col-md-4 mb-4">
                        <label>Start Time <span class="text-danger">*</span></label>
                        <input type="time" name="time_start" class="form-control" value="{{ old('time_start', date('H:i', strtotime($slot->time_start))) }}" required>
                    </div>
                    <div class="col-md-4 mb-4">
                        <label>End Time <span class="text-danger">*</span></label>
                        <input type="time" name="time_end" class="form-control" value="{{ old('time_end', date('H:i', strtotime($slot->time_end))) }}" required>
                    </div>
                    <div class="col-md-4 mb-4">
                        <label>Max Orders</label>
                        <input type="number" name="max_orders" class="form-control" min="1" value="{{ old('max_orders', $slot->max_orders) }}">
                    </div>
                    <div class="col-md-4 mb-4">
                        <label class="d-block">Enabled</label>
                        <input type="checkbox" name="is_enabled" value="1" {{ $slot->is_enabled ? 'checked' : '' }}>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.delivery-slots.index') }}" class="btn btn-secondary">Back</a>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
@endsection
