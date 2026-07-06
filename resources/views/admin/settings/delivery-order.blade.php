@extends('layouts.admin')
@section('title', __('settings.do_settings'))
@section('content')
    <div class="card shadow no-border">
        <div class="card-body">
            <h5 class="card-title">{{ __('settings.do_settings') }}</h5>
            <p class="text-muted">{{ __('settings.do_settings_help') }}</p>
            <hr>
            <form action="{{ route('admin.settings.delivery-order.update') }}" method="POST">
                @csrf
                @method('PUT')
                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" name="do_show_prices" id="do_show_prices" value="1"
                        {{ $showPrices ? 'checked' : '' }}>
                    <label class="form-check-label" for="do_show_prices">{{ __('settings.do_show_prices') }}</label>
                </div>
                <p class="text-muted small mb-4">{{ __('settings.do_show_prices_help') }}</p>
                <div class="d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-primary">{{ __('settings.save_settings') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
