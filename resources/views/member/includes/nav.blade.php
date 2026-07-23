@php
    $user = Auth::guard('web')->user();
    $currentRoute = Route::currentRouteName();
    $isGuest = $isGuest ?? false;
    $portal = $portal ?? [
        'products_url' => route('member.products'),
        'cart_url' => route('member.cart'),
        'orders_url' => route('member.orders'),
    ];
    $customerPermissions = $customerPermissions ?? [];
    $can = function (string $permission) use ($isGuest, $customerPermissions) {
        if ($isGuest) {
            return true;
        }
        return $customerPermissions[$permission] ?? true;
    };
@endphp
<nav class="navbar sticky-top navbar-expand-lg bg-body-tertiary">
    <div class="container">
        <a class="navbar-brand" href="{{ $portal['products_url'] }}">
            <img src="{{ asset('assets/images/logo.png') }}" alt="{{ env('APP_NAME') }}" class="app-logo">
        </a>
        <div>
            @if ($can('cart'))
                <a href="{{ $portal['cart_url'] }}" class="navbar-toggler-cart btn btn-success">
                    <i class="fa fa-shopping-cart"></i> <span>{{ $cartCount ?? 0 }}</span>
                </a>
            @endif
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarScroll"
                aria-controls="navbarScroll" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
        <div class="collapse navbar-collapse" id="navbarScroll">
            <ul class="navbar-nav me-auto my-2 my-lg-0 navbar-nav-scroll">
                @if ($can('products'))
                    <li class="nav-item">
                        <a class="nav-link {{ in_array($currentRoute, ['member.products', 'public.guest.index']) ? 'active' : '' }}" href="{{ $portal['products_url'] }}">{{ __('ui.nav.order_menu') }}</a>
                    </li>
                @endif
                @if (!$isGuest && ($portal['orders_url'] ?? null) && $can('orders'))
                    <li class="nav-item">
                        <a class="nav-link {{ in_array($currentRoute, ['member.orders', 'member.orders.summary']) ? 'active' : '' }}" href="{{ $portal['orders_url'] }}">
                            {{ __('ui.nav.my_orders') }}
                        </a>
                    </li>
                    @if ($user && $user->isCreditCustomer() && ($portal['bulk_payments_url'] ?? null) && $can('bulk_payments'))
                        <li class="nav-item">
                            <a class="nav-link {{ $currentRoute === 'member.bulk-payments' ? 'active' : '' }}" href="{{ $portal['bulk_payments_url'] }}">{{ __('ui.nav.bulk_payment') }}</a>
                        </li>
                    @endif
                @endif
                @if ($can('cart'))
                    <li class="nav-item">
                        <a class="nav-link {{ in_array($currentRoute, ['member.cart', 'public.guest.cart']) ? 'active' : '' }}" href="{{ $portal['cart_url'] }}">
                            {{ __('ui.nav.cart') }} <span class="badge badge-success">{{ $cartCount ?? 0 }}</span>
                        </a>
                    </li>
                @endif
                @if ($can('policies'))
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ $currentRoute === 'member.policies.show' ? 'active' : '' }}"
                            href="javascript:void(0);" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            {{ __('ui.nav.terms_policies') }}
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
                @endif
            </ul>
            @if (!$isGuest && $user)
                <ul class="navbar-nav d-flex align-items-center">
                    @include('partials.language-switcher')
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="javascript:void(0);" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            {{ __('ui.nav.greeting', ['name' => $user->name]) }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            @if ($can('profile'))
                                <li><a class="dropdown-item" href="{{ route('member.profile') }}">{{ __('ui.profile') }}</a></li>
                                <li><hr class="dropdown-divider"></li>
                            @endif
                            <li><a class="dropdown-item" href="{{ route('logout') }}">{{ __('ui.logout') }}</a></li>
                        </ul>
                    </li>
                </ul>
            @else
                <ul class="navbar-nav d-flex align-items-center">
                    @include('partials.language-switcher')
                </ul>
            @endif
        </div>
    </div>
</nav>
