<!DOCTYPE html>
<html lang="{{ $htmlLang ?? 'en' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Driver') | {{ env('APP_NAME') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/images/favicon.png') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/font-awesome.min.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #07203b;
            --deep: #023e7d;
            --teal: #0496a8;
            --accent: #ff5d3b;
            --bg: #e9f1f6;
            --surface: #ffffff;
            --muted: #4a6072;       /* darker than bootstrap text-muted, passes contrast */
            --line: #d6e2ec;
            --ok: #18794e;
            --warn: #b45309;
            --danger: #c0263d;
        }
        * { -webkit-tap-highlight-color: transparent; }
        body {
            background: var(--bg);
            color: var(--ink);
            font-family: 'Manrope', system-ui, sans-serif;
            font-size: 1rem;
            padding-bottom: 2.5rem;
        }
        h1, h2, h3, h4, h5, h6, .display-font {
            font-family: 'Bricolage Grotesque', system-ui, sans-serif;
            letter-spacing: -0.02em;
        }
        .driver-container { max-width: 720px; }

        /* Top bar */
        .driver-navbar {
            background: linear-gradient(120deg, var(--deep) 0%, var(--teal) 100%);
            box-shadow: 0 4px 18px rgba(2, 62, 125, .25);
        }
        .driver-navbar .navbar-brand,
        .driver-navbar .nav-link { color: #fff !important; font-weight: 600; }
        .driver-navbar .navbar-brand { font-family: 'Bricolage Grotesque', sans-serif; font-size: 1.25rem; }
        .driver-navbar .nav-link { opacity: .95; }
        .driver-navbar .nav-link:hover { opacity: 1; }

        /* Cards */
        .driver-card {
            border: 1px solid var(--line);
            border-radius: 1rem;
            background: var(--surface);
            box-shadow: 0 2px 10px rgba(7, 32, 59, .06);
            animation: rise .4s ease both;
        }
        @keyframes rise { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }

        .text-muted-ink { color: var(--muted) !important; }
        .detail-label {
            color: var(--muted);
            font-size: .8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        /* Status / payment pills — readable size */
        .pill {
            display: inline-flex; align-items: center; gap: .35rem;
            font-size: .8rem; font-weight: 700;
            padding: .3rem .65rem; border-radius: 999px; line-height: 1;
        }
        .pill-processing { background: #e7eef6; color: var(--deep); }
        .pill-delivering { background: #fff1e6; color: var(--warn); }
        .pill-completed  { background: #e3f3ec; color: var(--ok); }
        .pill-cancelled  { background: #fbe5e9; color: var(--danger); }
        .pill-unpaid  { background: #fbe5e9; color: var(--danger); }
        .pill-partial { background: #fff1e6; color: var(--warn); }
        .pill-paid    { background: #e3f3ec; color: var(--ok); }
        .pill-due     { background: #e7eef6; color: var(--deep); }
        .text-danger-ink { color: var(--danger) !important; }

        /* Buttons */
        .btn { font-weight: 600; border-radius: .7rem; }
        .btn-lg, .btn-block-tall { padding-top: .75rem; padding-bottom: .75rem; }
        .btn-brand { background: var(--deep); border-color: var(--deep); color: #fff; }
        .btn-brand:hover { background: #012f63; border-color: #012f63; color: #fff; }
        .btn-accent { background: var(--accent); border-color: var(--accent); color: #fff; }
        .btn-accent:hover { background: #ed4a28; border-color: #ed4a28; color: #fff; }
        .btn-outline-brand { border: 2px solid var(--deep); color: var(--deep); background: transparent; }
        .btn-outline-brand:hover { background: var(--deep); color: #fff; }

        .order-row-link { text-decoration: none; color: inherit; display: block; }
        .order-row-link:active .driver-card { transform: scale(.99); }

        a { color: var(--deep); }
        .form-control, .form-select { border-radius: .7rem; border-color: var(--line); padding: .6rem .85rem; font-size: 1rem; }
        .form-control:focus, .form-select:focus { border-color: var(--teal); box-shadow: 0 0 0 .2rem rgba(4,150,168,.18); }
        .form-label { font-weight: 600; }
        .nav-pills .nav-link { color: var(--muted); font-weight: 600; border-radius: 999px; }
        .nav-pills .nav-link.active { background: var(--deep); }
        .driver-navbar .nav-link.active-tab { opacity: 1; box-shadow: inset 0 -2px 0 0 #fff; }
    </style>
    @yield('css')
</head>

<body>
    @auth('web_driver')
        <nav class="navbar navbar-expand driver-navbar sticky-top">
            <div class="container-fluid px-3 px-md-4">
                <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('driver.orders.index') }}">
                    <i class="fa fa-anchor"></i> <span class="d-none d-sm-inline">Bluesky Driver</span>
                </a>
                <ul class="navbar-nav flex-row align-items-center gap-1 me-auto ms-2">
                    @if ($driverPermissions['delivery_orders'] ?? false)
                        <li class="nav-item">
                            <a class="nav-link px-2 {{ request()->routeIs('driver.orders.*') ? 'active-tab' : '' }}" href="{{ route('driver.orders.index') }}">
                                <i class="fa fa-cubes"></i> <span class="d-none d-sm-inline">Deliveries</span>
                            </a>
                        </li>
                    @endif
                    @if ($driverPermissions['assigned_customers'] ?? false)
                        <li class="nav-item">
                            <a class="nav-link px-2 {{ request()->routeIs('driver.customers.*') ? 'active-tab' : '' }}" href="{{ route('driver.customers.index') }}">
                                <i class="fa fa-users"></i> <span class="d-none d-sm-inline">Customers</span>
                            </a>
                        </li>
                    @endif
                </ul>
                <ul class="navbar-nav ms-auto align-items-center">
                    @if ($driverPermissions['vehicle'] ?? false)
                        <li class="nav-item">
                            <a class="nav-link px-2 d-flex align-items-center gap-1 {{ request()->routeIs('driver.vehicle.*') ? 'active-tab' : '' }}"
                               href="{{ route('driver.vehicle.edit') }}" data-bs-toggle="tooltip" title="{{ __('Change vehicle') }}">
                                <i class="fa fa-truck"></i>
                                <span>{{ Auth::guard('web_driver')->user()->lorry_number ?: __('Vehicle') }}</span>
                            </a>
                        </li>
                    @endif
                    @include('partials.language-switcher')
                    <li class="nav-item me-2 d-none d-sm-block">
                        <span class="nav-link">{{ Auth::guard('web_driver')->user()->name ?? Auth::guard('web_driver')->user()->username }}</span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('driver.logout') }}">
                            <i class="fa fa-sign-out"></i> {{ __('ui.logout') }}
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
    @endauth

    <main class="container driver-container py-4">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa fa-check-circle me-1"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa fa-exclamation-circle me-1"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @yield('content')
    </main>

    <script src="{{ asset('assets/js/jquery-3.6.0.min.js') }}"></script>
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    <script>
        // Enable Bootstrap tooltips (used on icon-only buttons).
        document.addEventListener('DOMContentLoaded', function () {
            var els = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            els.forEach(function (el) { new bootstrap.Tooltip(el); });
        });
    </script>
    @yield('script')
</body>

</html>
