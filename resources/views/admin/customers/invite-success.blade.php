@extends('layouts.admin')
@section('title', 'Registration Link Created')
@section('content')

    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="card-title">Registration Link Ready</h5>
                    <p class="text-muted mb-4">
                        Send this link to your new customer. They will register their own portal account with the
                        <strong>{{ strtoupper($customer->customer_type) }}</strong> customer type you selected.
                    </p>

                    <div class="mb-3">
                        <span class="badge bg-{{ $customer->customer_type === 'credit' ? 'info' : 'secondary' }} text-dark me-2">
                            {{ strtoupper($customer->customer_type) }}
                        </span>
                        <span class="badge bg-light text-dark border">{{ $customer->category }}</span>
                    </div>

                    <label class="mb-2">Registration Link</label>
                    <input type="text" class="form-control mb-3" id="registrationLink" value="{{ $registrationUrl }}" readonly>

                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <button type="button" class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('registrationLink').value)">Copy Link</button>
                        <a href="{{ route('admin.customers.generate-registration-link', $customer->id) }}" class="btn btn-outline-primary">Generate New Link</a>
                        <a href="{{ route('admin.customers.invite') }}" class="btn btn-outline-secondary">Invite Another</a>
                        <a href="{{ route('admin.customers') }}" class="btn btn-secondary">Back to Customers</a>
                    </div>

                    @if ($customer->registration_token_expires_at)
                        <p class="text-muted mb-0">Link expires {{ $customer->registration_token_expires_at->format('d M Y') }}.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

@endsection
