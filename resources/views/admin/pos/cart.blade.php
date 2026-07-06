@extends('layouts.pos')
@section('title', __('customers.pos.nav_cart'))
@section('content')

    <h4 class="mb-4"><i class="fa fa-shopping-cart me-2" aria-hidden="true"></i> {{ __('customers.pos.nav_cart') }}</h4>
    <div class="row cart-container mb-5">
        <div class="col-md-12 mb-5">
            <div class="card no-border shadow">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th></th>
                                    <th>{{ __('orders.product') }}</th>
                                    <th>{{ __('orders.remark') }}</th>
                                    <th>{{ __('orders.quantity') }}</th>
                                    <th>{{ __('product.estimated_weight', ['uom' => 'KG']) }}</th>
                                    <th>{{ __('orders.amount') }}</th>
                                    <th>{{ __('orders.total') }}</th>
                                    <th>{{ __('orders.option') }}</th>
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
                                            @if (in_array($product->sell_in, ['qty', 'qty_bill_weight', 'weight'], true))
                                                <div class="quantity-controls">
                                                    <button class="btn btn-sm btn-primary decrease-quantity" type="button">-</button>
                                                    <input type="number" name="quantity" class="quantity-input" data-id="{{ $product->cart_product_id }}" data-sell-in="{{ $product->sell_in }}" value="{{ $product->quantity }}" min="0.001" step="0.001">
                                                    <button class="btn btn-sm btn-primary increase-quantity" type="button">+</button>
                                                </div>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @if (in_array($product->sell_in, ['qty_bill_weight', 'weight'], true))
                                                <div class="weight-controls">
                                                    <small class="text-muted d-block mb-1">{{ __('product.optional') }}</small>
                                                    <button class="btn btn-sm btn-primary decrease-weight" type="button">-</button>
                                                    <input type="number" name="weight" class="weight-input weight-input-optional" data-id="{{ $product->cart_product_id }}" data-sell-in="qty_bill_weight" value="{{ $product->weight }}" min="0.001" step="0.001" placeholder="-">
                                                    <button class="btn btn-sm btn-primary increase-weight" type="button">+</button>
                                                </div>
                                            @elseif ($product->sell_in == 'weight')
                                                <div class="weight-controls">
                                                    <button class="btn btn-sm btn-primary decrease-weight" type="button">-</button>
                                                    <input type="number" name="weight" class="weight-input" data-id="{{ $product->cart_product_id }}" data-sell-in="weight" value="{{ $product->weight }}" min="0.001" step="0.001">
                                                    <button class="btn btn-sm btn-primary increase-weight" type="button">+</button>
                                                </div>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            RM <span class="unit-price-val">{{ $product->unit_price }}</span>
                                        </td>
                                        <td>
                                            RM <span class="product-price-val">{{ number_format((float) $product->price, 2, '.', '') }}</span>
                                        </td>
                                        <td class="text-center">
                                            <a class="btn btn-danger remove-button" data-id="{{ $product->cart_product_id }}">
                                                <i class="fa fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8">
                                            {{ __('customers.pos.cart_empty') }} <a href='{{ $portal['products_url'] }}'>{{ __('customers.pos.continue_shopping') }}</a>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if (count($products))
                                <tfoot>
                                    <tr>
                                        <td colspan="5"></td>
                                        <td>{{ __('orders.total') }}</td>
                                        <td colspan="2">
                                            RM <span id="total-price-value">{{ $total }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="8">
                                            <div class="alert alert-light border mb-3">
                                                @include('partials.subject_to_availability')
                                            </div>
                                            <div class="d-flex justify-content-end">
                                                <a href="{{ $portal['checkout_url'] }}" class="btn btn-primary px-5">{{ __('customers.pos.proceed_checkout') }}</a>
                                            </div>
                                        </td>
                                    </tr>
                                </tfoot>
                            @endif
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
                Swal.fire({
                    title: 'Confirm',
                    text: @json(__('customers.pos.remove_confirm')),
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = "{{ $portal['remove_cart_url'] }}/" + cartProductId;
                    }
                });
            });

            function cartBillAmount(row) {
                var sellIn = row.data('sellIn') || 'weight';
                var qty = parseFloat(row.find('.quantity-input').val()) || 0;
                var weight = parseFloat(row.find('.weight-input').val()) || 0;

                if (sellIn === 'qty') {
                    return qty;
                }

                if (sellIn === 'qty_bill_weight' || sellIn === 'weight') {
                    return qty * weight;
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
                $('#total-price-value').text(total.toFixed(2));
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
                    success: function() { updatePrices(); }
                });
            }

            $(document).on('click', '.decrease-quantity', function() {
                var input = $(this).closest('.quantity-controls').find('.quantity-input');
                var current = parseInt(input.val());
                if (current > 1) input.val(current - 1).trigger('input');
            });
            $(document).on('click', '.increase-quantity', function() {
                var input = $(this).closest('.quantity-controls').find('.quantity-input');
                input.val(parseInt(input.val()) + 1).trigger('input');
            });
            $(document).on('click', '.decrease-weight', function() {
                var input = $(this).closest('.weight-controls').find('.weight-input');
                var current = parseFloat(input.val());
                if (current > 0.1) input.val(current - 1).trigger('input');
            });
            $(document).on('click', '.increase-weight', function() {
                var input = $(this).closest('.weight-controls').find('.weight-input');
                input.val(parseFloat(input.val()) + 1).trigger('input');
            });
            $('.quantity-input, .weight-input').on('input', function() {
                var $input = $(this);
                var raw = $input.val();

                if (!($input.hasClass('weight-input-optional') && raw === '')) {
                    if (raw === '' || parseFloat(raw) < 0.001) {
                        $input.val(0.001);
                    }
                }

                syncCartRow(this);
            });
            updatePrices();
        });
    </script>
@endsection
