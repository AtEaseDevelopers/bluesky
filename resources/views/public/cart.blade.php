@extends('layouts.public')
@section('title', 'Cart')
@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <h4 class="mb-0">Your Cart</h4>
        <a href="{{ route('public.order', $link->token) }}" class="btn btn-outline-primary">
            <i class="fa fa-chevron-left"></i> Continue Shopping
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            @if (count($items))
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Qty / Weight</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Line Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $item)
                                <tr>
                                    <td>
                                        {{ $item->name }}
                                        @if ($item->remark)
                                            <br><small class="text-muted">Remark: {{ $item->remark }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($item->sell_by_weight)
                                            {{ $item->weight ?? $item->quantity }} KG
                                        @else
                                            {{ $item->quantity }}
                                        @endif
                                    </td>
                                    <td class="text-end">RM {{ number_format($item->unit_price, 2) }}</td>
                                    <td class="text-end">RM {{ number_format($item->line_total, 2) }}</td>
                                    <td class="text-center">
                                        <form action="{{ route('public.order.cart.remove', [$link->token, $item->product_id]) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Subtotal</strong></td>
                                <td class="text-end"><strong>RM {{ number_format($subtotal, 2) }}</strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="d-flex justify-content-end mt-4">
                    <a href="{{ route('public.order.checkout', $link->token) }}" class="btn btn-primary btn-lg">
                        Proceed to Checkout
                    </a>
                </div>
            @else
                <p class="text-muted mb-3">Your cart is empty.</p>
                <a href="{{ route('public.order', $link->token) }}" class="btn btn-primary">Browse Products</a>
            @endif
        </div>
    </div>
@endsection
