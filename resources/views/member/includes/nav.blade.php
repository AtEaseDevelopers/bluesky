@php
    $user = Auth::guard('web')->user();
    $currentRoute = Route::currentRouteName();
    $isGuest = $isGuest ?? false;
    $portal = $portal ?? [
        'products_url' => route('member.products'),
        'cart_url' => route('member.cart'),
        'orders_url' => route('member.orders'),
    ];
    $pendingReviewCount = (!$isGuest && $user)
        ? \App\Order::where('user_id', $user->id)
            ->where('status', \App\Order::$status['customer_reviewing'])
            ->count()
        : 0;
@endphp
<nav class="navbar sticky-top navbar-expand-lg bg-body-tertiary">
    <div class="container">
        <a class="navbar-brand" href="{{ $portal['products_url'] }}">
            <img src="{{ asset('assets/images/logo.png') }}" alt="{{ env('APP_NAME') }}" class="app-logo">
        </a>
        <div>
            <a href="{{ $portal['cart_url'] }}" class="navbar-toggler-cart btn btn-success">
                <i class="fa fa-shopping-cart"></i> <span>{{ $cartCount ?? 0 }}</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarScroll"
                aria-controls="navbarScroll" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
        <div class="collapse navbar-collapse" id="navbarScroll">
            <ul class="navbar-nav me-auto my-2 my-lg-0 navbar-nav-scroll">
                <li class="nav-item">
                    <a class="nav-link {{ in_array($currentRoute, ['member.products', 'public.guest.index']) ? 'active' : '' }}" href="{{ $portal['products_url'] }}">Order Menu</a>
                </li>
                @if (!$isGuest && ($portal['orders_url'] ?? null))
                    <li class="nav-item">
                        <a class="nav-link {{ in_array($currentRoute, ['member.orders', 'member.orders.summary', 'member.orders.review']) ? 'active' : '' }}" href="{{ $portal['orders_url'] }}">
                            My Orders
                            @if ($pendingReviewCount > 0)
                                <span class="badge bg-warning text-dark">{{ $pendingReviewCount }}</span>
                            @endif
                        </a>
                    </li>
                    @if ($user && $user->isCreditCustomer() && ($portal['bulk_payments_url'] ?? null))
                        <li class="nav-item">
                            <a class="nav-link {{ $currentRoute === 'member.bulk-payments' ? 'active' : '' }}" href="{{ $portal['bulk_payments_url'] }}">Bulk Payment</a>
                        </li>
                    @endif
                @endif
                <li class="nav-item">
                    <a class="nav-link {{ in_array($currentRoute, ['member.cart', 'public.guest.cart']) ? 'active' : '' }}" href="{{ $portal['cart_url'] }}">
                        Cart <span class="badge badge-success">{{ $cartCount ?? 0 }}</span>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ $currentRoute === 'member.policies.show' ? 'active' : '' }}"
                        href="javascript:void(0);" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Terms and Policies
                    </a>
                    <ul class="dropdown-menu">
                        @foreach (\App\Http\Controllers\Member\PolicyController::PAGES as $slug => $label)
                            <li>
                                <a class="dropdown-item {{ $currentRoute === 'member.policies.show' && request()->route('page') === $slug ? 'active' : '' }}"
                                    href="{{ route('member.policies.show', $slug) }}">
                                    {{ $label }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </li>
            </ul>
            @if (!$isGuest && $user)
                <ul class="navbar-nav d-flex">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="javascript:void(0);" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Hi, {{ $user->name }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/profile">My Profile</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="{{ route('logout') }}">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            @endif
        </div>
    </div>
</nav>
