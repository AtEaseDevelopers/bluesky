@extends('layouts.member')
@section('title', $pageTitle)
@section('content')

    <div class="row">
        <div class="col-lg-4 col-xl-3 mb-4">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="card-title mb-3">Terms and Policies</h5>
                    <div class="list-group list-group-flush">
                        @foreach ($pages as $slug => $label)
                            <a href="{{ route('member.policies.show', $slug) }}"
                                class="list-group-item list-group-item-action {{ $page === $slug ? 'active' : '' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8 col-xl-9">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h4 class="mb-4">{{ $pageTitle }}</h4>

                    @if ($page === 'contact-us')
                        <div class="row g-4">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2">Company Name</h6>
                                <p class="mb-0">{{ $company['name'] ?: config('app.name') }}</p>
                                @if (!empty($company['registration_no']))
                                    <small class="text-muted">({{ $company['registration_no'] }})</small>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2">Contact Number</h6>
                                <p class="mb-0">
                                    @if (!empty($company['phone']))
                                        <a href="tel:{{ $company['phone'] }}">{{ $company['phone'] }}</a>
                                    @else
                                        —
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2">Email Address</h6>
                                <p class="mb-0">
                                    @if (!empty($company['email']))
                                        <a href="mailto:{{ $company['email'] }}">{{ $company['email'] }}</a>
                                    @else
                                        —
                                    @endif
                                </p>
                            </div>
                            <div class="col-12">
                                <h6 class="text-muted mb-2">Company Address</h6>
                                <p class="mb-0">{!! nl2br(e($company['address'] ?: '—')) !!}</p>
                            </div>
                        </div>
                    @elseif ($content)
                        <div class="policy-content">
                            {!! nl2br(e($content)) !!}
                        </div>
                    @endif

                    @if ($kycNote)
                        <div class="alert alert-info mt-4 mb-0">
                            <strong>Note:</strong> {{ $kycNote }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

@endsection
