@extends('layouts.admin')
@section('title', __('product.add'))
@section('content')

    <div class="row">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="mb-4">{{ __('product.add') }}</h5>
                    <form method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data" class="form-wrapper">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="product_category_id">{{ __('product.select_category') }}</label>
                                    <span class="text-danger"> *</span>
                                    <select class="form-select @error('product_category_id') is-invalid @enderror"  id="product_category_id" name="product_category_id">
                                        <option value="">{{ __('orders.choose') }}</option>
                                        @foreach ($product_categories as $category)
                                            <option value="{{ $category->id }}" {{ old('product_category_id') == $category->id ? 'selected' : '' }}>
                                                {{ $category->category_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('product_category_id')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="uom_id">{{ __('product.select_uom') }}</label>
                                    <span class="text-danger"> *</span>
                                    <select class="form-select @error('uom_id') is-invalid @enderror"  id="uom_id" name="uom_id">
                                        <option value="">{{ __('orders.choose') }}</option>
                                        @foreach ($uoms as $uom)
                                            <option value="{{ $uom->id }}" {{ old('uom_id') == $uom->id ? 'selected' : '' }}>
                                                {{ $uom->uom_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('uom_id')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="productImage">{{ __('product.product_image') }}</label>
                                    <input type="file" class="form-control" name="images" id="productImage" accept="image/*">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="productName">{{ __('product.product_name') }}</label>
                                    <span class="text-danger"> *</span>
                                    <input type="text" class="form-control" name="name" id="productName" value="{{ old('name') }}" placeholder="{{ __('product.placeholder.product_name') }}" required>
                                    @if ($errors->has('name'))
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $errors->first('name') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <label class="mb-2" for="productDescription">{{ __('product.product_description') }}</label>
                                    <textarea class="form-control" name="description" id="productDescription" value="{{ old('description') }}" placeholder="{{ __('product.placeholder.product_description') }}"
                                        ></textarea>
                                    @if ($errors->has('description'))
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $errors->first('description') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="productSku">{{ __('product.sku') }}</label>
                                    <input type="text" class="form-control" name="sku" id="productSku" value="{{ old('sku') }}" placeholder="{{ __('product.placeholder.product_sku') }}">
                                    @if ($errors->has('sku'))
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $errors->first('sku') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="productPrice">{{ __('product.default_price') }}</label>
                                    <span class="text-danger"> *</span>
                                    <input type="number" step="0.01" class="form-control" name="price" id="productPrice" value="{{ old('price') }}" placeholder="{{ __('product.placeholder.default_price') }}" required>
                                    <small class="text-muted">{{ __('product.help.default_price') }}</small>
                                    @if ($errors->has('price'))
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $errors->first('price') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="weight">{{ __('product.weight') }}</label>
                                    <span>{{ __('product.weight_in_kg') }}</span>
                                    <input type="number" step="0.01" min="0" class="form-control" name="weight" id="weight" value="{{ old('weight') }}" placeholder="{{ __('product.placeholder.product_weight') }}">
                                    @if ($errors->has('weight'))
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $errors->first('weight') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="productStatus">{{ __('product.status_label') }}</label>
                                    <span class="text-danger"> *</span>
                                    <select id="productStatus" class="form-select" name="status">
                                        <option value="active"{{ old('status') == 'active'? " selected" : "" }}>{{ __('product.status.active') }}</option>
                                        <option value="inactive"{{ old('status') == 'inactive'? " selected" : "" }}>{{ __('product.status.inactive') }}</option>
                                    </select>
                                    @if ($errors->has('status'))
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $errors->first('status') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <label class="mb-2" for="remark">{{ __('product.remark') }}</label>
                                    <textarea class="form-control" name="remark" id="remark" value="{{ old('remark') }}" placeholder="{{ __('product.placeholder.remark') }}"
                                        ></textarea>
                                    @if ($errors->has('remark'))
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $errors->first('remark') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="row d-none">
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <label class="mb-2" for="nos">Nos</label>
                                    <textarea class="form-control" name="nos" id="nos" value="{{ old('nos') }}" placeholder="Enter Nos"
                                        ></textarea>
                                    @if ($errors->has('nos'))
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $errors->first('nos') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <label class="mb-2">{{ __('product.sell_in') }}</label>
                                    <span class="text-danger"> *</span>
                                    <div class="col-md-12 d-flex gap-4">
                                        <div>
                                            <input name="sell_in" id="sell_in_qty" type="radio" value="qty" />
                                            <label class="mb-2" for="sell_in_qty">{{ __('product.sell_in_qty') }}</label>
                                        </div>
                                        <div>
                                            <input name="sell_in" id="sell_in_weight" type="radio" value="weight" />
                                            <label class="mb-2" for="sell_in_weight">{{ __('product.sell_in_weight') }}</label>
                                        </div>
                                        <div>
                                            <input name="sell_in" id="sell_in_qty_bill_weight" type="radio" value="qty_bill_weight" />
                                            <label class="mb-2" for="sell_in_qty_bill_weight">{{ __('product.sell_in_qty_bill_weight') }}</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row" id="weight-presets-wrap">
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <label class="mb-2" for="weight_presets">{{ __('product.weight_presets') }}</label>
                                    <textarea class="form-control @error('weight_presets') is-invalid @enderror" name="weight_presets" id="weight_presets" rows="3" placeholder="1, 1.5, 2, 2.5, 3">{{ old('weight_presets') }}</textarea>
                                    <small class="text-muted">{{ __('product.help.weight_presets') }}</small>
                                    @error('weight_presets')
                                        <span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <h5 class="mb-1">{{ __('product.category_pricing') }}</h5>
                                    <p class="text-muted small mb-3">{{ __('product.category_pricing_help') }}</p>
                                    <div class="table-responsive">
                                        <table class="table bottom-bordered">
                                            <thead>
                                                <tr >
                                                    <th width="50">{{ __('product.no') }}</th>
                                                    <th>{{ __('product.customer_category') }}</th>
                                                    <th>{{ __('product.price_per_uom') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($customer_categories as $category)
                                                    <tr>
                                                        <td>{{ $loop->iteration }}</td>
                                                        <td>{{ $category }}</td>
                                                        <td>
                                                            <input type="number" step="0.01" min="0" class="form-control" name="category_prices[{{ $category }}]" placeholder="{{ __('product.placeholder.category_price') }}" value="{{ old('category_prices.' . $category) }}">
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <div class="product-option-group my-3">
                                        <label class="mb-2">{{ __('product.product_options') }}</label><br/>
                                        <a class="btn btn-outline-primary mr-auto" type="button" data-bs-toggle="modal" data-bs-target="#addOptionModal">
                                            <i class="fa fa-plus" aria-hidden="true"></i> {{ __('product.add_option') }}
                                        </a>
                                        <div class="container product-option mt-3"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-end">
                                    <a href="{{ route('admin.products') }}" class="btn btn-secondary me-2 mb-1">{{ __('ui.back') }}</a>
                                    <button type="submit" class="btn btn-primary mb-1">
                                        {{ __('ui.save') }}
                                        <div class="spinner-border spinner-border-sm d-none" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addOptionModal" tabindex="-1" aria-labelledby="addOptionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addOptionModalLabel">{{ __('product.add_option_type') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
                </div>
                <div class="modal-body">
                    <label class="mb-2" for="optionName">{{ __('product.option_name') }}</label>
                    <input type="text" class="form-control" id="optionName" placeholder="{{ __('product.placeholder.option_name') }}" required>
                    <hr/>
                    <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="isMandatory" checked="checked">
                    <label class="form-check-label" for="isMandatory">{{ __('product.mandatory_option') }}</label>
                    </div>
                    <hr/>
                    <label class="mb-2" for="optionItems">{{ __('product.option_items') }}</label>
                    <textarea class="form-control" id="optionItems" placeholder="{{ __('product.placeholder.option_items') }}"
                        required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}">{{ __('ui.close') }}</button>
                    <button type="button" class="btn btn-primary" id="saveOptionType">{{ __('ui.add') }}</button>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('script')

    <script>
        $('input[name="sell_in"]').on('change', function() {
            let val = $('input[name="sell_in"]:checked').val()

            if (val == 'qty' || val == 'qty_bill_weight') {
                $('input[name="weight"]').val(null)
                $('input[name="weight"]').prop('disabled', false)
            } else if (val == 'weight') {
                $('input[name="weight"]').val(null)
                $('input[name="weight"]').prop('disabled', true)
            }

            $('#weight-presets-wrap').toggle(val === 'weight' || val === 'qty_bill_weight')
        })

        $(document).ready(function(){
            $('input[name="sell_in"]').trigger('change')

            $('#addOptionModal').on('shown.bs.modal', function (e) {
                $('#optionName').val("");
                $('#optionItems').val("");
                $('#isMandatory').prop("checked", true);
            });

            $('#saveOptionType').click(function() {
                const optionName = $('#optionName').val();
                const optionItems = $('#optionItems').val();
                if(optionName == ""){
                    alert(@json(__('product.js.name_required')));
                    return false;
                }
                else if(optionItems == ""){
                    alert(@json(__('product.js.option_items_required')));
                    return false;
                }

                var optionItemsArr = optionItems.split(',');
                var optionItemStr = "";
                $.each(optionItemsArr, function(index, value) {
                    optionItemStr += (value.trim() + "<br/>");
                });
                var isMandatory = $('#isMandatory').is(':checked');
                var option_html = generateProductOption(optionName, isMandatory, optionItems, optionItemStr);
                $(".product-option").append(option_html);

                // Close the modal
                $('#addOptionModal').modal('hide');
            });

            function generateProductOption(optionName, isMandatory, optionItems, optionItemStr){
                return `<div class="row each-product-option">
                            <div class="col-10 col-md-10">
                                <p><b>`+ optionName +`</b>`+ (isMandatory? "" : " {{ __('product.optional') }}" ) +`<br/>
                                `+ optionItemStr +`</p>
                            </div>
                            <input type="hidden" name="product_option[`+ optionName +`]" value="`+ optionItems.trim() +`" />
                            <input type="hidden" name="product_option_mandatory[`+ optionName +`]" value="`+ ($('#isMandatory').is(':checked')? "1" : "" ) +`" />
                            <div class="col-2 col-md-2">
                                <a type="button" title="Remove `+ optionName +`" class="remove-item"><i class="fa fa-trash" aria-hidden="true"></i></a>
                            </div>
                        </div>`;
            }

            $(document).on('click', '.remove-item', function() {
                // Handle the remove item action here
                // For example, you can remove the parent div when the remove button is clicked
                $(this).closest('.each-product-option').remove();
            });
        });
    </script>

@endsection
