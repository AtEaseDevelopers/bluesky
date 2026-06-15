@extends('layouts.app')
@section('title', 'Home')
@section('content')

    <div class="row text-center my-5 py-5">
        <div class="col-md-12">
            <h1 class="display-3">Welcome to {{ env('APP_NAME') }}</h1>
            <p class="lead mb-5">Discover a wide range of products and place your orders with ease.</p>
            <p class="mb-4">Interested in exploring our platform? Contact us to obtain your login credentials and start browsing our products.</p>
            <a href="{{ route('login') }}" class="btn btn-outline-primary px-5">
                Signin to your account
            </a>
        </div>
    </div>

    <footer>
        <div class="fixed-footer">
            <p>&copy; {{ date('Y') }} {{ env('APP_NAME') }}</p>
        </div>
    </footer>

@endsection
