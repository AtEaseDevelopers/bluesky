@extends('layouts.admin')
@section('title', 'Edit Category')
@section('content')

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="card-title">Edit Category</h5>
                    <hr>
                    <form action="{{ route('admin.customer-categories.update', encrypt($category->id)) }}" method="POST"
                        class="form-wrapper">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <label class="mb-2" for="category_name">Category Name</label>
                                    <span class="text-danger"> *</span>
                                    <input type="text" class="form-control @error('category_name') is-invalid @enderror"
                                        name="category_name" id="category_name" placeholder="Enter category name"
                                        value="{{ $category->category }}">
                                    @error('category_name')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                                <div class="mb-4">
                                    <div class="mb-2">
                                        <label for="visible_products">Visible Products</label>
                                        <span class="text-danger"> *</span>
                                    </div>
                                    <div>
                                        @error('visible_products')
                                            <span class="text-danger" role="alert">
                                                <strong>{{ $message }}</strong>
                                            </span>
                                        @enderror
                                    </div>
                                    <div class="row">
                                        @foreach ($products_by_type as $product)
                                            <div class="col-md-3 col-sm-6">
                                                <input type="checkbox" id="visible_products_{{ $product->id }}"
                                                    name="visible_products[]" value="{{ $product->id }}"
                                                    {{ in_array($product->id, old('visible_products') ?? $category_product_ids) ? 'checked' : '' }} />
                                                <label
                                                    for="visible_products_{{ $product->id }}">{{ $product->name }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <a href="{{ route('admin.customer-categories.index') }}"
                                        class="btn btn-secondary me-2 mb-1">Back</a>
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

@endsection
@section('script')
    <script>
        $('.select-all-type').on('change', function() {
            var isChecked = $(this).is(':checked');
            var typeId = $(this).data('type-id');
            $('input[name="visible_products[]"][data-type-id="' + typeId + '"]').prop('checked', isChecked);
        });
    </script>
@endsection
