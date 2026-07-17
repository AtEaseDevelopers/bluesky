@foreach ($products as $product)
    @php
        $product_option = $product->product_option;
        $uomLabel = $product->uom->uom_name ?? 'KG';
    @endphp
    <div class="card products-card mb-3" id="product-card-{{ $product->id }}" data-id="{{ $product->id }}" data-name="{{ $product->name }}" data-sku="{{ $product->sku }}" data-sell-in="{{ $product->sell_in }}">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <div class="d-flex">
                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" onError="this.onerror=null;this.src='{{ asset('assets/images/product-default.jpg') }}';" class="me-3" width="120">
                    <div>
                        <h5>{{ $product->name }}</h5>
                        <p>{{ __('orders.sku_label', ['sku' => $product->sku]) }}</p>
                        @if ($product->price < $product->original_price)
                            <p>RM {{ $product->original_price }} -> RM {{ $product->price }}</p>
                        @else
                            <p>RM {{ $product->price }}</p>
                        @endif
                    </div>
                </div>
                <div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input products toggle-product-options" id="product_{{ $product->id }}" value="{{ $product->id }}" {{ in_array($product->id, $selected_ids) ? 'checked' : '' }}>
                        <label class="form-check-label" for="product_{{ $product->id }}">{{ __('orders.select') }}</label>
                    </div>
                </div>
            </div>
            <div class="product-option-section {{ in_array($product->id, $selected_ids) ? '' : 'd-none' }}" id="product-option-{{ $product->id }}">
                <input type="hidden" name="product_id" value="{{ $product->id }}" />
                <input type="hidden" name="product_name" value="{{ $product->name }}" />
                <input type="hidden" name="price" value="{{ $product->price }}" />
                @if ($product_option)
                    @php
                        $product_options = $product_option['product_option'];
                    @endphp
                    @foreach ($product_options as $type => $options)
                        @php
                            $is_required = isset($product_option['product_option_mandatory'][$type]);
                            $isSituation = \App\OrderFieldSetting::isSituationOption($type);
                        @endphp
                        <div class="form-group mb-3">
                            <label class="mb-2" for="productOption-{{ $product->id }}-{{ $type }}">{{ $type }}</label>
                            @if ($is_required)
                                <span class="text-danger"> *</span>
                            @endif
                            @if ($isSituation)
                                <input type="hidden" name="{{ $type }}" id="productOption-{{ $product->id }}-{{ $type }}" {{ $is_required ? 'required' : '' }}>
                                <div class="d-flex flex-wrap gap-2 situation-btn-group" data-target="productOption-{{ $product->id }}-{{ $type }}">
                                    @foreach ($options as $option)
                                        <button type="button" class="btn btn-sm btn-outline-primary situation-preset-btn" data-value="{{ $option }}">{{ ucfirst($option) }}</button>
                                    @endforeach
                                </div>
                            @else
                                <select id="productOption-{{ $product->id }}-{{ $type }}" class="form-select" name="{{ $type }}" {{ $is_required ? 'required' : '' }}>
                                    <option value="">{{ __('orders.choose_option', ['option' => $type]) }}</option>
                                    @foreach ($options as $option)
                                        <option value="{{ $option }}">{{ ucfirst($option) }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                    @endforeach
                @endif

                @if ($product->sell_in == 'qty')
                    <div class="form-group mb-3">
                        <label class="mb-2" for="productQuantity_{{ $product->id }}">{{ __('orders.quantity_label') }}</label>
                        <span class="text-danger"> *</span>
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn btn-outline-primary btn-adjust-qty" data-target="productQuantity_{{ $product->id }}" data-action="minus">
                                <i class="fa fa-minus"></i>
                            </button>
                            <input type="number" class="form-control text-center" id="productQuantity_{{ $product->id }}" name="quantity" value="1" min="0.001" step="0.001" required>
                            <button type="button" class="btn btn-outline-primary btn-adjust-qty" data-target="productQuantity_{{ $product->id }}" data-action="plus">
                                <i class="fa fa-plus"></i>
                            </button>
                        </div>
                    </div>
                @elseif ($product->sell_in == 'qty_bill_weight')
                    <div class="form-group mb-3">
                        <label class="mb-2" for="productQuantity_{{ $product->id }}">{{ __('orders.quantity_label') }}</label>
                        <span class="text-danger"> *</span>
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn btn-outline-primary btn-adjust-qty" data-target="productQuantity_{{ $product->id }}" data-action="minus">
                                <i class="fa fa-minus"></i>
                            </button>
                            <input type="number" class="form-control text-center" id="productQuantity_{{ $product->id }}" name="quantity" value="1" min="0.001" step="0.001" required>
                            <button type="button" class="btn btn-outline-primary btn-adjust-qty" data-target="productQuantity_{{ $product->id }}" data-action="plus">
                                <i class="fa fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="mb-2" for="productWeight_{{ $product->id }}">{{ __('product.estimated_weight', ['uom' => $uomLabel]) }} {{ __('product.optional') }}</label>
                        @include('partials.weight_presets', ['targetId' => 'productWeight_' . $product->id, 'uomLabel' => $uomLabel, 'presets' => $product->weightPresetsList()])
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn btn-outline-primary btn-adjust-qty" data-target="productWeight_{{ $product->id }}" data-action="minus">
                                <i class="fa fa-minus"></i>
                            </button>
                            <input type="number" class="form-control text-center" id="productWeight_{{ $product->id }}" name="weight" value="" min="0" step="0.001">
                            <button type="button" class="btn btn-outline-primary btn-adjust-qty" data-target="productWeight_{{ $product->id }}" data-action="plus">
                                <i class="fa fa-plus"></i>
                            </button>
                        </div>
                    </div>
                @elseif ($product->sell_in == 'weight')
                    <div class="form-group mb-3">
                        <label class="mb-2" for="productWeight_{{ $product->id }}">{{ __('orders.order_qty_uom', ['uom' => $uomLabel]) }}</label>
                        <span class="text-danger"> *</span>
                        @include('partials.weight_presets', ['targetId' => 'productWeight_' . $product->id, 'uomLabel' => $uomLabel, 'presets' => $product->weightPresetsList()])
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn btn-outline-primary btn-adjust-qty" data-target="productWeight_{{ $product->id }}" data-action="minus">
                                <i class="fa fa-minus"></i>
                            </button>
                            <input type="number" class="form-control text-center" id="productWeight_{{ $product->id }}" name="weight" value="1" min="0.001" step="0.001" required>
                            <button type="button" class="btn btn-outline-primary btn-adjust-qty" data-target="productWeight_{{ $product->id }}" data-action="plus">
                                <i class="fa fa-plus"></i>
                            </button>
                        </div>
                    </div>
                @endif

                <div class="form-group mb-3">
                    <label class="mb-2" for="productRemark_{{ $product->id }}">{{ __('orders.remark') }}</label>
                    <textarea class="form-control" id="productRemark_{{ $product->id }}" name="remark"></textarea>
                </div>
            </div>
        </div>
    </div>
@endforeach
