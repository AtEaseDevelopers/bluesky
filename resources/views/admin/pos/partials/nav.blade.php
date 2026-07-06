<nav class="navbar sticky-top navbar-expand-lg bg-body-tertiary border-bottom">
    <div class="container">
        <a class="navbar-brand" href="{{ route('admin.pos.index') }}">
            <img src="{{ asset('assets/images/logo.png') }}" alt="{{ env('APP_NAME') }}" class="app-logo">
        </a>
        <div>
            <a href="{{ $portal['cart_url'] ?? route('admin.pos.cart') }}" class="navbar-toggler-cart btn btn-success">
                <i class="fa fa-shopping-cart"></i> <span>{{ $cartCount ?? 0 }}</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#posNavbar"
                aria-controls="posNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
        <div class="collapse navbar-collapse" id="posNavbar">
            <ul class="navbar-nav me-auto my-2 my-lg-0">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.pos.index') ? 'active' : '' }}" href="{{ route('admin.pos.index') }}">
                        {{ __('customers.pos.nav_products') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.pos.cart') ? 'active' : '' }}" href="{{ $portal['cart_url'] ?? route('admin.pos.cart') }}">
                        {{ __('customers.pos.nav_cart') }} <span class="badge badge-success">{{ $cartCount ?? 0 }}</span>
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav align-items-center gap-2">
                @if ($posReady ?? false)
                    <li class="nav-item">
                        <span class="badge bg-warning text-dark">{{ $posCustomerLabel ?? '' }}</span>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="posChangeCustomerBtn">
                            {{ __('customers.pos.change_customer') }}
                        </button>
                    </li>
                @endif
                <li class="nav-item">
                    <form action="{{ route('admin.pos.exit') }}" method="POST" class="mb-0">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-dark">{{ __('customers.pos.exit') }}</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</nav>
