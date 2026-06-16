<nav class="navbar sticky-top navbar-expand-lg bg-body-tertiary">
    <div class="container">
        <a class="navbar-brand" href="{{ isset($link) ? route('public.order', $link->token) : url('/') }}">
            <img src="{{ asset('assets/images/logo.png') }}" alt="{{ env('APP_NAME') }}" class="app-logo">
        </a>
        <div class="ms-auto d-flex gap-2">
            @if (isset($link))
                <a href="{{ route('public.order.cart', $link->token) }}" class="btn btn-outline-success btn-sm">
                    <i class="fa fa-shopping-cart"></i> Cart{{ isset($cartCount) ? " ($cartCount)" : '' }}
                </a>
            @endif
            <a href="{{ route('login') }}" class="btn btn-outline-primary btn-sm">Customer Login</a>
        </div>
    </div>
</nav>
