@extends('layouts.admin')
@section('title', 'Stock In')
@section('content')

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="card-title">Record Stock In</h5>
                    <hr>
                    <form action="{{ route('admin.inventory.stock-in.store') }}" method="POST" class="form-wrapper">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="product_id">Product</label>
                                    <span class="text-danger"> *</span>
                                    <select name="product_id" id="product_id"
                                        class="form-control @error('product_id') is-invalid @enderror" required>
                                        <option value="">Select product</option>
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
                                    <label class="mb-2" for="movement_date">Date</label>
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
                                    <label class="mb-2" for="quantity">Quantity</label>
                                    <span class="text-danger"> *</span>
                                    <input type="number" step="0.001" min="0.001"
                                        class="form-control @error('quantity') is-invalid @enderror" name="quantity"
                                        id="quantity" placeholder="Enter quantity" value="{{ old('quantity') }}" required>
                                    @error('quantity')
                                        <span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="weight">Weight (kg)</label>
                                    <input type="number" step="0.001" min="0"
                                        class="form-control @error('weight') is-invalid @enderror" name="weight"
                                        id="weight" placeholder="Optional weight in kg" value="{{ old('weight') }}">
                                    @error('weight')
                                        <span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <label class="mb-2" for="remarks">Remarks</label>
                                    <textarea class="form-control @error('remarks') is-invalid @enderror" name="remarks"
                                        id="remarks" rows="3" placeholder="Optional remarks">{{ old('remarks') }}</textarea>
                                    @error('remarks')
                                        <span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="d-flex justify-content-end">
                                    <a href="{{ route('admin.inventory.index') }}" class="btn btn-secondary me-2 mb-1">Back</a>
                                    <button type="submit" class="btn btn-success mb-1">
                                        Save Stock In
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
        $('#product_id').select2({ width: '100%' });
    </script>
@endsection
