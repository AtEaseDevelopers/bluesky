@extends('driver.layouts.app')
@section('title', __('driver_portal.vehicle.title'))
@section('content')

    <div class="mb-3">
        <h2 class="display-font mb-0" style="font-size:1.6rem;">{{ __('driver_portal.vehicle.title') }}</h2>
        <div class="text-muted-ink">{{ __('driver_portal.vehicle.subtitle') }}</div>
    </div>

    <div class="card driver-card mb-3">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <div class="detail-label mb-0">{{ __('driver_portal.vehicle.current') }}</div>
                <div class="text-muted-ink">{{ __('driver_portal.vehicle.current_help') }}</div>
            </div>
            <span class="fw-bold" style="font-size:1.5rem;">
                <i class="fa fa-truck me-1" style="color: var(--teal);"></i>{{ $current ?: '—' }}
            </span>
        </div>
    </div>

    @if ($lorries->isEmpty())
        <div class="card driver-card">
            <div class="card-body text-center py-5">
                <i class="fa fa-truck fa-3x mb-3" style="color: var(--teal);"></i>
                <p class="mb-0 text-muted-ink">{{ __('driver_portal.vehicle.empty') }}</p>
            </div>
        </div>
    @else
        <div class="card driver-card">
            <div class="card-body">
                <form method="POST" action="{{ route('driver.vehicle.update') }}">
                    @csrf
                    <label for="lorry_number" class="form-label">{{ __('driver_portal.vehicle.select') }}</label>
                    <select name="lorry_number" id="lorry_number"
                            class="form-select form-select-lg mb-3 @error('lorry_number') is-invalid @enderror" required>
                        <option value="" disabled {{ $current ? '' : 'selected' }}>{{ __('driver_portal.vehicle.choose') }}</option>
                        @foreach ($lorries as $lorry)
                            @php $taken = $assigned->contains($lorry); @endphp
                            <option value="{{ $lorry }}" {{ $current === $lorry ? 'selected' : '' }} {{ $taken ? 'disabled' : '' }}>
                                {{ $lorry }}@if ($taken) {{ __('driver_portal.vehicle.in_use') }} @endif
                            </option>
                        @endforeach
                    </select>
                    @error('lorry_number')
                        <div class="invalid-feedback d-block mb-2">{{ $message }}</div>
                    @enderror
                    <button type="submit" class="btn btn-brand btn-lg w-100">
                        <i class="fa fa-check me-1"></i> {{ __('driver_portal.vehicle.save') }}
                    </button>
                </form>
            </div>
        </div>
    @endif

@endsection
