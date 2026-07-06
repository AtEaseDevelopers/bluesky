@extends('layouts.admin')
@section('title', __('product-daily-price.set_new_title'))
@section('content')

    <h4><i class="fa fa-plus" aria-hidden="true"></i> {{ __('product-daily-price.set_new_title') }}</h4>
    <div class="row">
        <form method="POST" action="{{ url('/admin/product-daily-price/add') }}" enctype="multipart/form-data" class="form-wrapper">
            @csrf
            <div class="form-group">
                <label for="date">{{ __('product-daily-price.date') }}<span class="text-danger ml-1">*</span></label>
                <input type="date" class="form-control" name="date" id="date" value="{{ old('date')? : $today_date }}" required>
                @if ($errors->has('date'))
                    <span class="text-danger" role="alert">
                        <strong>{{ $errors->first('date') }}</strong>
                    </span>
                @endif
            </div>

            <div class="form-group">
                <label for="product_id">{{ __('product.product_name') }}<span class="text-danger ml-1">*</span></label>
                <select class="form-select" name="product_id" id="product_id" required>
                    <option value="">{{ __('product.form.select-default') }}</option>
                    @foreach($products as $product)
                    <option value="{{ $product->id }}"{{ (old('product_id')? : ($product_daily_price->product_id ?? '')) == $product->id? " selected" : "" }}>{{ $product->name . ' (' . __('product-daily-price.normal_price', ['price' => $product->price]) . ')' }}</option>
                    @endforeach
                </select>
                @if ($errors->has('product_id'))
                    <span class="text-danger" role="alert">
                        <strong>{{ $errors->first('product_id') }}</strong>
                    </span>
                @endif
            </div>

            <div class="form-group">
                <label for="user_category">{{ __('product-daily-price.user_category') }}</label>
                <select class="form-select" name="user_category" id="user_category">
                    <option value="">{{ __('product-daily-price.all_category') }}</option>
                    @foreach($category_list as $category)
                    @if($category)
                    <option value="{{ $category }}"{{ (old('user_category')? : ($product_daily_price->user_category ?? '')) == $category? " selected" : "" }}>{{ $category }}</option>
                    @endif
                    @endforeach
                </select>
                @if ($errors->has('user_category'))
                    <span class="text-danger" role="alert">
                        <strong>{{ $errors->first('user_category') }}</strong>
                    </span>
                @endif
            </div>

            <div class="form-group">
                <label for="price">{{ __('product.price') }}<span class="text-danger ml-1">*</span></label>
                <input type="number" step="0.01" class="form-control" name="price" id="price" value="{{ old('price')? : ($product_daily_price->price ?? '') }}" placeholder="{{ __('product-daily-price.enter_price') }}" required>
                @if ($errors->has('price'))
                    <span class="text-danger" role="alert">
                        <strong>{{ $errors->first('price') }}</strong>
                    </span>
                @endif
            </div>

            <a href="{{ url('/admin/product-daily-prices') }}" class="btn btn-secondary mt-4">{{ __('ui.back') }}</a>
            <button type="submit" class="btn btn-primary mt-4">{{ __('ui.save') }}</button>
        </form>
    </div>

@endsection
