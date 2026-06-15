@extends('layouts.member')
@section('title', 'Cart')
@section('content')

    <h4 class="mb-4"><i class="fa fa-shopping-cart me-2" aria-hidden="true"></i> Cart</h4>
    <div class="row cart-container mb-5">
        <div class="col-md-12 mb-5">
            <div class="card no-border shadow">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th></th>
                                    <th>Product</th>
                                    <th>Remarks</th>
                                    <th>Quantity/Weight</th>
                                    <th>Amount</th>
                                    <th>Total</th>
                                    <th>Option</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($products as $product)
                                    <tr>
                                        <td>
                                            <img src="{{ $product->image_url }}" onError="this.onerror=null;this.src='{{ asset('assets/images/product-default.jpg') }}';" alt="{{ $product->name }}" class="img-fluid" width="100px">
                                        </td>
                                        <td>
                                            {{ $product->name }} <br>
                                            @foreach ($product->options as $opt => $opt_itm)
                                                <span>{{ $opt . ': ' . $opt_itm }}</span><br />
                                            @endforeach
                                        </td>
                                        <td>{{ $product->remark ?? '-' }}</td>
                                        <td>
                                            @if ($product->sell_in == 'qty')
                                                <div class="quantity-controls">
                                                    <button class="btn btn-sm btn-primary decrease-quantity" type="button">-</button>
                                                    <input type="number" id="quantity" name="quantity" class="quantity-input" data-id="{{ $product->cart_product_id }}" value="{{ $product->quantity }}">
                                                    <button class="btn btn-sm btn-primary increase-quantity" type="button">+</button>
                                                </div>
                                            @else
                                                <div class="weight-controls">
                                                    <button class="btn btn-sm btn-primary decrease-weight" type="button">-</button>
                                                    <input type="number" id="weight" name="weight" class="weight-input" data-id="{{ $product->cart_product_id }}" value="{{ $product->weight }}">
                                                    <button class="btn btn-sm btn-primary increase-weight" type="button">+</button>
                                                    <span class="ms-1">KG</span>
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($user->price_permission)
                                                @if ($product->original_unit_price > $product->unit_price)
                                                    <span class="original-price">RM {{ $product->original_unit_price ?: '0.00' }}</span><br>
                                                @endif
                                                RM <span class="unit-price-val">{{ $product->unit_price }}</span>
                                            @else
                                            -
                                            @endif
                                        </td>
                                        <td>
                                            @if ($user->price_permission)
                                                RM <span class="product-price-val">{{ $product->price }}</span>
                                            @else
                                            -
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <a class="btn btn-danger remove-button" data-id="{{ $product->cart_product_id }}">
                                                <i class="fa fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7">
                                            Your cart is empty. <a href='{{ route('member.products') }}'>Shop now</a>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4"></td>
                                    <td>Total</td>
                                    <td colspan="2">
                                        @if ($user->price_permission)
                                            RM <span id="total-price-value">{{ $total }}</span>
                                        @else
                                        -
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="7">
                                        <div class="d-flex justify-content-end">
                                            <a href="{{ route('member.checkout') }}" class="btn btn-primary px-5">Proceed to Checkout</a>
                                        </div>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('script')

    <script src="{{ asset('assets/js/sweetalert.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            $(".remove-button").on('click', function() {
                var cartProductId = $(this).data('id');

                // Show SweetAlert for confirmation
                Swal.fire({
                    title: 'Confirm',
                    text: 'Are you sure you want to remove this item from your cart?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, remove it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Redirect to remove cart item URL
                        window.location.href = "{{ url('/remove-cart-item') }}" + "/" + cartProductId;
                    }
                });
            })

            function updatePrices() {
                $('.cart-container').each(function() {
                    var container = $(this);
                    var quantity = parseInt(container.find('.quantity-input').val()) || 0;
                    var unitPrice = parseFloat(container.find('.unit-price-val').text()) || 0;

                    // Calculate product price for this item
                    var productPrice = unitPrice * quantity;

                    // Update the product price for this item
                    container.find('.product-price-val').text(productPrice.toFixed(2));
                });

                // Calculate the total price for all items
                var total = 0;
                $('.product-price-val').each(function() {
                    total += parseFloat($(this).text()) || 0;
                });

                // Update the total price
                if ($('#total-price-value')) {
                    $('#total-price-value').text(total.toFixed(2));
                }
            }

            // Decrease quantity
            $(document).on('click', '.decrease-quantity', function() {
                var quantityInput = $(this).closest('.quantity-controls').find('.quantity-input');
                var currentQuantity = parseInt(quantityInput.val());
                if (currentQuantity > 1) {
                    quantityInput.val(currentQuantity - 1).trigger('input');
                    updatePrices();
                }
            });

            // Increase quantity
            $(document).on('click', '.increase-quantity', function() {
                var quantityInput = $(this).closest('.quantity-controls').find('.quantity-input');
                var currentQuantity = parseInt(quantityInput.val());
                quantityInput.val(currentQuantity + 1).trigger('input');
                updatePrices();
            });

            // Call the updatePrices function when quantity changes
            $('.quantity-input').on('input', function(e) {
                if ($(this).val() < 1) {
                    e.preventDefault();
                    $(this).val(1)
                    return false;
                }

                $.ajax({
                    url: document.querySelector('meta[name="app-url"]').getAttribute('content') + "/update-cart-item",
                    method: 'POST',
                    data: {
                        _token: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        quantity: $(this).val(),
                        id: $(this).data('id')
                    },
                    success: function(response) {
                        updatePrices();
                    },
                    error: function(xhr, status, error) {
                        console.error(error);
                    }
                });
            });
            
            // Decrease weight
            $(document).on('click', '.decrease-weight', function() {
                var weightInput = $(this).closest('.weight-controls').find('.weight-input');
                var currentWeight = parseFloat(weightInput.val());
                if (currentWeight > 0.1) {
                    weightInput.val(currentWeight - 1).trigger('input');
                    updatePrices();
                }
            });

            // Increase weight
            $(document).on('click', '.increase-weight', function() {
                var weightInput = $(this).closest('.weight-controls').find('.weight-input');
                var currentWeight = parseFloat(weightInput.val());
                weightInput.val(currentWeight + 1).trigger('input');
                updatePrices();
            });
            
            // Call the updatePrices function when weight changes
            $('.weight-input').on('input', function(e) {
                if ($(this).val() < 0.1) {
                    e.preventDefault();
                    $(this).val(0.1)
                    return false;
                }

                $.ajax({
                    url: document.querySelector('meta[name="app-url"]').getAttribute('content') + "/update-cart-item",
                    method: 'POST',
                    data: {
                        _token: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        weight: $(this).val(),
                        id: $(this).data('id')
                    },
                    success: function(response) {
                        updatePrices();
                    },
                    error: function(xhr, status, error) {
                        console.error(error);
                    }
                });
            });

            // Call the updatePrices function on page load
            updatePrices();
        });
    </script>

@endsection
