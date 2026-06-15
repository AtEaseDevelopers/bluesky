@php
    $user = Auth::guard('web')->user();
    $currentRoute = Route::currentRouteName();
@endphp
<nav class="navbar sticky-top navbar-expand-lg bg-body-tertiary">
    <div class="container">
        <a class="navbar-brand" href="{{ route('member.products') }}">
            <img src="{{ asset('assets/images/logo.png') }}" alt="{{ env('APP_NAME') }}" class="app-logo">
        </a>
        <div>
            <a href="{{ route('member.cart') }}" class="navbar-toggler-cart btn btn-success">
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
                    <a class="nav-link {{ in_array($currentRoute, ['member.products']) ? 'active' : '' }}" href="{{ route('member.products') }}">Products</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ in_array($currentRoute, ['member.orders']) ? 'active' : '' }}" href="{{ route('member.orders') }}">My Orders</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ in_array($currentRoute, ['member.cart']) ? 'active' : '' }}" href="{{ route('member.cart') }}">
                        Cart <span class="badge badge-success">{{ $cartCount ?? 0 }}</span>
                    </a>
                </li>
            </ul>
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
        </div>
    </div>
</nav>
