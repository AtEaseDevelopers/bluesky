@php
    $user = Auth::guard('web_admin')->user();
    $currentRoute = Route::currentRouteName();
@endphp
<nav class="navbar sticky-top navbar-expand-lg bg-body-tertiary">
    <div class="container">
        <a class="navbar-brand" href="{{ route('admin.dashboard') }}">
            <img src="{{ asset('assets/images/logo.png') }}" alt="{{ env('APP_NAME') }}" class="app-logo">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarScroll"
            aria-controls="navbarScroll" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarScroll">
            <ul class="navbar-nav me-auto my-2 my-lg-0 navbar-nav-scroll">
                @if ($user->canAccessModule('dashboard'))
                    <li class="nav-item">
                        <a class="nav-link {{ $currentRoute === 'admin.dashboard' ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">{{ __('ui.nav.dashboard') }}</a>
                    </li>
                @endif
                @if ($user->canAccessModule('customers'))
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="javascript:void(0);" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            {{ __('ui.nav.customers') }}
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('admin.customers') }}">{{ __('ui.nav.manage_customers') }}</a></li>
                            @if ($user->canModule('customers', 'create'))
                                <li><a class="dropdown-item" href="{{ route('admin.customers.invite') }}">{{ __('ui.nav.invite_customer') }}</a></li>
                                <li><a class="dropdown-item" href="{{ route('admin.customers.create') }}">{{ __('ui.nav.add_customer') }}</a></li>
                            @endif
                        </ul>
                    </li>
                @endif
                @if ($user->canAccessModule('orders'))
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="javascript:void(0);" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            {{ __('ui.nav.orders') }}
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('admin.orders') }}">{{ __('ui.nav.manage_orders') }}</a></li>
                            @if ($user->canModule('orders', 'create'))
                                <li><a class="dropdown-item" href="{{ route('admin.orders.create') }}">{{ __('ui.nav.add_order') }}</a></li>
                            @endif
                            @if ($user->canModule('orders', 'edit'))
                                <li><a class="dropdown-item" href="{{ route('admin.delivery-slots.index') }}">{{ __('ui.nav.delivery_slots') }}</a></li>
                            @endif
                        </ul>
                    </li>
                @endif
                @if ($user->canAccessModule('products'))
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="javascript:void(0);" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            {{ __('ui.nav.products') }}
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('admin.products') }}">{{ __('ui.nav.manage_products') }}</a></li>
                            @if ($user->canModule('products', 'create'))
                                <li><a class="dropdown-item" href="{{ route('admin.products.create') }}">{{ __('ui.nav.add_product') }}</a></li>
                            @endif
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="javascript:void(0);" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            {{ __('ui.nav.inventory') }}
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('admin.inventory.index') }}">{{ __('ui.nav.stock_balance') }}</a></li>
                            @if ($user->canModule('products', 'edit'))
                                <li><a class="dropdown-item" href="{{ route('admin.inventory.stock-in.create') }}">{{ __('ui.nav.stock_in') }}</a></li>
                                <li><a class="dropdown-item" href="{{ route('admin.inventory.stock-out.create') }}">{{ __('ui.nav.stock_out') }}</a></li>
                            @endif
                            <li><a class="dropdown-item" href="{{ route('admin.inventory.movements') }}">{{ __('ui.nav.movement_log') }}</a></li>
                        </ul>
                    </li>
                @endif
                @if ($user->canAccessModule('reports'))
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="javascript:void(0);" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            {{ __('ui.nav.reports') }}
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('admin.daily-sales-report') }}">{{ __('ui.nav.daily_sales_report') }}</a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.do-report') }}">{{ __('ui.nav.do_report') }}</a></li>
                        </ul>
                    </li>
                @endif
                @if ($user->canAccessModule('drivers'))
                    <li class="nav-item">
                        <a class="nav-link {{ str_starts_with($currentRoute ?? '', 'admin.drivers.') ? 'active' : '' }}" href="{{ route('admin.drivers.index') }}">
                            {{ __('ui.nav.drivers_lorry') }}
                        </a>
                    </li>
                @endif
                @if ($user->canAccessModule('settings'))
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="javascript:void(0);" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            {{ __('ui.nav.settings') }}
                        </a>
                        <ul class="dropdown-menu">
                            @if ($user->canManageAdminUsers())
                                <li><a class="dropdown-item" href="{{ route('admin.admins.index') }}">{{ __('ui.nav.admin_users') }}</a></li>
                            @endif
                            @if ($user->canManageRolePermissions())
                                <li><a class="dropdown-item" href="{{ route('admin.roles.index') }}">{{ __('ui.nav.manage_roles') }}</a></li>
                            @endif
                            @if ($user->canManageAdminUsers() && $user->canManageRolePermissions())
                                <li><hr class="dropdown-divider"></li>
                            @endif
                            <li><a class="dropdown-item" href="{{ route('admin.areas.index') }}">{{ __('ui.nav.areas') }}</a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.uom.index') }}">{{ __('ui.nav.uom') }}</a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.product-categories.index') }}">{{ __('ui.nav.product_categories') }}</a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.customer-categories.index') }}">{{ __('ui.nav.customer_categories') }}</a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.settings.delivery-order') }}">{{ __('ui.nav.delivery_order_settings') }}</a></li>
                        </ul>
                    </li>
                @endif
            </ul>
            <ul class="navbar-nav d-flex align-items-center">
                @include('partials.language-switcher')
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="javascript:void(0);" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        {{ __('ui.nav.greeting', ['name' => $user->name]) }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text text-muted small">{{ $user->roleLabel() }}</span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('admin.profile') }}">{{ __('ui.profile') }}</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('admin.logout') }}">{{ __('ui.logout') }}</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
