<!DOCTYPE html>
<html lang="{{ $htmlLang ?? 'en' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="app-url" content="{{ url('/') }}">
    <meta name="product-info-url" content="{{ $portal['product_info_url'] ?? route('admin.pos.add-to-cart-product-info') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('customers.pos.title')) | {{ env('APP_NAME') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/images/favicon.png') }}"/>
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v=" />
    <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}?v=1.2" />
    <link rel="stylesheet" href="{{ asset('assets/css/select2.min.css') }}?v=" />
    @yield('css')
</head>

<body class="{{ ($posReady ?? false) ? '' : 'pos-setup-required' }}">

    @include('admin.pos.partials.nav')

    <main>
        <div class="container my-5">
            @include('partials.response')
            @yield('content')
        </div>
    </main>

    @include('admin.pos.partials.setup-modal')

    <script src="{{ asset('assets/js/jquery-3.6.0.min.js') }}"></script>
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/select2.min.js') }}?v="></script>
    @yield('script')
    <script src="{{ asset('assets/js/script.js') }}?v=1.7"></script>
</body>

</html>
