@extends('layouts.member')
@section('title', 'Product Details')
@section('content')

    <div class="row mb-5">
        <div class="col-md-4">
            <div class="card no-border shadow">
                <div class="card-body">
                    <img src="{{ $product->image_url }}" onError="this.onerror=null;this.src='{{ asset('assets/images/product-default.jpg') }}';" class="img-fluid w-100" alt="{{ $product->name }}">
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card no-border shadow">
                <div class="card-body">
                    <h4 class="mb-4">{{ $product->name }} {{ $product->sku? "($product->sku) " : "" }}</h2>
                    @if ($product->description)
                        <div class="product-info mb-4">
                            <h6>Description:</h6>
                            <p class="card-text">{{ ucfirst($product->description) }}</p>
                        </div>
                    @endif
                    @if (Auth::guard('web')->user()->price_permission)
                        <div class="mb-4">
                            <h6>Price:</h6>
                            @if($product->original_price > $product->price)
                                <p class="card-text original-price">{{ $product->original_price_label }}</p>
                            @endif
                            <p class="card-text discounted-price">{{ $product->price_label }}</p>
                        </div>
                    @endif
                    <div class="mb-4">
                        <h6>Available Stock:</h6>
                        <p class="card-text">
                            <span class="badge bg-success">{{ $product->stock_label }}</span>
                        </p>
                        <small class="text-muted">Same as inventory Stock Balance — quantity in {{ $product->uom_name ?? 'KG' }}.</small>
                    </div>
                    <hr class="w-50">
                    <form method="POST" action="{{ route('member.add-to-cart', encrypt($product->id)) }}" enctype="multipart/form-data" class="form-wrapper">
                        @csrf
                        <div class="mb-4">
                            @foreach($product->product_option['product_option'] as $option => $option_items)
                                @php
                                    $isSituation = \App\OrderFieldSetting::isSituationOption($option);
                                    $selectedValue = old('product_option.'.$option, optional($product->cart_product_option)->option_item);
                                    if ($isSituation && ! $selectedValue && ! empty($option_items[0])) {
                                        $selectedValue = $option_items[0];
                                    }
                                @endphp
                                <div class="form-group mb-3">
                                    <label class="mb-2" for="productOption-{{ $option }}">{{ $option }}
                                        @if($product->product_option['product_option_mandatory'][$option])
                                            <span class="text-danger ml-1">*</span>
                                        @endif
                                    </label>
                                    @if ($isSituation)
                                        <input type="hidden" name="product_option[{{ $option }}]" id="productOption-{{ $option }}" value="{{ $selectedValue }}" {{ $product->product_option['product_option_mandatory'][$option] ? 'required' : '' }}>
                                        <div class="d-flex flex-wrap gap-2 situation-btn-group" data-target="productOption-{{ $option }}">
                                            @foreach($option_items as $opt_itm)
                                                <button type="button" class="btn btn-sm btn-outline-primary situation-preset-btn {{ $selectedValue === $opt_itm ? 'active' : '' }}" data-value="{{ $opt_itm }}">
                                                    {{ ucfirst($opt_itm) }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @else
                                        <select id="productOption-{{ $option }}" class="form-select" name="product_option[{{ $option }}]"{{ $product->product_option['product_option_mandatory'][$option]? " required" : "" }}>
                                            <option value="">{{ __('product.form.select-default') }} {{ $product->product_option['product_option_mandatory'][$option]? "" : " (Optional)" }}</option>
                                            @foreach($option_items as $opt_itm)
                                                <option value="{{ $opt_itm }}" {{ $selectedValue == $opt_itm ? "selected" : "" }}>
                                                    {{ ucfirst($opt_itm) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @endif
                                    @if ($errors->has('product_option.'.$option))
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $errors->first('product_option.'.$option) }}</strong>
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        @if ($product->sell_in == 'qty')
                            <div class="mb-4">
                                <label class="mb-2" for="quantity">Quantity</label>
                                <span class="text-danger"> *</span>
                                <input type="number" class="form-control @error('quantity') is-invalid @enderror" id="quantity" name="quantity" value="{{ $product->added_to_cart? $product->added_to_cart->quantity : 1 }}" min="0.001" max="{{ $product->storefront_available_amount }}" step="0.001">
                                @error('quantity')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        @elseif ($product->sell_in == 'qty_bill_weight')
                            <div class="mb-4">
                                <label class="mb-2" for="quantity">Quantity</label>
                                <span class="text-danger"> *</span>
                                <input type="number" class="form-control @error('quantity') is-invalid @enderror" id="quantity" name="quantity" value="{{ old('quantity', $product->added_to_cart? $product->added_to_cart->quantity : 1) }}" min="0.001" max="{{ $product->storefront_available_amount }}" step="0.001">
                                @error('quantity')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                            <div class="mb-4">
                                <label class="mb-2" for="weight">Weight ({{ $product->uom_name ?? 'KG' }})</label>
                                <span class="text-danger"> *</span>
                                <input type="number" class="form-control @error('weight') is-invalid @enderror" id="weight" name="weight" value="{{ old('weight', $product->added_to_cart? $product->added_to_cart->weight : 1) }}" min="0.001" step="0.001">
                                @error('weight')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        @else
                            <div class="mb-4">
                                <label class="mb-2" for="weight">Order Qty ({{ $product->uom_name ?? 'KG' }})</label>
                                <span class="text-danger"> *</span>
                                <input type="number" class="form-control @error('weight') is-invalid @enderror" id="weight" name="weight" value="{{ $product->added_to_cart? $product->added_to_cart->weight : 1 }}" min="0.001" max="{{ $product->storefront_available_amount }}" step="0.001">
                                @error('weight')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        @endif
                        <div class="mb-4">
                            <label class="mb-2" for="remark">Remark</label>
                            <textarea class="form-control @error('remark') is-invalid @enderror" id="remark" rows="3" name="remark"></textarea>
                            @error('remark')
                                <span class="text-danger" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4">
                            <button type="submit" class="btn btn-outline-success">
                                <i class="fa fa-shopping-cart me-2"></i> {{ $product->added_to_cart ? "Update Cart" : "Add to Cart" }}
                                <div class="spinner-border spinner-border-sm d-none" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
