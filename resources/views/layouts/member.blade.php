<!DOCTYPE html>
<html lang="{{ $htmlLang ?? 'en' }}">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="app-url" content="{{ url('/') }}">
    <meta name="product-info-url" content="{{ $portal['product_info_url'] ?? url('/add-to-cart-product-info') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="form-has-old-input" content="{{ ($errors ?? null) && $errors->any() ? '1' : '0' }}">
    <title>@yield('title', 'Home') | {{ env('APP_NAME') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/images/favicon.png') }}"/>
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v=" />
    <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}?v=1.2" />
    @yield('css')

</head>

<body>

    @include('member.includes.nav')
    
    <main>
        <div class="container my-5">
            @include('partials.response')
            @yield('content')
        </div>
    </main>

    <script src="{{ asset('assets/js/jquery-3.6.0.min.js') }}"></script>
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/form-draft.js') }}?v=3"></script>
    @yield('script')
    <script src="{{ asset('assets/js/script.js') }}?v=2.0"></script>
</body>

</html>
