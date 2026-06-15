@extends('layouts.admin')
@section('title', 'Add Product Daily Price')
@section('content')

    @if($duplicating)
        <h5 class="mb-4">
            <i class="fa fa-tags" aria-hidden="true"></i> 
            Duplicate Product Daily Price For {{ Carbon\Carbon::parse($setup_date)->format('d/m/Y') }} from {{ Carbon\Carbon::parse($duplicate_from_date)->format('d/m/Y') }}
        </h5>
    @else
        <h5 class="mb-4">
            <i class="fa fa-tags" aria-hidden="true"></i> 
            Manage Product Daily Price For 
        </h5>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="mb-4">
                    <label for="setup_date" class="mb-2">Setup Date</label>
                    <input type="date" class="form-control d-inline col-3" id="setup_date" name="setup_date" value="{{ $setup_date }}">
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-4">
                    <label for="setup_date" class="mb-2"><i class="fa fa-repeat" aria-hidden="true"></i> Duplicate For </label>
                    <div class="input-group">
                        <input type="date" class="form-control d-inline col-3" id="duplicate_to_date" value="{{ $setup_date }}">
                        <button class="btn btn-primary" id="duplicate-btn">Go</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card shadow no-border mb-0">
                <div class="card-body">
                    <h5 class="mb-4">Filter Result</h5>
                    <form method="GET" id="searchProd" class="form-wrapper">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="mb-2">Search Product Name / SKU</label>
                                    <input type="text" class="form-control d-inline col-3" id="search_q" name="search_q" value="{{ request()->input('search_q') }}" placeholder="Search Product Name / SKU">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="mb-2">Sort By</label>
                                    <select class="form-select d-inline col-8" name="sort_by">
                                        <option value="name-asc"{{ request()->input('sort_by') == 'name-asc'? " selected" : "" }}>Name (A-Z)</option>
                                        <option value="name-desc"{{ request()->input('sort_by') == 'name-desc'? " selected" : "" }}>Name (Z-A)</option>
                                        <option value="price-asc"{{ request()->input('sort_by') == 'price-asc'? " selected" : "" }}>Default Price (High to Low)</option>
                                        <option value="price-desc"{{ request()->input('sort_by') == 'price-desc'? " selected" : "" }}>Default Price (High to Low)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary me-3">
                                    <i class="fa fa-search" aria-hidden="true"></i> Search
                                </button>
                                <a href="">Clear Search</a>
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
                    <h5 class="mb-4">Set Daily Price</h5>
                    <form method="POST" action="{{ url('/admin/product-daily-price/add') . '/' . $setup_date }}" enctype="multipart/form-data" class="form-wrapper">
                        @csrf
                        @if ($products->count() == 0)
                            <div class="alert alert-warning">
                                No matched product found.
                            </div>
                        @endif
                        <table class="table">
                            <tbody>
                                @foreach($products as $index => $product)
                                    <tr>
                                        <td colspan="3">
                                            <b>{{ $index + 1 }}. {{ $product->name }}{{ $product->sku? " (SKU: $product->sku)" : "" }}</b>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>User Category</th>
                                        <th>Default Price (RM)</th>
                                        <th>Price To Set (RM)</th>
                                    </tr>
                                    @foreach($category_list as $category)
                                        <tr>
                                            <td>
                                                <input type="text" class="form-control" value="{{ $category? : "No Category" }}" readonly>
                                            </td>
                                            <td align="center">
                                                <input type="number" step="0.01" class="form-control" value="{{ $product->price }}" readonly>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" class="form-control" name="price[{{ $product->id }}][{{ $category }}]" id="price" value="{{ $product_daily_price[$product->id][$category] ?? $product->price }}" placeholder="Enter price" required>
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
                                    <a href="{{ url('/admin/product-daily-prices') }}" class="btn btn-secondary me-2">Back</a>
                                    <button type="submit" class="btn btn-primary">
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

@endsection
@section('script')

    <script src="{{ asset('assets/js/sweetalert.min.js') }}"></script>
    <script>
        $(document).ready(function(){
            $("#setup_date").change(function(){
                Swal.fire({
                    title: 'Fetching...',
                    text: 'The daily price is now fetching...',
                    icon: 'info',
                    showConfirmButton: false, // This will hide the "OK" button
                    timer: 2000
                });
                window.location.href = "{{ url('/admin/product-daily-price/add') }}/" + $(this).val();
            });

            $("#duplicate-btn").click(function(){
                var duplicate_to_date = $("#duplicate_to_date").val();
                if(duplicate_to_date == "{{ $setup_date }}"){
                    Swal.fire({
                        title: 'Not Allowed',
                        text: 'The date to duplicate cannot be same date.',
                        icon: 'error',
                        timer: 2000
                    });
                }else{
                    Swal.fire({
                        title: 'Confirm',
                        text: 'Are you sure you want to duplicate this daily price setting to '+ duplicate_to_date +'?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // If user confirms, redirect to remove URL
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
