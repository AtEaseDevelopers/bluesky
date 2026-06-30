@if ($product_option['product_option'])
    @php $cart_product_options = $cart_product_options ?? []; @endphp
    <div class="mb-4">
        @foreach($product_option['product_option'] as $option => $option_items)
            @php
                $isSituation = \App\OrderFieldSetting::isSituationOption($option);
                $selectedValue = old('product_option.'.$option, ($cart_product_options[$option] ?? null));
                if ($isSituation && ! $selectedValue && ! empty($option_items[0])) {
                    $selectedValue = $option_items[0];
                }
            @endphp
            <div class="form-group mb-3">
                <label class="mb-2" for="productOption-{{ $option }}">{{ $option }}
                    @if($product_option['product_option_mandatory'][$option])
                        <span class="text-danger ml-1">*</span>
                    @endif
                </label>
                @if ($isSituation)
                    <input type="hidden" name="product_option[{{ $option }}]" id="productOption-{{ $option }}" value="{{ old('product_option.'.$option, $selectedValue) }}" {{ $product_option['product_option_mandatory'][$option] ? 'required' : '' }}>
                    <div class="d-flex flex-wrap gap-2 situation-btn-group" data-target="productOption-{{ $option }}">
                        @foreach($option_items as $opt_itm)
                            <button type="button" class="btn btn-sm btn-outline-primary situation-preset-btn {{ old('product_option.'.$option, $selectedValue) === $opt_itm ? 'active' : '' }}" data-value="{{ $opt_itm }}">
                                {{ ucfirst($opt_itm) }}
                            </button>
                        @endforeach
                    </div>
                @else
                    <select id="productOption-{{ $option }}" class="form-select" name="product_option[{{ $option }}]"{{ $product_option['product_option_mandatory'][$option]? " required" : "" }}>
                        <option value="">{{ __('product.form.select-default') }} {{ $product_option['product_option_mandatory'][$option]? "" : " (Optional)" }}</option>
                        @foreach($option_items as $opt_itm)
                            <option value="{{ $opt_itm }}" {{ old('product_option.'.$option, $selectedValue) == $opt_itm ? "selected" : "" }}>
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
@endif

<p class="mb-3"><span class="badge bg-success">{{ $product->stock_label }}</span></p>

@if ($product->sell_in == 'qty')
    <div class="mb-4">
        <label class="mb-2" for="quantity">Quantity</label>
        <div class="btn-group w-100" role="group">
            <button type="button" class="btn btn-outline-primary btn-minus" disabled>
                <i class="fa fa-minus" aria-hidden="true"></i>
            </button>
            <input type="number" class="form-control px-4" id="quantity" name="quantity" value="1" min="0.001" max="{{ $product->storefront_available_amount }}" step="0.001">
            <button type="button" class="btn btn-outline-primary btn-plus">
                <i class="fa fa-plus" aria-hidden="true"></i>
            </button>
        </div>
    </div>
@elseif ($product->sell_in == 'qty_bill_weight')
    <div class="mb-4">
        <label class="mb-2" for="quantity">Quantity</label>
        <div class="btn-group w-100" role="group">
            <button type="button" class="btn btn-outline-primary btn-minus" disabled>
                <i class="fa fa-minus" aria-hidden="true"></i>
            </button>
            <input type="number" class="form-control px-4" id="quantity" name="quantity" value="1" min="0.001" max="{{ $product->storefront_available_amount }}" step="0.001">
            <button type="button" class="btn btn-outline-primary btn-plus">
                <i class="fa fa-plus" aria-hidden="true"></i>
            </button>
        </div>
    </div>
    <div class="mb-4">
        <label class="mb-2" for="weight">Weight ({{ $product->uom_name ?? 'KG' }})</label>
        @include('partials.weight_presets', ['targetId' => 'weight', 'uomLabel' => $product->uom_name ?? 'KG', 'presets' => $product->weightPresetsList()])
        <div class="btn-group w-100" role="group">
            <button type="button" class="btn btn-outline-primary btn-minus-weight" disabled>
                <i class="fa fa-minus" aria-hidden="true"></i>
            </button>
            <input type="number" class="form-control px-4" id="weight" name="weight" value="1" min="0.001" step="0.001">
            <button type="button" class="btn btn-outline-primary btn-plus-weight">
                <i class="fa fa-plus" aria-hidden="true"></i>
            </button>
        </div>
    </div>
@else
    <div class="mb-4">
        <label class="mb-2" for="weight">Order Qty ({{ $product->uom_name ?? 'KG' }})</label>
        @include('partials.weight_presets', ['targetId' => 'weight', 'uomLabel' => $product->uom_name ?? 'KG', 'presets' => $product->weightPresetsList()])
        <div class="btn-group w-100" role="group">
            <button type="button" class="btn btn-outline-primary btn-minus-weight" disabled>
                <i class="fa fa-minus" aria-hidden="true"></i>
            </button>
            <input type="number" class="form-control px-4" id="weight" name="weight" value="1" min="0.001" max="{{ $product->storefront_available_amount }}" step="0.001">
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
