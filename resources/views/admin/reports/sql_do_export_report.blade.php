@extends('layouts.admin')
@section('title', 'SQL DO EXPORT Report')
@section('content')

    <div class="row mb-5">
        <div class="col-md-12">
            <h4 class="mb-4">SQL DO EXPORT Report</h4>
            @php
                $req = request();
            @endphp
            <div class="card shadow no-border">
                <div class="card-body">
                    <form method="GET" class="form-wrapper" id="report-filters">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="filterId">Order ID</label>
                                    <input type="text" class="form-control" name="id" id="filterId" value="{{ $req['id'] }}" placeholder="Search ID">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="filterFromDate">Date Range</label>
                                    <div class="d-flex">
                                        <input type="date" class="form-control mb-1 me-2" name="fdate" id="filterFromDate" value="{{ $req['fdate'] }}">
                                        <input type="date" class="form-control mb-1" name="tdate" id="filterToDate" value="{{ $req['tdate'] }}">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="status">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="">Choose...</option>
                                        @foreach ($statuses as $key => $value)
                                            <option value="{{ $key }}" {{ $req['status'] == $key ? 'selected' : '' }}>
                                                {{ $value }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="driver">Select Lorry</label>
                                    <select class="form-select" name="driver" id="driver">
                                        <option value="">Choose...</option>
                                        @foreach($drivers as $driver)
                                            <option value="{{ $driver->id }}" {{ $req['drive'] == $driver->id ? 'selected' : '' }}>
                                                {{ $driver->name ?: $driver->username }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="customer">Select Customer</label>
                                    <select class="form-select" name="customer">
                                        <option value="">Choose...</option>
                                        @foreach($customers as $customer)
                                            <option value="{{ $customer->id }}" {{ $req['customer'] == $customer->id ? 'selected' : '' }}>
                                                {{ $customer->name }} - {{ $customer->email }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="area">Select Area</label>
                                    <select class="form-select @error('area') is-invalid @enderror"  id="area" name="area">
                                        <option value="">All</option>
                                        @foreach ($areaList as $area)
                                            <option value="{{ $area }}" {{ ($input['shipping_state'] ?? '') == $area ? 'selected' : '' }}>
                                                {{ $area }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-end">
                                    <a href="{{ route('admin.sql-do-export-report-excel') }}" id="export-excel-btn" class="btn btn-success disabled">
                                        <i class="fa fa-file-excel-o me-2" aria-hidden="true"></i> Export to Excel
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
