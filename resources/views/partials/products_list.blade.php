@foreach ($products as $product)
    @php
        $product_option = $product->product_option;
    @endphp
    <div class="card products-card mb-3" id="product-card-{{ $product->id }}" data-id="{{ $product->id }}" data-name="{{ $product->name }}" data-sku="{{ $product->sku }}">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <div class="d-flex">
                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" onError="this.onerror=null;this.src='{{ asset('assets/images/product-default.jpg') }}';" class="me-3" width="120">
                    <div>
                        <h5>{{ $product->name }}</h5>
                        <p>Sku: {{ $product->sku }}</p>
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
                        <label class="form-check-label" for="product_{{ $product->id }}">Select</label>
                    </div>
                </div>
            </div>
            <div class="product-option-section d-none" id="product-option-{{ $product->id }}">
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
                        @endphp
                        <div class="form-group mb-3">
                            <label class="mb-2" for="productOption-{{ $type }}">{{ $type }}</label>
                            @if ($is_required)
                                <span class="text-danger"> *</span>
                            @endif
                            <select id="productOption-{{ $type }}" class="form-select" name="{{ $type }}" {{ $is_required ? 'required' : '' }}>
                                <option value="">Choose {{ $type }}...</option>
                                @foreach ($options as $option)
                                    <option value="{{ $option }}">{{ ucfirst($option) }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                @endif

                @if ($product->sell_in == 'qty')
                <div class="form-group mb-3">
                    <label class="mb-2" for="productQuantity">Quantity</label>
                    <span class="text-danger"> *</span>
                    <input type="number" class="form-control col-3" id="productQuantity_{{ $product->id }}" name="quantity" value="1" min="0" step="0.10">
                </div>
@else
    <div class="form-group mb-3">
        <label class="mb-2" for="productQuantity">Weight/KG</label>
        <span class="text-danger"> *</span>
        <input type="number" class="form-control col-3" id="productWeight_{{ $product->id }}" name="weight" value="1" min="0" step="0.10">
    </div>
@endif


                <!--<div class="form-group mb-3">-->
                <!--    <label class="mb-2" for="nos">Nos</label>-->
                <!--    <textarea class="form-control" id="nos_{{ $product->id }}" name="nos"></textarea>-->
                <!--</div>-->
                <div class="form-group mb-3">
                    <label class="mb-2" for="productRemark">Remark</label>
                    <textarea class="form-control" id="productRemark_{{ $product->id }}" name="remark"></textarea>
                </div>
            </div>
        </div>
    </div>
@endforeach
