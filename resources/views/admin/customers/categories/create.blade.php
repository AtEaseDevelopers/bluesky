@extends('layouts.admin')
@section('title', __('customers.categories.add'))
@section('content')
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="card-title">{{ __('customers.categories.add') }}</h5>
                    <hr>
                    <form action="{{ route('admin.customer-categories.store') }}" method="POST" class="form-wrapper">
                        @csrf
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <label class="mb-2" for="category_name">{{ __('customers.categories.category_name') }}</label>
                                    <span class="text-danger"> *</span>
                                    <input type="text" class="form-control @error('category_name') is-invalid @enderror"
                                        name="category_name" id="category_name" placeholder="{{ __('customers.categories.placeholder.category_name') }}"
                                        value="{{ old('category_name') }}">
                                    @error('category_name')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                                <div class="mb-4">
                                    <div class="mb-2">
                                        <label for="visible_products">{{ __('customers.categories.visible_products') }}</label>
                                        <span class="text-danger"> *</span>
                                    </div>
                                    <div>
                                        @error('visible_products')
                                            <span class="text-danger" role="alert">
                                                <strong>{{ $message }}</strong>
                                            </span>
                                        @enderror
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="select-all-visible-products">
                                            {{ __('customers.categories.select_all') }}
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="deselect-all-visible-products">
                                            {{ __('customers.categories.deselect_all') }}
                                        </button>
                                    </div>
                                    <div class="row">
                                        @foreach ($products_by_type as $product)
                                            <div class="col-md-3 col-sm-6">
                                                <input type="checkbox" id="visible_products_{{ $product->id }}"
                                                    name="visible_products[]" value="{{ $product->id }}"
                                                    {{ in_array($product->id, old('visible_products') ?? []) ? 'checked' : '' }} />
                                                <label
                                                    for="visible_products_{{ $product->id }}">{{ $product->name }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <a href="{{ route('admin.customer-categories.index') }}"
                                        class="btn btn-secondary me-2 mb-1">{{ __('ui.back') }}</a>
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
@section('script')
    <script>
        document.getElementById('select-all-visible-products')?.addEventListener('click', function () {
            document.querySelectorAll('input[name="visible_products[]"]').forEach(function (el) {
                el.checked = true;
            });
        });

        document.getElementById('deselect-all-visible-products')?.addEventListener('click', function () {
            document.querySelectorAll('input[name="visible_products[]"]').forEach(function (el) {
                el.checked = false;
            });
        });
    </script>
@endsection
