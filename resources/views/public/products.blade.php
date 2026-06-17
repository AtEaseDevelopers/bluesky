@extends('layouts.public')
@section('title', 'Order Menu')
@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h4 class="mb-1">Order Menu</h4>
            <p class="text-muted mb-0">Select products to add to your cart. COD payment only.</p>
        </div>
        <a href="{{ route('public.order.cart', $link->token) }}" class="btn btn-success">
            <i class="fa fa-shopping-cart"></i> Cart ({{ $cartCount }})
        </a>
    </div>

    <div class="row">
        @forelse ($products as $product)
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="card shadow-sm h-100">
                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="card-img-top" style="height:180px;object-fit:cover" onerror="this.onerror=null;this.src='{{ asset('assets/images/product-default.jpg') }}';">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">{{ $product->name }}</h5>
                        <p class="mb-2">
                            <span class="badge bg-success">{{ $product->stock_label }}</span>
                        </p>
                        <p class="mb-3">{{ $product->price_label }}</p>

                        <form action="{{ route('public.order.cart.add', [$link->token, $product->id]) }}" method="POST" class="mt-auto">
                            @csrf
                            @if ($product->sell_in === 'weight' || $product->show_weight)
                                <div class="mb-3">
                                    <label class="form-label">Order Qty ({{ $product->uom_name ?? 'KG' }})</label>
                                    <input type="number" name="weight" class="form-control" min="0.001" max="{{ $product->stock_quantity }}" step="0.001" placeholder="e.g. 1.5" required>
                                </div>
                            @else
                                <div class="mb-3">
                                    <label class="form-label">Quantity</label>
                                    <input type="number" name="quantity" class="form-control" min="0.001" max="{{ $product->stock_quantity }}" step="0.001" placeholder="e.g. 2" required>
                                </div>
                            @endif
                            <div class="mb-3">
                                <label class="form-label">Remark (optional)</label>
                                <input type="text" name="remark" class="form-control" maxlength="200" placeholder="e.g. clean and cut">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Add to Cart</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-info">No products are currently in stock.</div>
            </div>
        @endforelse
    </div>
@endsection
