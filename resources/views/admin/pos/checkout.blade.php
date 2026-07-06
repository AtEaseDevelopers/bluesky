@extends('layouts.pos')
@section('title', __('customers.pos.checkout'))
@section('content')

    <div class="row mb-5">
        <div class="col-md-8">
            <div class="card no-border shadow">
                <div class="card-body">
                    <h5 class="mb-4">{{ __('customers.pos.checkout') }}</h5>
                    <form action="{{ route('admin.pos.checkout.submit') }}" method="POST" class="form-wrapper">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="attn_name">{{ __('orders.attn_name') }} <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="attn_name" id="attn_name" value="{{ old('attn_name', $customer->attn_name) }}" placeholder="{{ __('orders.attn_name_placeholder') }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="attn_contact">{{ __('orders.attn_contact') }} <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="attn_contact" id="attn_contact" value="{{ old('attn_contact', $customer->attn_contact) }}" placeholder="{{ __('orders.attn_contact_placeholder') }}" required>
                                </div>
                            </div>
                        </div>

                        <h6 class="card-subtitle my-3 text-body-secondary">{{ __('customers.pos.delivery_info') }}</h6>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="billing_address">{{ __('customers.pos.delivery_address') }} <span class="text-danger">*</span></label>
                                    <textarea id="billing_address" name="billing_address" class="form-control" rows="3" placeholder="{{ __('orders.billing_address_placeholder') }}" required>{{ old('billing_address', $customer->billing_address) }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-2 mb-0">
                            <i class="fa fa-money" aria-hidden="true"></i>
                            <strong>{{ __('customers.pos.cod_notice_title') }}</strong>
                            {{ __('customers.pos.cod_notice') }}
                        </div>
                        <div class="alert alert-light border mt-3 mb-0">
                            @include('partials.subject_to_availability')
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-end flex-wrap gap-2">
                                    <a href="{{ $portal['cart_url'] }}" class="btn btn-outline-primary px-3">{{ __('customers.pos.nav_cart') }}</a>
                                    <button type="submit" name="checkout_action" value="place_order" class="btn btn-primary px-3">
                                        {{ __('customers.pos.place_order') }}
                                    </button>
                                    <button type="submit" name="checkout_action" value="make_payment" class="btn btn-success px-3">
                                        {{ __('customers.pos.make_payment') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card no-border shadow">
                <div class="card-body">
                    <h5 class="mb-4">{{ __('customers.pos.order_summary') }}</h5>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>{{ __('orders.product') }}</th>
                                <th>{{ __('orders.unit_price_short') }}</th>
                                <th>{{ __('orders.qty') }}/{{ __('orders.weight') }}</th>
                                <th>{{ __('orders.total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($products as $product)
                                <tr>
                                    <td>
                                        <strong>{{ $product->name }}</strong><br>
                                        @foreach($product->options as $opt => $opt_itm)
                                            {{ $opt }}: {{ $opt_itm }}<br>
                                        @endforeach
                                    </td>
                                    <td align="right">{{ $product->unit_price }}</td>
                                    <td>{{ $product->quantity ?? ($product->weight . ' KG') }}</td>
                                    <td align="right">{{ number_format((float) $product->price, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3">{{ __('orders.total') }}</td>
                                <td align="right"><strong>{{ $total }}</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

@endsection
