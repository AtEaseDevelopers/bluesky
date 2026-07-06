@extends('layouts.admin')
@section('title', __('product-daily-price.add_title'))
@section('content')

    @if($duplicating)
        <h5 class="mb-4">
            <i class="fa fa-tags" aria-hidden="true"></i>
            {{ __('product-daily-price.duplicate_title', [
                'date' => Carbon\Carbon::parse($setup_date)->format('d/m/Y'),
                'from_date' => Carbon\Carbon::parse($duplicate_from_date)->format('d/m/Y'),
            ]) }}
        </h5>
    @else
        <h5 class="mb-4">
            <i class="fa fa-tags" aria-hidden="true"></i>
            {{ __('product-daily-price.manage_title') }}
        </h5>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="mb-4">
                    <label for="setup_date" class="mb-2">{{ __('product-daily-price.setup_date') }}</label>
                    <input type="date" class="form-control d-inline col-3" id="setup_date" name="setup_date" value="{{ $setup_date }}">
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-4">
                    <label for="duplicate_to_date" class="mb-2"><i class="fa fa-repeat" aria-hidden="true"></i> {{ __('product-daily-price.duplicate_for') }}</label>
                    <div class="input-group">
                        <input type="date" class="form-control d-inline col-3" id="duplicate_to_date" value="{{ $setup_date }}">
                        <button class="btn btn-primary" id="duplicate-btn">{{ __('product-daily-price.go') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card shadow no-border mb-0">
                <div class="card-body">
                    <h5 class="mb-4">{{ __('product-daily-price.filter_result') }}</h5>
                    <form method="GET" id="searchProd" class="form-wrapper">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="mb-2">{{ __('product-daily-price.search_product') }}</label>
                                    <input type="text" class="form-control d-inline col-3" id="search_q" name="search_q" value="{{ request()->input('search_q') }}" placeholder="{{ __('product-daily-price.search_product') }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="mb-2">{{ __('product-daily-price.sort_by') }}</label>
                                    <select class="form-select d-inline col-8" name="sort_by">
                                        <option value="name-asc"{{ request()->input('sort_by') == 'name-asc'? " selected" : "" }}>{{ __('product-daily-price.name_az') }}</option>
                                        <option value="name-desc"{{ request()->input('sort_by') == 'name-desc'? " selected" : "" }}>{{ __('product-daily-price.name_za') }}</option>
                                        <option value="price-asc"{{ request()->input('sort_by') == 'price-asc'? " selected" : "" }}>{{ __('product-daily-price.price_high_low') }}</option>
                                        <option value="price-desc"{{ request()->input('sort_by') == 'price-desc'? " selected" : "" }}>{{ __('product-daily-price.price_low_high') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary me-3">
                                    <i class="fa fa-search" aria-hidden="true"></i> {{ __('ui.search') }}
                                </button>
                                <a href="">{{ __('ui.clear_search') }}</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card shadow no-border mb-0">
                <div class="card-body">
                    <h5 class="mb-4">{{ __('product.set_daily_price') }}</h5>
                    <form method="POST" action="{{ url('/admin/product-daily-price/add') . '/' . $setup_date }}" enctype="multipart/form-data" class="form-wrapper">
                        @csrf
                        @if ($products->count() == 0)
                            <div class="alert alert-warning">
                                {{ __('product-daily-price.no_matched_product') }}
                            </div>
                        @endif
                        <table class="table">
                            <tbody>
                                @foreach($products as $index => $product)
                                    <tr>
                                        <td colspan="3">
                                            <b>{{ $index + 1 }}. {{ $product->name }}{{ $product->sku? ' (' . __('product-daily-price.sku_prefix', ['sku' => $product->sku]) . ')' : '' }}</b>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('product-daily-price.user_category') }}</th>
                                        <th>{{ __('product.default_price') }}</th>
                                        <th>{{ __('product-daily-price.price_to_set_rm') }}</th>
                                    </tr>
                                    @foreach($category_list as $category)
                                        <tr>
                                            <td>
                                                <input type="text" class="form-control" value="{{ $category? : __('product-daily-price.no_category') }}" readonly>
                                            </td>
                                            <td align="center">
                                                <input type="number" step="0.01" class="form-control" value="{{ $product->price }}" readonly>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" class="form-control" name="price[{{ $product->id }}][{{ $category }}]" id="price" value="{{ $product_daily_price[$product->id][$category] ?? $product->price }}" placeholder="{{ __('product-daily-price.enter_price') }}" required>
                                            </td>
                                        </tr>
                                    @endforeach
                                    <tr>
                                        <td colspan="3">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-end">
                                    <a href="{{ url('/admin/product-daily-prices') }}" class="btn btn-secondary me-2">{{ __('ui.back') }}</a>
                                    <button type="submit" class="btn btn-primary">
                                        {{ __('ui.save') }}
                                        <div class="spinner-border spinner-border-sm d-none" role="status">
                                            <span class="visually-hidden">{{ __('product-daily-price.js.loading') }}</span>
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

@endsection
@section('script')

    <script src="{{ asset('assets/js/sweetalert.min.js') }}"></script>
    <script>
        $(document).ready(function(){
            $("#setup_date").change(function(){
                Swal.fire({
                    title: @json(__('product-daily-price.js.fetching')),
                    text: @json(__('product-daily-price.js.fetching_text')),
                    icon: 'info',
                    showConfirmButton: false,
                    timer: 2000
                });
                window.location.href = "{{ url('/admin/product-daily-price/add') }}/" + $(this).val();
            });

            $("#duplicate-btn").click(function(){
                var duplicate_to_date = $("#duplicate_to_date").val();
                if(duplicate_to_date == "{{ $setup_date }}"){
                    Swal.fire({
                        title: @json(__('product-daily-price.js.not_allowed')),
                        text: @json(__('product-daily-price.js.duplicate_same_date')),
                        icon: 'error',
                        timer: 2000
                    });
                }else{
                    Swal.fire({
                        title: @json(__('product-daily-price.js.confirm')),
                        text: @json(__('product-daily-price.js.duplicate_confirm')).replace(':date', duplicate_to_date),
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: @json(__('product-daily-price.js.yes'))
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = "{{ url('/admin/product-daily-price/add/'.$setup_date.'/') }}" + duplicate_to_date;
                        }
                    });
                }

            });

            $("[name=sort_by]").change(function(){
                $("#searchProd").submit();
            });
        });
    </script>

@endsection
