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
                                    <tr data-sell-in="{{ $product->sell_in ?? 'weight' }}">
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
                                                    <input type="number" id="quantity" name="quantity" class="quantity-input" data-id="{{ $product->cart_product_id }}" data-sell-in="qty" value="{{ $product->quantity }}">
                                                    <button class="btn btn-sm btn-primary increase-quantity" type="button">+</button>
                                                </div>
                                            @elseif ($product->sell_in == 'qty_bill_weight')
                                                <div class="quantity-controls mb-2">
                                                    <span class="small text-muted d-block mb-1">Qty</span>
                                                    <button class="btn btn-sm btn-primary decrease-quantity" type="button">-</button>
                                                    <input type="number" name="quantity" class="quantity-input" data-id="{{ $product->cart_product_id }}" data-sell-in="qty_bill_weight" value="{{ $product->quantity }}">
                                                    <button class="btn btn-sm btn-primary increase-quantity" type="button">+</button>
                                                </div>
                                                <div class="weight-controls">
                                                    <span class="small text-muted d-block mb-1">Weight (KG)</span>
                                                    <button class="btn btn-sm btn-primary decrease-weight" type="button">-</button>
                                                    <input type="number" name="weight" class="weight-input" data-id="{{ $product->cart_product_id }}" data-sell-in="qty_bill_weight" value="{{ $product->weight }}">
                                                    <button class="btn btn-sm btn-primary increase-weight" type="button">+</button>
                                                </div>
                                            @else
                                                <div class="weight-controls">
                                                    <button class="btn btn-sm btn-primary decrease-weight" type="button">-</button>
                                                    <input type="number" id="weight" name="weight" class="weight-input" data-id="{{ $product->cart_product_id }}" data-sell-in="weight" value="{{ $product->weight }}">
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
                                                RM <span class="product-price-val">{{ number_format((float) $product->price, 2, '.', '') }}</span>
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
                                            Your cart is empty. <a href='{{ $portal['products_url'] }}'>Shop now</a>
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
                                            <a href="{{ $portal['checkout_url'] }}" class="btn btn-primary px-5">Proceed to Checkout</a>
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
                        window.location.href = "{{ $portal['remove_cart_url'] }}" + "/" + cartProductId;
                    }
                });
            })

            function cartBillAmount(row) {
                var sellIn = row.data('sellIn') || 'weight';
                var qty = parseFloat(row.find('.quantity-input').val()) || 0;
                var weight = parseFloat(row.find('.weight-input').val()) || 0;

                if (sellIn === 'qty') {
                    return qty;
                }

                return weight;
            }

            function updatePrices() {
                var total = 0;

                $('table.table-bordered tbody tr[data-sell-in]').each(function() {
                    var row = $(this);
                    var unitPrice = parseFloat(row.find('.unit-price-val').text()) || 0;
                    var productPrice = unitPrice * cartBillAmount(row);

                    row.find('.product-price-val').text(productPrice.toFixed(2));
                    total += productPrice;
                });

                if ($('#total-price-value').length) {
                    $('#total-price-value').text(total.toFixed(2));
                }
            }

            function syncCartRow(input) {
                var row = $(input).closest('tr');
                var data = {
                    _token: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    id: $(input).data('id')
                };

                if (row.find('.quantity-input').length) {
                    data.quantity = row.find('.quantity-input').val();
                }
                if (row.find('.weight-input').length) {
                    data.weight = row.find('.weight-input').val();
                }

                $.ajax({
                    url: "{{ $portal['update_cart_url'] }}",
                    method: 'POST',
                    data: data,
                    success: function(response) {
                        updatePrices();
                    },
                    error: function(xhr, status, error) {
                        console.error(error);
                    }
                });
            }

            // Decrease quantity
            $(document).on('click', '.decrease-quantity', function() {
                var quantityInput = $(this).closest('.quantity-controls').find('.quantity-input');
                var currentQuantity = parseInt(quantityInput.val());
                if (currentQuantity > 1) {
                    quantityInput.val(currentQuantity - 1).trigger('input');
                }
            });

            // Increase quantity
            $(document).on('click', '.increase-quantity', function() {
                var quantityInput = $(this).closest('.quantity-controls').find('.quantity-input');
                var currentQuantity = parseInt(quantityInput.val());
                quantityInput.val(currentQuantity + 1).trigger('input');
            });

            $(document).on('click', '.decrease-weight', function() {
                var weightInput = $(this).closest('.weight-controls').find('.weight-input');
                var currentWeight = parseFloat(weightInput.val());
                if (currentWeight > 0.1) {
                    weightInput.val(currentWeight - 1).trigger('input');
                }
            });

            $(document).on('click', '.increase-weight', function() {
                var weightInput = $(this).closest('.weight-controls').find('.weight-input');
                var currentWeight = parseFloat(weightInput.val());
                weightInput.val(currentWeight + 1).trigger('input');
            });

            $('.quantity-input, .weight-input').on('input', function(e) {
                if ($(this).val() < 0.1) {
                    e.preventDefault();
                    $(this).val(0.1);
                    return false;
                }

                syncCartRow(this);
            });

            // Call the updatePrices function on page load
            updatePrices();
        });
    </script>

@endsection
