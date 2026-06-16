@extends('layouts.admin')
@section('title', 'Manage Customers')
@section('content')

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card shadow no-border mb-0">
                <div class="card-body">
                    <h5 class="mb-4">Filter Customers</h5>
                    <form method="GET" class="form-wrapper">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="filterName">Name</label>
                                    <input type="text" class="form-control" name="name" id="filterName" value="{{ $input['name'] ?? '' }}" placeholder="Search Name">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="filterEmail">Email</label>
                                    <input type="text" class="form-control" name="email" id="filterEmail" value="{{ $input['email'] ?? '' }}" placeholder="Search Email">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="filterName">Category</label>
                                    <select class="form-select" name="category" id="filterCategory">
                                        <option value="">All</option>
                                        @foreach ($category_list as $category)
                                            @if ($category)
                                                <option value="{{ $category->category }}"{{ ($input['category'] ?? '') == $category->category ? ' selected' : '' }}>
                                                    {{ $category->category }}
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="customerState">Shipping State</label>
                                    <select id="customerState" class="form-select" name="shipping_state">
                                        <option value="">All</option>
                                        @foreach ($shipping_state_options as $state)
                                            <option value="{{ $state }}"{{ ($input['shipping_state'] ?? '') == $state ? ' selected' : '' }}>
                                                {{ $state }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="filterStatus">Status</label>
                                    <select class="form-select" name="status" id="filterStatusStatus">
                                        <option value="">All</option>
                                        <option value="active"{{ ($input['status'] ?? '') == 'active' ? ' selected' : '' }}>Active
                                        </option>
                                        <option value="inactive"{{ ($input['status'] ?? '') == 'inactive' ? ' selected' : '' }}>Inactive
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="area">Select Area</label>
                                    <select class="form-select @error('area') is-invalid @enderror"  id="area" name="area">
                                        <option value="">All</option>
                                        @foreach ($areas as $area)
                                            <option value="{{ $area->id }}" {{ ($input['shipping_state'] ?? '') == $area->id ? 'selected' : '' }}>
                                                {{ $area->area_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary me-3">Search</button>
                                <a href="{{ route('admin.customers') }}">Clear Search</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="d-flex justify-content-end align-items-center flex-wrap gap-3">
                <a href="{{ route('admin.customers.create') }}" class="btn btn-primary">
                    Add New Customer
                </a>
                <a href="{{ route('admin.customers.export') }}?{{ $query_params }}" class="btn btn-success">
                    <i class="fa fa-file-excel-o me-2" aria-hidden="true"></i> Export to Excel
                </a>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="mb-4">Customers</h5>
                    <div class="table-responsive">
                        <table id="productTable" class="table table-bordered w-100">
                            <thead>
                                <tr>
                                    <th>Options</th>
                                    <th>Login Link</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Category</th>
                                    <!--<th>Payment Method</th>-->
                                    <th>Area</th>
                                    <th>Billing Address</th>
                                    <th>Shipping Address</th>
                                    <th>Status</th>
                                    <th>Last Updated At</th>
                                    <th>Added At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $index => $user)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.customers.edit', encrypt($user->id)) }}" class="btn btn-sm btn-primary" title="Edit">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control fast_link mb-2" style="width: 150px;" value="{{ url('fast-login/' . Crypt::encryptString($user->login_code)) }}" readonly />
                                            <p>
                                                <a href="{{ route('admin.customers.generate-new-login-link', $user->id) }}" class="btn btn-sm btn-primary me-1" title="Generate New Login Link">
                                                    <i class="fa fa-refresh"></i>
                                                </a>
                                                <a type="button" class="btn btn-sm btn-primary copylink">
                                                    <i class="fa fa-clipboard"></i>
                                                </a>
                                            </p>
                                        </td>
                                        <td>{{ $user->name }}</td>
                                        <td>{{ $user->email ?: '--' }}</td>
                                        <td>{{ $user->category ?: '--' }}</td>
                                        <td>{{ $user->area ?? '-' }}</td>
                                        <td>{{ $user->billing_address }}</td>
                                        <td>{{ $user->shipping_address }}</td>
                                        <td>{!! __('user.status.' . $user->status) !!}</td>
                                        <td>{{ $user->updated_at }}</td>
                                        <td>{{ $user->created_at }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="11">
                                        {{ $users->appends(request()->query())->links('pagination::bootstrap-4') }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('script')

    <script>
        $(document).ready(function() {
            $(".copylink").click(function() {
                const linkToCopy = $(this).closest("td").children(".fast_link");
                linkToCopy.select();
                document.execCommand('copy');
                alert('Link copied to clipboard!');
            });
        });
    </script>

@endsection
