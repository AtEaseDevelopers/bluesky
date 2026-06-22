<!DOCTYPE html>
<html lang="{{ $htmlLang ?? 'en' }}">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="app-url" content="{{ url('/') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Home') | {{ env('APP_NAME') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/images/favicon.png') }}"/>
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v=" />
    <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}?v=" />
    @yield('css')

</head>

<body>

    @if (in_array(Route::currentRouteName(), ['login', 'admin.login']))
        <nav class="navbar navbar-expand-lg bg-body-tertiary">
            <div class="container justify-content-end">
                <ul class="navbar-nav">
                    @include('partials.language-switcher')
                </ul>
            </div>
        </nav>
    @elseif (!in_array(Route::currentRouteName(), ['login', 'admin.login']))
        <nav class="navbar navbar-expand-lg bg-body-tertiary">
            <div class="container">
                <a class="navbar-brand" href="/">
                    <img src="{{ asset('assets/images/logo.png') }}" alt="{{ env('APP_NAME') }}" class="app-logo">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarScroll"
                    aria-controls="navbarScroll" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarScroll">
                    <ul class="navbar-nav me-auto my-2 my-lg-0 navbar-nav-scroll">
                    </ul>
                    <ul class="navbar-nav d-flex">
                        @include('partials.language-switcher')
                        @if (Auth::guard('web')->user())
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="javascript:void(0);" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Hi, {{ Auth::guard('web')->user()->name }}
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="/profile">{{ __('ui.profile') }}</a></li>
                                    <li><a class="dropdown-item" href="#">{{ __('ui.change_password') }}</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item" href="{{ route('logout') }}">{{ __('ui.logout') }}</a></li>
                                </ul>
                            </li>
                        @elseif (Auth::user())
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="javascript:void(0);" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Hi, {{ Auth::user()->name }}
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#">{{ __('ui.profile') }}</a></li>
                                    <li><a class="dropdown-item" href="#">{{ __('ui.change_password') }}</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item" href="{{ route('admin.logout') }}">{{ __('ui.logout') }}</a></li>
                                </ul>
                            </li>
                        @else
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('login') }}">{{ __('ui.login') }}</a>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>
        </nav>
    @endif
    
    <main>
        <div class="container my-5">
            {{-- @include('partials.response') --}}
            @yield('content')
        </div>
    </main>

    <script src="{{ asset('assets/js/jquery-3.6.0.min.js') }}"></script>
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/script.js') }}?v=1.1"></script>

</body>

</html>
