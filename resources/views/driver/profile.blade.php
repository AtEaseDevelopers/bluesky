@extends('driver.layouts.app')
@section('title', __('driver_portal.profile.title'))
@section('content')

    <div class="mb-3">
        <h2 class="display-font mb-0" style="font-size:1.6rem;">{{ __('driver_portal.profile.title') }}</h2>
        <div class="text-muted-ink">{{ __('driver_portal.profile.subtitle') }}</div>
    </div>

    <div class="card driver-card">
        <div class="card-body">
            <form method="POST" action="{{ route('driver.profile.update-password') }}">
                @csrf
                <div class="mb-3">
                    <label for="old_password" class="form-label">{{ __('driver_portal.profile.old_password') }}</label>
                    <input type="password"
                           name="old_password"
                           id="old_password"
                           class="form-control @error('old_password') is-invalid @enderror"
                           autocomplete="current-password"
                           required>
                    @error('old_password')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">{{ __('driver_portal.profile.new_password') }}</label>
                    <input type="password"
                           name="password"
                           id="password"
                           class="form-control @error('password') is-invalid @enderror"
                           autocomplete="new-password"
                           required>
                    @error('password')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-4">
                    <label for="password_confirmation" class="form-label">{{ __('driver_portal.profile.confirm_password') }}</label>
                    <input type="password"
                           name="password_confirmation"
                           id="password_confirmation"
                           class="form-control"
                           autocomplete="new-password"
                           required>
                </div>
                <button type="submit" class="btn btn-brand btn-lg w-100">
                    <i class="fa fa-key me-1"></i> {{ __('driver_portal.profile.save') }}
                </button>
            </form>
        </div>
    </div>

@endsection
