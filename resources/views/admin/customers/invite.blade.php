@extends('layouts.admin')
@section('title', 'Invite Customer')
@section('content')

    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="card-title">Invite New Customer</h5>
                    <p class="text-muted">Choose the customer type and category first. We will create a unique registration link so the customer can sign up for the customer portal themselves.</p>
                    <hr>

                    <form action="{{ route('admin.customers.invite.store') }}" method="POST" class="form-wrapper">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="customer_type">Customer Type</label>
                                    <span class="text-danger">*</span>
                                    <select name="customer_type" id="customer_type" class="form-select @error('customer_type') is-invalid @enderror" required>
                                        <option value="">Choose customer type...</option>
                                        <option value="cod" {{ old('customer_type') === 'cod' ? 'selected' : '' }}>COD — pay on delivery</option>
                                        <option value="credit" {{ old('customer_type') === 'credit' ? 'selected' : '' }}>Credit — invoice / terms</option>
                                    </select>
                                    @error('customer_type')
                                        <span class="text-danger"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="category">Category</label>
                                    <span class="text-danger">*</span>
                                    <select name="category" id="category" class="form-select @error('category') is-invalid @enderror" required>
                                        <option value="">Choose category...</option>
                                        @foreach ($category_list as $category)
                                            <option value="{{ $category->category }}" {{ old('category') === $category->category ? 'selected' : '' }}>
                                                {{ $category->category }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('category')
                                        <span class="text-danger"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="area_id">Area</label>
                                    <select name="area_id" id="area_id" class="form-select">
                                        <option value="">Optional</option>
                                        @foreach ($areas as $area)
                                            <option value="{{ $area->id }}" {{ old('area_id') == $area->id ? 'selected' : '' }}>{{ $area->area_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="default_driver_id">Default Driver / Lorry</label>
                                    <select name="default_driver_id" id="default_driver_id" class="form-select">
                                        <option value="">Optional</option>
                                        @foreach ($drivers as $driver)
                                            <option value="{{ $driver->id }}" {{ old('default_driver_id') == $driver->id ? 'selected' : '' }}>
                                                {{ $driver->name ? $driver->name . ' (' . $driver->lorry_number . ')' : $driver->lorry_number }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <label class="mb-2" for="remark">Internal Remark</label>
                                    <textarea name="remark" id="remark" class="form-control" rows="2" placeholder="Optional note for admin only">{{ old('remark') }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <a href="{{ route('admin.customers') }}" class="btn btn-secondary">Back</a>
                            <button type="submit" class="btn btn-primary">Generate Registration Link</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
