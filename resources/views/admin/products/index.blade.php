@extends('layouts.admin')
@section('title', __('product.manage'))
@section('content')
@php
    $admin = Auth::guard('web_admin')->user();
@endphp
    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card shadow no-border mb-0">
                <div class="card-body">
                    <h5 class="mb-4">{{ __('product.filter') }}</h5>
                    <form method="GET" class="form-wrapper">
                        <input type="hidden" name="price_range" id="priceRangeInput">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="uom_id">{{ __('product.select_uom') }}</label>
                                    <select class="form-select" id="uom_id" name="uom_id">
                                        <option value="">{{ __('orders.choose') }}</option>
                                        @foreach ($uoms as $uom)
                                            <option value="{{ $uom->id }}"
                                                {{ request('uom_id') == $uom->id ? 'selected' : '' }}>
                                                {{ $uom->uom_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="product_category_id">{{ __('product.select_category') }}</label>
                                    <select class="form-select" id="product_category_id" name="product_category_id">
                                        <option value="">{{ __('orders.choose') }}</option>
                                        @foreach ($product_categories as $category)
                                            <option value="{{ $category->id }}"
                                                {{ request('product_category_id') == $category->id ? 'selected' : '' }}>
                                                {{ $category->category_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="filterSku">{{ __('product.sku') }}</label>
                                    <input type="text" class="form-control" name="sku" id="filterSku"
                                        value="{{ $input['sku'] ?? '' }}" placeholder="{{ __('product.placeholder.sku') }}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="filterName">{{ __('product.name') }}</label>
                                    <input type="text" class="form-control" name="name" id="filterName"
                                        value="{{ $input['name'] ?? '' }}" placeholder="{{ __('product.placeholder.name') }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="filterStatus">{{ __('product.status_label') }}</label>
                                    <select class="form-select" name="status" id="filterStatus">
                                        <option value="">{{ __('ui.all') }}</option>
                                        <option value="active"{{ ($input['status'] ?? '') == 'active' ? ' selected' : '' }}>
                                            {{ __('product.status.active') }}</option>
                                        <option
                                            value="inactive"{{ ($input['status'] ?? '') == 'inactive' ? ' selected' : '' }}>
                                            {{ __('product.status.inactive') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="filterPrice">{{ __('product.price_range') }}</label>
                                    <div id="priceRange" class="px-3">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">{{ __('product.min') }}: RM<span
                                                    id="minPriceDisplay">{{ $input['min_price'] ?? 0 }}</span></span>
                                            <span class="text-muted">{{ __('product.max') }}: RM<span
                                                    id="maxPriceDisplay">{{ $input['max_price'] ?? 1000 }}</span></span>
                                        </div>
                                        <div class="range-slider-container">
                                            <input type="range" class="form-range" id="minPriceSlider" min="0"
                                                max="1000" step="0.01" value="{{ $input['min_price'] ?? 0 }}">
                                            <input type="range" class="form-range" id="maxPriceSlider" min="0"
                                                max="1000" step="0.01" value="{{ $input['max_price'] ?? 1000 }}">
                                        </div>
                                        <input type="hidden" name="min_price" id="minPriceInput"
                                            value="{{ $input['min_price'] ?? 0 }}">
                                        <input type="hidden" name="max_price" id="maxPriceInput"
                                            value="{{ $input['max_price'] ?? 1000 }}">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary me-3">{{ __('ui.search') }}</button>
                                <a href="{{ route('admin.products') }}">{{ __('ui.clear_search') }}</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="d-flex justify-content-end align-items-center flex-wrap gap-3">
                @if ($admin->canModule('products', 'edit'))
                    <a href="{{ url('/admin/product-daily-prices') }}" class="btn btn-primary me-1">
                        {{ __('product.set_daily_price') }}
                    </a>
                @endif
                @if ($admin->canModule('products', 'create'))
                    <a href="{{ route('admin.products.create') }}" class="btn btn-primary me-1">
                        {{ __('product.add') }}
                    </a>
                @endif
                <a href="{{ url('/admin/products/export' . $query_params) }}" class="btn btn-success">
                    <i class="fa fa-file-excel-o" aria-hidden="true"></i> {{ __('product.export_excel') }}
                </a>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card shadow no-border mb-0">
                <div class="card-body">
                    <h5 class="mb-4">{{ __('product.list') }}</h5>
                    <div class="table-responsive">
                        <table id="productTable" class="table table-bordered w-100">
                            <thead>
                                <tr>
                                    <th>{{ __('product.option') }}</th>
                                    <th>{{ __('product.image') }}</th>
                                    <th>{{ __('product.sku') }}</th>
                                    <th>{{ __('product.name') }}</th>
                                    <th>{{ __('product.category') }}</th>
                                    <th>{{ __('product.uom') }}</th>
                                    <th>{{ __('product.description') }}</th>
                                    <th>{{ __('product.price') }}</th>
                                    <th>{{ __('product.weight') }}</th>
                                    <th>{{ __('product.status_label') }}</th>
                                    <th>{{ __('product.last_updated_at') }}</th>
                                    <th>{{ __('product.added_at') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($products as $index => $product)
                                    @php
                                        $image = json_decode($product->images, true);
                                        if (isset($image[0])) {
                                            $image_url =
                                                url('/') . '/' . $product_path . '/' . $product->id . '/' . $image[0];
                                        } else {
                                            $image_url = asset('assets/images/product-default.jpg');
                                        }
                                    @endphp
                                    <tr>
                                        <td>
                                            @if ($admin->canModule('products', 'edit'))
                                                <a href="{{ route('admin.products.edit', encrypt($product->id)) }}"
                                                    class="btn btn-sm btn-primary mb-1" title="{{ __('ui.edit') }}">
                                                    <i class="fa fa-edit"></i>
                                                </a>
                                            @endif
                                            @if (Auth::guard('web_admin')->user()->id == 1)
                                                <a href="#" class="btn btn-sm btn-danger mb-1"
                                                    onclick="confirmRemove('{{ $product->id }}')" title="{{ __('product.remove') }}">
                                                    <i class="fa fa-trash"></i>
                                                </a>
                                            @endif
                                        </td>
                                        <td><img src="{{ $image_url }}" width="80px" /></td>
                                        <td>{{ $product->sku }}</td>
                                        <td>{{ $product->name }}</td>
                                        <td>{{ $product->category_name }}</td>
                                        <td>{{ $product->uom_name }}</td>
                                        <td>{{ $product->description }}</td>
                                        <td>{{ $product->price }}</td>
                                        <td>{{ $product->weight ?? 0 }} {{ __('product.kg_unit') }}</td>
                                        <td>{{ __('product.status.' . $product->status) }}</td>
                                        <td>{{ $product->updated_at }}</td>
                                        <td>{{ $product->created_at }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="12">
                                        {{ $products->appends(request()->query())->links('pagination::bootstrap-4') }}
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
        document.addEventListener('DOMContentLoaded', function() {
            const minSlider = document.getElementById('minPriceSlider');
            const maxSlider = document.getElementById('maxPriceSlider');
            const minDisplay = document.getElementById('minPriceDisplay');
            const maxDisplay = document.getElementById('maxPriceDisplay');
            const minInput = document.getElementById('minPriceInput');
            const maxInput = document.getElementById('maxPriceInput');

            function updateMinPrice() {
                let minVal = parseFloat(minSlider.value);
                let maxVal = parseFloat(maxSlider.value);

                if (minVal > maxVal) {
                    minVal = maxVal;
                    minSlider.value = minVal;
                }

                minDisplay.textContent = minVal.toFixed(2);
                minInput.value = minVal;
            }

            function updateMaxPrice() {
                let minVal = parseFloat(minSlider.value);
                let maxVal = parseFloat(maxSlider.value);

                if (maxVal < minVal) {
                    maxVal = minVal;
                    maxSlider.value = maxVal;
                }

                maxDisplay.textContent = maxVal.toFixed(2);
                maxInput.value = maxVal;
            }

            minSlider.addEventListener('input', updateMinPrice);
            maxSlider.addEventListener('input', updateMaxPrice);

            minSlider.addEventListener('mousedown', function() {
                minSlider.style.zIndex = '3';
                maxSlider.style.zIndex = '2';
            });

            maxSlider.addEventListener('mousedown', function() {
                maxSlider.style.zIndex = '3';
                minSlider.style.zIndex = '1';
            });
        });

        $(document).ready(function() {
            $("#filterPriceFrom,#filterPriceTo").change(function(e) {
                $("#priceRangeInput").val($("#filterPriceFrom").val() + "," + $("#filterPriceTo").val());
            });
        });

        function confirmRemove(id) {
            Swal.fire({
                title: @json(__('product.js.remove_title')),
                text: @json(__('product.js.remove_text')),
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: @json(__('product.js.remove_confirm'))
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "{{ url('/admin/product/remove/') }}/" + id;
                }
            });
        }
    </script>
    <style>
        .range-slider-container {
            position: relative;
            height: 40px;
            margin-bottom: 10px;
        }

        .range-slider-container input[type="range"] {
            position: absolute;
            width: 100%;
            height: 5px;
            pointer-events: none;
            appearance: none;
            -webkit-appearance: none;
            background: transparent;
            outline: none;
        }

        .range-slider-container input[type="range"]::-webkit-slider-track {
            height: 5px;
            background: #dee2e6;
            border-radius: 5px;
        }

        .range-slider-container input[type="range"]::-webkit-slider-thumb {
            pointer-events: all;
            appearance: none;
            -webkit-appearance: none;
            height: 20px;
            width: 20px;
            border-radius: 50%;
            background: #0d6efd;
            cursor: pointer;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            margin-top: -7.5px;
        }

        .range-slider-container input[type="range"]::-moz-range-track {
            height: 5px;
            background: #dee2e6;
            border-radius: 5px;
        }

        .range-slider-container input[type="range"]::-moz-range-thumb {
            pointer-events: all;
            height: 20px;
            width: 20px;
            border-radius: 50%;
            background: #0d6efd;
            cursor: pointer;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        #minPriceSlider {
            z-index: 1;
        }

        #maxPriceSlider {
            z-index: 2;
        }
    </style>
@endsection
