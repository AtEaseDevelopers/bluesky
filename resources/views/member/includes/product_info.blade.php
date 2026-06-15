@if ($product_option['product_option'])
    <div class="mb-4">
        @foreach($product_option['product_option'] as $option => $option_items)
            <div class="form-group">
                <label class="mb-2" for="productOption-{{ $option }}">{{ $option }}
                    @if($product_option['product_option_mandatory'][$option])
                        <span class="text-danger ml-1">*</span>
                    @endif
                </label>
                <select id="productOption-{{ $option }}" class="form-select" name="product_option[{{ $option }}]"{{ $product_option['product_option_mandatory'][$option]? " required" : "" }}>
                    <option value="">{{ __('product.form.select-default') }} {{ $product_option['product_option_mandatory'][$option]? "" : " (Optional)" }}</option>
                    @foreach($option_items as $opt_itm)
                        <option value="{{ $opt_itm }}" {{ ($cart_product_option && $cart_product_option->option_item == $opt_itm) ? "selected" : "" }}>
                            {{ ucfirst($opt_itm) }}
                        </option>
                    @endforeach
                </select>
                @if ($errors->has('product_option.'.$option))
                    <span class="text-danger" role="alert">
                        <strong>{{ $errors->first('product_option.'.$option) }}</strong>
                    </span>
                @endif
            </div>
        @endforeach
    </div>
@endif

@if ($product->sell_in == 'qty')
    <div class="mb-4">
        <label class="mb-2" for="quantity">Quantity</label>
        <div class="btn-group w-100" role="group">
            <button type="button" class="btn btn-outline-primary btn-minus" disabled>
                <i class="fa fa-minus" aria-hidden="true"></i>
            </button>
            <input type="number" class="form-control px-4" id="quantity" name="quantity" value="1" min="1">
            <button type="button" class="btn btn-outline-primary btn-plus">
                <i class="fa fa-plus" aria-hidden="true"></i>
            </button>
        </div>
    </div>
@else
    <div class="mb-4">
        <label class="mb-2" for="weight">Weight</label>
        <div class="btn-group w-100" role="group">
            <button type="button" class="btn btn-outline-primary btn-minus-weight" disabled>
                <i class="fa fa-minus" aria-hidden="true"></i>
            </button>
            <input type="number" class="form-control px-4" id="weight" name="weight" value="1" step=".01">
            <button type="button" class="btn btn-outline-primary btn-plus-weight">
                <i class="fa fa-plus" aria-hidden="true"></i>
            </button>
        </div>
    </div>
@endif
<div>
    <label class="mb-2" for="remark">Remark</label>
    <textarea class="form-control" id="remark" rows="3" name="remark"></textarea>
</div>
