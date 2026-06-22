@extends('layouts.admin')
@section('title', __('customers.invite_success_title'))
@section('content')

    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="card-title">{{ __('customers.registration_link_ready') }}</h5>
                    <p class="text-muted mb-4">
                        {{ __('customers.invite_success_help', ['type' => strtoupper($customer->customer_type)]) }}
                    </p>

                    <div class="mb-3">
                        <span class="badge bg-{{ $customer->customer_type === 'credit' ? 'info' : 'secondary' }} text-dark me-2">
                            {{ strtoupper($customer->customer_type) }}
                        </span>
                        <span class="badge bg-light text-dark border">{{ $customer->category }}</span>
                    </div>

                    <label class="mb-2">{{ __('customers.registration_link') }}</label>
                    <input type="text" class="form-control mb-3" id="registrationLink" value="{{ $registrationUrl }}" readonly>

                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <button type="button" class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('registrationLink').value)">{{ __('customers.copy_link') }}</button>
                        <a href="{{ route('admin.customers.generate-registration-link', $customer->id) }}" class="btn btn-outline-primary">{{ __('customers.generate_new_link') }}</a>
                        <a href="{{ route('admin.customers.invite') }}" class="btn btn-outline-secondary">{{ __('customers.invite_another') }}</a>
                        <a href="{{ route('admin.customers') }}" class="btn btn-secondary">{{ __('customers.back_to_customers') }}</a>
                    </div>

                    @if ($customer->registration_token_expires_at)
                        <p class="text-muted mb-0">{{ __('customers.link_expires', ['date' => $customer->registration_token_expires_at->format('d M Y')]) }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

@endsection
