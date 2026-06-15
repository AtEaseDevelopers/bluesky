@extends('layouts.admin')
@section('title', 'Set New Product Daily Price')
@section('content')

    <h4><i class="fa fa-plus" aria-hidden="true"></i> Set New Product Daily Price</h4>
    <div class="row">
        <form method="POST" action="{{ url('/admin/product-daily-price/add') }}" enctype="multipart/form-data" class="form-wrapper">
            @csrf
            <div class="form-group">
                <label for="date">Date<span class="text-danger ml-1">*</span></label>
                <input type="date" class="form-control" name="date" id="date" value="{{ old('date')? : $today_date }}" required>
                @if ($errors->has('date'))
                    <span class="text-danger" role="alert">
                        <strong>{{ $errors->first('date') }}</strong>
                    </span>
                @endif
            </div>

            <div class="form-group">
                <label for="product_id">Product<span class="text-danger ml-1">*</span></label>
                <select class="form-select" name="product_id" id="product_id" required>
                    <!-- Populate options dynamically based on your product data -->
                    <option value="">{{ __('product.form.select-default') }}</option>
                    @foreach($products as $product)
                    <option value="{{ $product->id }}"{{ (old('product_id')? : ($product_daily_price->product_id ?? '')) == $product->id? " selected" : "" }}>{{ $product->name . " (NP: RM $product->price)" }}</option>
                    @endforeach
                    <!-- Add more options as needed -->
                </select>
                @if ($errors->has('product_id'))
                    <span class="text-danger" role="alert">
                        <strong>{{ $errors->first('product_id') }}</strong>
                    </span>
                @endif
            </div>

            <div class="form-group">
                <label for="user_category">User Category</label>
                <select class="form-select" name="user_category" id="user_category">
                    <!-- Populate options dynamically based on your product data -->
                    <option value="">{{ __('All Category') }}</option>
                    @foreach($category_list as $category)
                    @if($category)
                    <option value="{{ $category }}"{{ (old('user_category')? : ($product_daily_price->user_category ?? '')) == $category? " selected" : "" }}>{{ $category }}</option>
                    @endif
                    @endforeach
                    <!-- Add more options as needed -->
                </select>
            </datalist>
                @if ($errors->has('user_category'))
                    <span class="text-danger" role="alert">
                        <strong>{{ $errors->first('user_category') }}</strong>
                    </span>
                @endif
            </div>

            <div class="form-group">
                <label for="price">Price<span class="text-danger ml-1">*</span></label>
                <input type="number" step="0.01" class="form-control" name="price" id="price" value="{{ old('price')? : ($product_daily_price->price ?? '') }}" placeholder="Enter price" required>
                @if ($errors->has('price'))
                    <span class="text-danger" role="alert">
                        <strong>{{ $errors->first('price') }}</strong>
                    </span>
                @endif
            </div>

            <a href="{{ url('/admin/product-daily-prices') }}" class="btn btn-secondary mt-4">Back</a>
            <button type="submit" class="btn btn-primary mt-4">Save</button>
        </form>
    </div>

@endsection
