@extends('layouts.app')
@section('title', __('ui.landing.title'))
@section('content')

    <div class="row text-center my-5 py-5">
        <div class="col-md-12">
            <h1 class="display-3">{{ __('ui.landing.welcome', ['name' => config('app.name')]) }}</h1>
            <p class="lead mb-5">{{ __('ui.landing.tagline') }}</p>
            <p class="mb-4">{{ __('ui.landing.contact_prompt') }}</p>
            <a href="{{ route('login') }}" class="btn btn-outline-primary px-5">
                {{ __('ui.landing.sign_in') }}
            </a>
        </div>
    </div>

    <footer>
        <div class="fixed-footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}</p>
        </div>
    </footer>

@endsection
