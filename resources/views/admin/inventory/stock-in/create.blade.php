@extends('layouts.admin')
@section('title', __('inventory.stock_in'))
@section('content')

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="card-title">{{ __('inventory.record_stock_in') }}</h5>
                    <hr>
                    <form action="{{ route('admin.inventory.stock-in.store') }}" method="POST" class="form-wrapper">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="product_id">{{ __('inventory.product') }}</label>
                                    <span class="text-danger"> *</span>
                                    <select name="product_id" id="product_id"
                                        class="form-control @error('product_id') is-invalid @enderror" required>
                                        <option value="">{{ __('inventory.select_product') }}</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}"
                                                {{ (string) old('product_id', request('product_id')) === (string) $product->id ? 'selected' : '' }}>
                                                {{ $product->name }} @if ($product->sku)({{ $product->sku }})@endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('product_id')
                                        <span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="movement_date">{{ __('inventory.date') }}</label>
                                    <span class="text-danger"> *</span>
                                    <input type="date" class="form-control @error('movement_date') is-invalid @enderror"
                                        name="movement_date" id="movement_date"
                                        value="{{ old('movement_date', date('Y-m-d')) }}" required>
                                    @error('movement_date')
                                        <span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="quantity">{{ __('inventory.quantity') }}</label>
                                    <span class="text-danger"> *</span>
                                    <input type="number" step="0.001" min="0.001"
                                        class="form-control @error('quantity') is-invalid @enderror" name="quantity"
                                        id="quantity" placeholder="{{ __('inventory.enter_quantity') }}" value="{{ old('quantity') }}" required>
                                    @error('quantity')
                                        <span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="weight">{{ __('inventory.weight_kg') }}</label>
                                    <input type="number" step="0.001" min="0"
                                        class="form-control @error('weight') is-invalid @enderror" name="weight"
                                        id="weight" placeholder="{{ __('inventory.weight_optional') }}" value="{{ old('weight') }}">
                                    @error('weight')
                                        <span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <label class="mb-2" for="remarks">{{ __('inventory.remarks') }}</label>
                                    <textarea class="form-control @error('remarks') is-invalid @enderror" name="remarks"
                                        id="remarks" rows="3" placeholder="{{ __('inventory.remarks_optional') }}">{{ old('remarks') }}</textarea>
                                    @error('remarks')
                                        <span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="d-flex justify-content-end">
                                    <a href="{{ route('admin.inventory.index') }}" class="btn btn-secondary me-2 mb-1">{{ __('ui.back') }}</a>
                                    <button type="submit" class="btn btn-success mb-1">
                                        {{ __('inventory.save_stock_in') }}
                                        <div class="spinner-border spinner-border-sm d-none" role="status">
                                            <span class="visually-hidden">{{ __('inventory.loading') }}</span>
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
        $('#product_id').select2({ width: '100%', placeholder: @json(__('inventory.select_product')) });
    </script>
@endsection
