@php
    $user = Auth::user();
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
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="{{ route('admin.dashboard') }}">Dashboard</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="javascript:void(0);" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        Customers
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('admin.customers') }}">Manage Customers</a></li>
                        <li><a class="dropdown-item" href="{{ route('admin.customers.create') }}">Add New Customer</a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="javascript:void(0);" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        Orders
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('admin.orders') }}">Manage Orders</a></li>
                        @if (Auth::guard('web_admin')->user()->role == 'superadmin')
                            <li><a class="dropdown-item" href="{{ route('admin.orders.create') }}">Add New Order</a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.delivery-slots.index') }}">Delivery Slots</a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.public-order-links.index') }}">Public Order Links</a></li>
                        @endif
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="javascript:void(0);" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        Inventory
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('admin.inventory.index') }}">Stock Balance</a></li>
                        <li><a class="dropdown-item" href="{{ route('admin.inventory.stock-in.create') }}">Stock In</a></li>
                        <li><a class="dropdown-item" href="{{ route('admin.inventory.stock-out.create') }}">Stock Out</a></li>
                        <li><a class="dropdown-item" href="{{ route('admin.inventory.movements') }}">Movement Log</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="javascript:void(0);" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        Reports
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('admin.daily-sales-report') }}">Daily Sales Report</a></li>
                        <li><a class="dropdown-item" href="{{ route('admin.do-report') }}">DO Report</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="javascript:void(0);" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        Settings
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('admin.admins.index') }}">Admin Settings</a></li>
                        <li><a class="dropdown-item" href="{{ route('admin.areas.index') }}">Areas</a></li>
                        <li><a class="dropdown-item" href="{{ route('admin.lorry.index') }}">Drivers / Lorry</a></li>
                        <li><a class="dropdown-item" href="{{ route('admin.uom.index') }}">UOM</a></li>
                    </ul>
                </li>
            </ul>
            <ul class="navbar-nav d-flex">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="javascript:void(0);" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        Hi, {{ $user->name }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('admin.profile') }}">My Profile</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item" href="{{ route('admin.logout') }}">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
