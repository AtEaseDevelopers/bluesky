@extends('driver.layouts.app')
@section('title', 'My Deliveries')
@section('content')


    <div class="d-flex justify-content-between align-items-end mb-3">
        <div>
            <h2 class="display-font mb-0" style="font-size:1.6rem;">My Deliveries</h2>
            <div class="text-muted-ink">{{ $orders->total() }} order{{ $orders->total() === 1 ? '' : 's' }} assigned</div>
        </div>
    </div>

    <ul class="nav nav-pills mb-3 flex-nowrap overflow-auto pb-1">
        <li class="nav-item">
            <a class="nav-link {{ !$activeStatus ? 'active' : '' }}" href="{{ route('driver.orders.index') }}">All</a>
        </li>
        @foreach (['processing' => 'Processing', 'in_route' => 'In Route', 'delivered' => 'Delivered'] as $st => $label)
            <li class="nav-item">
                <a class="nav-link text-nowrap {{ $activeStatus === $st ? 'active' : '' }}"
                   href="{{ route('driver.orders.index', ['status' => $st]) }}">{{ $label }}</a>
            </li>
        @endforeach
    </ul>

    @forelse ($orders as $order)
        @php
            $total = (float) $order->total_price;
            $paid = (float) $order->paid_amount;
            if ($paid <= 0) { $payLabel = 'Unpaid'; $payClass = 'pill-unpaid'; }
            elseif ($paid + 0.001 < $total) { $payLabel = 'Partial'; $payClass = 'pill-partial'; }
            else { $payLabel = 'Paid'; $payClass = 'pill-paid'; }
        @endphp
        <a href="{{ route('driver.orders.show', $order->id) }}" class="order-row-link">
            <div class="card driver-card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="fw-bold" style="font-size:1.05rem;">{{ $order->do_no ?? ('Order #' . $order->id) }}</div>
                            <div class="text-muted-ink">
                                <i class="fa fa-user me-1"></i>{{ $order->attn_name ?? optional($order->customer)->name ?? '—' }}
                            </div>
                        </div>
                        @php
                            $rowStatusLabel = \App\Http\Controllers\Driver\DeliveryOrderController::statusLabel($order->status);
                            $rowCanonicalStatus = \App\Http\Controllers\Driver\DeliveryOrderController::$legacy_status_map[$order->status] ?? $order->status;
                        @endphp
                        <span class="pill pill-{{ $rowCanonicalStatus }}">
                            {{ $rowStatusLabel }}
                        </span>
                    </div>
                    <div class="text-muted-ink mb-2">
                        <i class="fa fa-map-marker me-1"></i>{{ Str::limit($order->shipping_address ?? $order->billing_address ?? '—', 48) }}
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-2" style="border-top:1px solid var(--line);">
                        <span class="{{ $order->do_date ? 'text-muted-ink' : 'invisible' }}">
                            <i class="fa fa-calendar me-1"></i>{{ $order->do_date ? \Illuminate\Support\Carbon::parse($order->do_date)->format('d M Y') : '' }}
                        </span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="pill {{ $payClass }}">{{ $payLabel }}</span>
                            <span class="fw-bold" style="font-size:1.1rem;">RM {{ number_format($total, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    @empty
        <div class="card driver-card">
            <div class="card-body text-center py-5">
                <i class="fa fa-inbox fa-3x mb-3" style="color: var(--teal);"></i>
                <p class="mb-0 text-muted-ink">No delivery orders assigned.</p>
            </div>
        </div>
    @endforelse

    <div class="d-flex justify-content-center">
        {{ $orders->links('pagination::bootstrap-4') }}
    </div>

@endsection
