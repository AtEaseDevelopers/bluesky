@extends('layouts.member')
@section('title', 'Products')
@section('content')

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 full-width-on-mobile">
        <h4><i class="fa fa-star me-2" aria-hidden="true"></i> Our Products</h4>
        <form class="d-flex" role="search">
            <input class="form-control me-2" type="search" placeholder="Search" name="keyword" value="{{ $keyword }}">
            <button class="btn btn-outline-primary" type="submit">Search</button>
        </form>
    </div>
    <div class="row mb-5">
        @forelse ($products as $product)
            <div class="col-12 col-custom-2 col-sm-6 col-md-3 mb-4">
                <div class="card no-border shadow {{ $product->added_to_cart ? 'added-in-cart' : '' }}">
                    <div class="card-body">
                        <a href="{{ route('member.products.show', encrypt($product->id)) }}">
                            <img src="{{ $product->image_url }}" onError="this.onerror=null;this.src='{{ asset('assets/images/product-default.jpg') }}';" class="card-img-top" alt="{{ $product->name }}">
                        </a>
                        <h5 class="card-title my-4">{{ $product->name }}</h5>
                        <p class="mb-3">
                            <span class="badge {{ (float) $product->stock_quantity > 0 ? 'bg-success' : 'bg-secondary' }}">
                                {{ $product->stock_label }}
                            </span>
                        </p>
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
                                @if ((float) $product->stock_quantity > 0)
                                <button type="button" class="btn btn-outline-primary btn-add-to-cart mb-1" data-id="{{ encrypt($product->id) }}" data-action="{{ route('member.add-to-cart', encrypt($product->id)) }}" data-bs-toggle="modal" data-bs-target="#add-to-cart">
                                    Add to cart
                                </button>
                                @else
                                <button type="button" class="btn btn-secondary mb-1" disabled>Out of stock</button>
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
                <div class="alert alert-info mb-0">No products are currently in stock. Please check back later.</div>
            </div>
        @endforelse
    </div>

    @if ($preferred_products)
        <h3 class="mb-4"><i class="fa fa-lightbulb-o me-2" aria-hidden="true"></i> Based On Your Previous Order</h3>
        <div class="row mb-5">
            @foreach ($preferred_products as $product)
                <div class="col-12 col-custom-2 col-sm-6 col-md-3 mb-4">
                    <div class="card no-border shadow {{ $product->added_to_cart ? 'added-in-cart' : '' }}">
                        <div class="card-body">
                            <img src="{{ $product->image_url }}" onError="this.onerror=null;this.src='{{ asset('assets/images/product-default.jpg') }}';" class="card-img-top" alt="{{ $product->name }}">
                            <h5 class="card-title my-4">{{ $product->name }}</h5>
                            <p class="mb-3">
                                <span class="badge bg-success">{{ $product->stock_label }}</span>
                            </p>
                            <p class="alert alert-info text-center py-2 mb-4 text-muted">You've ordered {{ $product->sold_count }} before</p>
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
                                    <a href="{{ route('member.products.show', encrypt($product->id)) }}" class="btn btn-outline-primary btn-add-to-cart mb-1" data-id="{{ encrypt($product->id) }}" data-action="{{ route('member.add-to-cart', encrypt($product->id)) }}" data-bs-toggle="modal" data-bs-target="#add-to-cart">
                                        Add to cart
                                    </a>
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
            @endforeach
        </div>
    @endif

    <div class="modal" id="add-to-cart" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add to cart</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="POST" id="add-to-cart-form" class="form-wrapper">
                    @csrf
                    <div class="modal-body"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>
                        <button type="submit" class="btn btn-primary">
                            Add to cart
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
