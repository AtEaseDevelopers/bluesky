@extends('driver.layouts.app')
@section('title', __('driver_portal.customers.title'))
@section('content')

    <div class="d-flex justify-content-between align-items-end mb-3">
        <div>
            <h2 class="display-font mb-0" style="font-size:1.6rem;">{{ __('driver_portal.customers.title') }}</h2>
            <div class="text-muted-ink">{{ trans_choice('driver_portal.customers.customers_assigned', $customers->total(), ['count' => $customers->total()]) }}</div>
        </div>
    </div>

    <div class="card driver-card mb-3">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <div class="detail-label mb-0">{{ __('driver_portal.customers.total_outstanding') }}</div>
                <div class="text-muted-ink">{{ __('driver_portal.customers.across_all') }}</div>
            </div>
            <span class="fw-bold {{ $grandOutstanding > 0 ? 'text-danger-ink' : '' }}" style="font-size:1.5rem;">RM {{ number_format($grandOutstanding, 2) }}</span>
        </div>
    </div>

    @forelse ($customers as $customer)
        @php
            $invoices = $customer->orders;
            $outstanding = \App\Http\Controllers\Driver\CustomerController::outstandingTotal($invoices);
            $overdue = \App\Http\Controllers\Driver\CustomerController::overdueCount($invoices);
            $isCredit = $customer->isCreditCustomer();
        @endphp
        <a href="{{ route('driver.customers.show', $customer->id) }}" class="order-row-link">
            <div class="card driver-card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="fw-bold" style="font-size:1.05rem;">{{ $customer->name }}</div>
                            <div class="text-muted-ink">
                                <i class="fa fa-phone me-1"></i>{{ $customer->attn_contact ?? $customer->phone ?? '—' }}
                            </div>
                        </div>
                        <span class="pill {{ $isCredit ? 'pill-due' : 'pill-paid' }}">
                            {{ $isCredit ? __('driver_portal.customers.credit') : __('driver_portal.customers.cod') }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-2" style="border-top:1px solid var(--line);">
                        <span class="text-muted-ink">
                            <i class="fa fa-file-text-o me-1"></i>{{ trans_choice('driver_portal.customers.invoices', $invoices->count(), ['count' => $invoices->count()]) }}
                            @if ($overdue > 0)
                                <span class="pill pill-unpaid ms-2">{{ trans_choice('driver_portal.customers.overdue', $overdue, ['count' => $overdue]) }}</span>
                            @endif
                        </span>
                        <div class="text-end">
                            <div class="detail-label mb-0">{{ __('driver_portal.customers.outstanding') }}</div>
                            <span class="fw-bold {{ $outstanding > 0 ? 'text-danger-ink' : '' }}" style="font-size:1.1rem;">RM {{ number_format($outstanding, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    @empty
        <div class="card driver-card">
            <div class="card-body text-center py-5">
                <i class="fa fa-users fa-3x mb-3" style="color: var(--teal);"></i>
                <p class="mb-0 text-muted-ink">{{ __('driver_portal.customers.empty') }}</p>
            </div>
        </div>
    @endforelse

    <div class="d-flex justify-content-center">
        {{ $customers->links('pagination::bootstrap-4') }}
    </div>

@endsection
