@extends('layouts.admin')
@section('title', 'Edit Product')
@section('content')

    <div class="row">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="mb-4">Edit Product</h5>
                    <form method="POST" action="{{ route('admin.products.update', encrypt($product->id)) }}" enctype="multipart/form-data" class="form-wrapper">
                        @csrf

                        @if ($product->image_url)
                            <div class="p-2 mb-4">
                                <img width="150" src="{{ $product->image_url }}" />
                            </div>
                        @endif
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="product_category_id">Select Category</label>
                                    <span class="text-danger"> *</span>
                                    <select class="form-select @error('product_category_id') is-invalid @enderror"  id="product_category_id" name="product_category_id">
                                        <option value="">Choose...</option>
                                        @foreach ($product_categories as $category)
                                            <option value="{{ $category->id }}" {{ $product->product_category_id == $category->id ? 'selected' : '' }}>
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
                                    <label class="mb-2" for="uom_id">Select UOM</label>
                                    <span class="text-danger"> *</span>
                                    <select class="form-select @error('uom_id') is-invalid @enderror"  id="uom_id" name="uom_id">
                                        <option value="">Choose...</option>
                                        @foreach ($uoms as $uom)
                                            <option value="{{ $uom->id }}" {{ $product->uom_id == $uom->id ? 'selected' : '' }}>
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
                                    <label class="mb-2" for="productImage">Product Image</label>
                                    <input type="file" class="form-control" name="images" id="productImage" accept="image/*">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="productName">Product Name</label>
                                    <span class="text-danger"> *</span>
                                    <input type="text" class="form-control" name="name" id="productName" value="{{ old('name')? : $product->name }}" placeholder="Enter product name" required>
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
                                    <label class="mb-2" for="productDescription">Product Description</label>
                                    <textarea class="form-control" name="description" id="productDescription" value="" placeholder="Enter product description"
                                        >{{ old('description')? : $product->description }}</textarea>
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
                                    <label class="mb-2" for="productSku">SKU</label>
                                    <input type="text" class="form-control" name="sku" id="productSku" value="{{ old('sku')? : $product->sku }}" placeholder="Enter product SKU">
                                    @if ($errors->has('sku'))
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $errors->first('sku') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="productPrice">Price</label>
                                    <span class="text-danger"> *</span>
                                    <input type="number" step="0.01" class="form-control" name="price" id="productPrice" value="{{ old('price')? : $product->price }}" placeholder="Enter product price" required>
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
                                    <label class="mb-2" for="weight">Weight</label>
                                    <span> (in KG)</span>
                                    <input type="number" step="0.01" min="0" class="form-control" name="weight" id="weight" value="{{ $product->weight }}" placeholder="Enter product weight">
                                    @if ($errors->has('weight'))
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $errors->first('weight') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="productStatus">Status</label>
                                    <span class="text-danger"> *</span>
                                    <select id="productStatus" class="form-select" name="status">
                                        <option value="active"{{ (old('status')? : $product->status) == 'active'? " selected" : "" }}>{{ __('product.status.active') }}</option>
                                        <option value="inactive"{{ (old('status')? : $product->status) == 'inactive'? " selected" : "" }}>{{ __('product.status.inactive') }}</option>
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
                                    <label class="mb-2" for="remark">Remark</label>
                                    <textarea class="form-control" name="remark" id="remark" value="" placeholder="Enter remark"
                                        >{{ old('remark')? : $product->remark }}</textarea>
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
                                    <textarea class="form-control" name="nos" id="nos" value="" placeholder="Enter Nos"
                                        >{{ old('nos')? : $product->nos }}</textarea>
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
                                    <label class="mb-2">Sell In</label>
                                    <span class="text-danger"> *</span>
                                    <div class="col-md-12 d-flex gap-4">
                                        <div>
                                            <input name="sell_in" id="sell_in_qty" type="radio" value="qty" {{ $product->sell_in == 'qty' ? 'checked' : '' }} />
                                            <label class="mb-2" for="sell_in_qty">Quantity</label>
                                        </div>
                                        <div>
                                            <input name="sell_in" id="sell_in_weight" type="radio" value="weight" {{ $product->sell_in == 'weight' ? 'checked' : '' }} />
                                            <label class="mb-2" for="sell_in_weight">Weight</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 d-flex gap-4">
                                <div class="mb-4">
                                    <input name="show_weight" id="show_weight" type="checkbox" {{ $product->show_weight == true ? 'checked' : '' }} />
                                    <label class="mb-2" for="show_weight">Show Weight In Report</label>
                                </div>
                                <div class="mb-4">
                                    <input name="show_qty" id="show_qty" type="checkbox" {{ $product->show_qty == true ? 'checked' : '' }} />
                                    <label class="mb-2" for="show_qty">Show Quantity In Report</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <h5 class="mb-3">Category Pricing</h5>
                                    <div class="table-responsive">
                                        <table class="table bottom-bordered">
                                            <thead>
                                                <tr>
                                                    <th width="50">No</th>
                                                    <th>Customer Category</th>
                                                    <th>Price</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($customer_categories as $category)
                                                    @php
                                                        $categoryPrice = $category_prices->where('category_name', $category)->first();
                                                    @endphp
                                                    <tr>
                                                        <td>{{ $loop->iteration }}</td>
                                                        <td>{{ $category }}</td>
                                                        <td>
                                                            <input type="number" step="0.01" min="0" class="form-control" name="category_prices[{{ $category }}]" placeholder="Leave empty for default price" value="{{ $categoryPrice->price ?? old('category_prices.' . $category) }}">
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
                                        <label class="mb-2">Product Options</label><br/>
                                        <a class="btn btn-outline-primary mr-auto" type="button" data-bs-toggle="modal" data-bs-target="#addOptionModal">
                                            <i class="fa fa-plus" aria-hidden="true"></i> Add Option
                                        </a>
                                        <div class="row product-option mt-4"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-end">
                                    <a href="{{ route('admin.products') }}" class="btn btn-secondary me-2 mb-1">Back</a>
                                    <button type="submit" class="btn btn-primary mb-1">
                                        Save
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
                    <h5 class="modal-title" id="addOptionModalLabel">Add Option Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label for="optionName">Options Name</label>
                    <input type="text" class="form-control" id="optionName" placeholder="Enter option name" required>
                    <hr/>
                    <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="isMandatory" checked="checked">
                    <label class="form-check-label" for="isMandatory">Is Mandatory Option</label>
                    </div>
                    <hr/>
                    <label for="optionItems">Options Items (Selection)</label>
                    <textarea class="form-control" id="optionItems" placeholder="Enter option items, separate with comma(,)"
                        required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button type="button" class="btn btn-primary" id="saveOptionType">Add</button>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('script')

    <script>
        $('input[name="sell_in"]').on('change', function() {
            let val = $('input[name="sell_in"]:checked').val()

            if (val == 'qty') {
                $('input[name="weight"]').val(null)
                $('input[name="weight"]').prop('disabled', false)
            } else if (val == 'weight') {
                $('input[name="weight"]').val(null)
                $('input[name="weight"]').prop('disabled', true)
            }
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
                    alert("Name is required!");
                    return false;
                }
                else if(optionItems == ""){
                    alert("Option Items (Selection) is required!");
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


            var productOption = {!! json_encode($product->product_option) !!};
            $.each(productOption.product_option, function(optionName, optionItems){
                var optionItemsArr = optionItems.split(',');
                var optionItemStr = "";
                $.each(optionItemsArr, function(index, value) {
                    optionItemStr += (value.trim() + "<br/>");
                });
                var isMandatory = productOption.product_option_mandatory[optionName];
                var option_html = generateProductOption(optionName, isMandatory, optionItems, optionItemStr);
                $(".product-option").append(option_html);
            })

            function generateProductOption(optionName, isMandatory, optionItems, optionItemStr){
                return `<div class="col-md-3 each-product-option mb-4">
                            <div class="card shadow no-border">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-baseline flex-wrap gap-3">
                                        <div>
                                            <input type="hidden" name="product_option[`+ optionName +`]" value="`+ optionItems.trim() +`" />
                                            <input type="hidden" name="product_option_mandatory[`+ optionName +`]" value="`+ ($('#isMandatory').is(':checked')? "1" : "" ) +`" />
                                            <p><b>`+ optionName +`</b>`+ (isMandatory? "" : " (Optional)" ) +`<br/>`+ optionItemStr +`</p>
                                        </div>
                                        <a type="button" title="Remove `+ optionName +`" class="remove-item"><i class="fa fa-trash" aria-hidden="true"></i></a>
                                    </div>
                                </div>
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
