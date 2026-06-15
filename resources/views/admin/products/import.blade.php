@extends('layouts.admin')
@section('title', 'Products Import')
@section('content')

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-transparent">
                    <h5 class="card-title fw-bold my-2">Import Products</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.products-import.store') }}" method="POST" enctype="multipart/form-data" class="form-wrapper">
                        @csrf

                        <div class="mb-4">
                            <label for="file">Excel File <span class="text-danger">*</span></label>
                            <input type="file" class="form-control @error('file') is-invalid @enderror" id="file" name="file" required>
                            @error('file')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                            <small class="form-text text-muted">
                                Supported formats: .xlsx, .xls, .csv (Max: 10MB)
                            </small>
                        </div>

                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-upload me-2"></i> Import Products
                            <div class="spinner-border d-none spinner-border-sm mx-2" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </button>
                        <a href="{{ asset('assets/product_template.xlsx') }}" class="btn btn-outline-secondary ml-2">
                            <i class="fas fa-download me-2"></i> Download Template
                        </a>
                    </form>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-transparent">
                    <h5 class="card-title fw-bold my-2">Import Template Format</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>UOM</th>
                                    <th>Product Category</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>SKU</th>
                                    <th>Price</th>
                                    <th>Weight</th>
                                    <th>Images</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                    <th>NOS</th>
                                    <th>Show Weight</th>
                                    <th>Show Quantity</th>
                                    <th>Sell In</th>
                                    <th>Options</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>pcs</td>
                                    <td>Electronics</td>
                                    <td>Sample Product</td>
                                    <td>Product description</td>
                                    <td>SKU-001</td>
                                    <td>99.99</td>
                                    <td>1.5</td>
                                    <td>["image1.jpg", "image2.jpg"]</td>
                                    <td>active</td>
                                    <td>Sample remark</td>
                                    <td>1</td>
                                    <td>Yes</td>
                                    <td>Yes</td>
                                    <td>piece</td>
                                    <td>{"color":["black","white"], "size":["sm","md"]}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="alert alert-info">
                <strong>Note:</strong>
                <ul class="mb-0">
                    <li>Options field must be valid JSON format</li>
                    <li>Images field must be a JSON array of image filenames</li>
                    <li>Show Weight and Show Quantity: Use "Yes" or "No"</li>
                    <li>Status: Use your system's status values (active, inactive, etc.)</li>
                </ul>
            </div>
        </div>
    </div>

@endsection
