@extends('layouts.admin')
@section('title', 'Add New Customer')
@section('css')

    <link rel="stylesheet" href="{{ asset('assets/css/select2.min.css') }}" />

@endsection
@section('content')

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow no-border">
                <div class="card-body">
                    <div class="mb-4">
                        <h5 class="card-title">Add New Customer</h5>
                    </div>
                    <form action="{{ route('admin.import.customers.submit') }}" method="POST" enctype="multipart/form-data" class="form-wrapper">
                        @csrf
                        <div class="mb-4">
                            <label class="mb-2" for="file">Select Customer Excel</label>
                            <span class="text-danger"> *</span>
                            <input type="file" class="form-control @error('file') is-invalid @enderror" name="file" id="file" accept=".xlsx, .csv" required>
                            @error('file')
                                <span class="text-danger" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4">
                            <a href="{{ route('admin.customers') }}" class="btn btn-secondary me-2 mb-1">Back</a>
                            <button type="submit" class="btn btn-primary mb-1">
                                Import
                                <div class="spinner-border spinner-border-sm d-none" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow no-border">
                <div class="card-body alert alert-info mb-0">
                    <div class="mb-4">
                        <h5 class="card-title d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div>
                                <i class="fa fa-info"></i>
                                Customer Columns Order
                            </div>
                            <a href="{{ asset('assets/customers_sample.xlsx') }}" target="_blank" class="btn btn-sm btn-outline-success">
                                <i class="fa fa-file-excel-o me-2" aria-hidden="true"></i> Download Sample
                            </a>
                        </h5>
                        <p><strong class="text-danger">Make sure the columns order is correctly formatted</strong></p>
                    </div>
                    <ul>
                        <li>Customer Name <small class="text-danger">(required)</small></li>
                        <li>{{ __('customers.customer_code') }}</li>
                        <li>Customer Email</li>
                        <li>Category</li>
                        <li>Attn. Name</li>
                        <li>Attn. Contact</li>
                        <li>Product Price Permission <small class="text-danger">(Yes or No)</small></li>
                        <li>Invoice Visibility <small class="text-danger">(Yes or No)</small></li>
                        <li>Invoice Product Price Visibility <small class="text-danger">(Yes or No)</small></li>
                        <li>Area</li>
                        <li>Billing Address <small class="text-danger">(required)</small></li>
                        <li>Billing City <small class="text-danger">(required)</small></li>
                        <li>Billing Postcode <small class="text-danger">(required)</small></li>
                        <li>Billing State <small class="text-danger">(required)</small></li>
                        <li>Shipping Address</li>
                        <li>Shipping City</li>
                        <li>Shipping Postcode</li>
                        <li>Shipping State</li>
                        <li>Payment Method <small class="text-danger">Methods: cod,term,bank-transfer,e-wallet (comma separated required)</small></li>
                        <li>Remark</li>
                        <li>Password <small class="text-danger">(required)</small></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

@endsection
