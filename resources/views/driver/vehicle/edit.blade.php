@extends('driver.layouts.app')
@section('title', 'My Vehicle')
@section('content')

    <div class="mb-3">
        <h2 class="display-font mb-0" style="font-size:1.6rem;">My Vehicle</h2>
        <div class="text-muted-ink">Choose the lorry you are driving. The office sees this on your deliveries.</div>
    </div>

    <div class="card driver-card mb-3">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <div class="detail-label mb-0">Current Vehicle</div>
                <div class="text-muted-ink">Your active lorry</div>
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
                <p class="mb-0 text-muted-ink">No vehicles are registered yet. Please contact the office.</p>
            </div>
        </div>
    @else
        <div class="card driver-card">
            <div class="card-body">
                <form method="POST" action="{{ route('driver.vehicle.update') }}">
                    @csrf
                    <label for="lorry_number" class="form-label">Select vehicle</label>
                    <select name="lorry_number" id="lorry_number"
                            class="form-select form-select-lg mb-3 @error('lorry_number') is-invalid @enderror" required>
                        <option value="" disabled {{ $current ? '' : 'selected' }}>— Choose a vehicle —</option>
                        @foreach ($lorries as $lorry)
                            @php $taken = $assigned->contains($lorry); @endphp
                            <option value="{{ $lorry }}" {{ $current === $lorry ? 'selected' : '' }} {{ $taken ? 'disabled' : '' }}>
                                {{ $lorry }}@if ($taken) — in use @endif
                            </option>
                        @endforeach
                    </select>
                    @error('lorry_number')
                        <div class="invalid-feedback d-block mb-2">{{ $message }}</div>
                    @enderror
                    <button type="submit" class="btn btn-brand btn-lg w-100">
                        <i class="fa fa-check me-1"></i> Save Vehicle
                    </button>
                </form>
            </div>
        </div>
    @endif

@endsection
