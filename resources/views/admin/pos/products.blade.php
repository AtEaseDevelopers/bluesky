@extends('layouts.pos')
@section('title', __('customers.pos.nav_products'))
@section('css')
    <style>
        body.pos-setup-required main {
            pointer-events: none;
            opacity: 0.45;
            user-select: none;
        }
    </style>
@endsection
@section('content')

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4 full-width-on-mobile">
        <div>
            <h4 class="mb-2"><i class="fa fa-shopping-basket me-2" aria-hidden="true"></i> {{ __('customers.pos.nav_products') }}</h4>
            @include('partials.subject_to_availability')
        </div>
        <form class="d-flex" role="search" method="GET" action="{{ route('admin.pos.index') }}">
            <input class="form-control me-2" type="search" placeholder="{{ __('ui.search') }}" name="keyword" value="{{ $keyword }}">
            <button class="btn btn-outline-primary" type="submit">{{ __('ui.search') }}</button>
        </form>
    </div>

    <div class="row mb-5">
        @forelse ($products as $product)
            <div class="col-12 col-custom-2 col-sm-6 col-md-3 mb-4">
                <div class="card no-border shadow {{ $product->added_to_cart ? 'added-in-cart' : '' }}">
                    <div class="card-body">
                        <img src="{{ $product->image_url }}" onError="this.onerror=null;this.src='{{ asset('assets/images/product-default.jpg') }}';" class="card-img-top" alt="{{ $product->name }}">
                        <h5 class="card-title my-4">{{ $product->name }}</h5>
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 full-width-on-mobile">
                            <div>
                                @if ($user->price_permission)
                                    @if ($product->original_price > $product->price)
                                        <span class="original-price">RM {{ $product->original_price ?: '0.00' }}</span><br>
                                    @endif
                                    <b>{{ $product->price_label }}</b>
                                @endif
                            </div>
                            <div class="full-width-on-mobile">
                                @if ($posReady && (float) $product->storefront_available_amount > 0)
                                    <button type="button" class="btn btn-outline-primary btn-add-to-cart mb-1" data-id="{{ encrypt($product->id) }}" data-action="{{ route($portal['add_to_cart_name'], encrypt($product->id)) }}" data-bs-toggle="modal" data-bs-target="#add-to-cart">
                                        {{ __('customers.pos.add_to_cart') }}
                                    </button>
                                @elseif ((float) $product->storefront_available_amount > 0)
                                    <button type="button" class="btn btn-outline-primary mb-1" disabled>{{ __('customers.pos.add_to_cart') }}</button>
                                @else
                                    <button type="button" class="btn btn-secondary mb-1" disabled>{{ __('customers.pos.out_of_stock') }}</button>
                                @endif
                                @if ($product->added_to_cart)
                                    <button type="button" class="btn btn-primary disabled mb-1">
                                        <i class="fa fa-shopping-cart"></i> {{ $product->added_to_cart->quantity ?? ($product->added_to_cart->weight . ' KG') }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-info mb-0">{{ __('customers.pos.no_products') }}</div>
            </div>
        @endforelse
    </div>

    <div class="modal" id="add-to-cart" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('customers.pos.add_to_cart') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="POST" id="add-to-cart-form" class="form-wrapper">
                    @csrf
                    <div class="modal-body"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close">{{ __('ui.close') }}</button>
                        <button type="submit" class="btn btn-primary">
                            {{ __('customers.pos.add_to_cart') }}
                            <div class="spinner-border spinner-border-sm d-none" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection
