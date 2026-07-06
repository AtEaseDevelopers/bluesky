@extends('layouts.admin')
@section('title', __('product.categories.add'))
@section('content')

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="card-title">{{ __('product.categories.add') }}</h5>
                    <hr>
                    <form action="{{ route('admin.product-categories.store') }}" method="POST" class="form-wrapper">
                        @csrf
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <label class="mb-2" for="category_name">{{ __('product.categories.category_name') }}</label>
                                    <span class="text-danger"> *</span>
                                    <input type="text" class="form-control @error('category_name') is-invalid @enderror" name="category_name" id="category_name" placeholder="{{ __('product.categories.placeholder.category_name') }}" value="{{ old('category_name') }}">
                                    @error('category_name')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                                <div class="d-flex justify-content-end">
                                    <a href="{{ route('admin.product-categories.index') }}" class="btn btn-secondary me-2 mb-1">{{ __('ui.back') }}</a>
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

@endsection
