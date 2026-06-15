@extends('layouts.admin')
@section('title', 'Manage Orders')
@section('content')

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card shadow no-border mb-0">
                <div class="card-body">
                    <h5 class="mb-4">Filter Orders</h5>
                    <form method="GET" class="form-wrapper">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="filterId">ID</label>
                                    <input type="text" class="form-control" name="id" id="filterId" value="{{ $input['id'] ?? '' }}" placeholder="Search ID">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="filterFromDate">Order Date From</label>
                                    <input type="date" class="form-control" name="fdate" id="filterFromDate" value="{{ $input['fdate'] ?? '' }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="filterToDate">Order Date To</label>
                                    <input type="date" class="form-control" name="tdate" id="filterToDate" value="{{ $input['tdate'] ?? '' }}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="customerCategory">Customer</label>
                                    <select class="form-select" name="customer" id="filterCustomer">
                                        <option value="">All</option>
                                        @foreach($customers_list as $cust)
                                            <option value="{{ $cust->id }}" {{ ($input['customer'] ?? '') == $cust->id? " selected" : "" }}>
                                                {{ $cust->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="area">Select Area</label>
                                    <select class="form-select @error('area') is-invalid @enderror"  id="area" name="area">
                                        <option value="">Choose...</option>
                                        @foreach ($areaList as $area)
                                            <option value="{{ $area }}" {{ ($input['area'] ?? '') == $area ? 'selected' : '' }}>
                                                {{ $area }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <!--
                            <div class="form-group col-md-4">
                                <label class="mb-2" for="filterPrice">Price Range</label>
                                <div id="priceRangeSlider"></div>
                                <span id="priceRangeValue">{{ $input['min_price'] }} - {{ $input['max_price'] }}</span>
                                <input type="hidden" name="price_range" id="priceRangeInput">
                            </div>
                            -->
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="filterStatus">Status</label>
                                    <select class="form-select" name="status" id="filterStatus">
                                        <option value="">All</option>
                                        @foreach($status_options as $status)
                                        <option value="{{ $status }}"{{ ($input['status'] ?? '') == $status? " selected" : "" }}>{{ trans('order.status.'.$status) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="lorry">Select Lorry</label>
                                    <select class="form-select" id="lorry" name="lorry">
                                        <option value="">Choose...</option>
                                        @foreach ($drivers as $id => $lorry)
                                            <option value="{{ $id }}" {{ ($input['lorry'] ?? '') == $id ? 'selected' : '' }}>
                                                {{ $lorry }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="orderby">Order By</label>
                                    <select class="form-select" name="orderby" id="orderby">
                                        <option value="desc" {{ ($input['orderby'] ?? '') === 'desc'? " selected" : "" }}>Latest First</option>
                                        <option value="asc" {{ ($input['orderby'] ?? '') === 'asc'? " selected" : "" }}>Oldest First</option>
                                        {{-- <option value="do_no_asc" {{ ($input['orderby'] ?? '') === 'do_no_asc'? " selected" : "" }}>DO No Latest First</option> --}}
                                        {{-- <option value="do_no_desc" {{ ($input['orderby'] ?? '') === 'do_no_desc'? " selected" : "" }}>DO No Oldest First</option> --}}
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <div id="priceRange" class="row col-12">
                                        <div class="form-group col-md-6">
                                            <label class="mb-2" for="filterPriceFrom">Price Range From</label>
                                            <input type="number" class="form-control" name="min_price" id="filterPriceFrom" value="{{ $input['min_price'] }}" step="0.01" placeholder="Min">
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label class="mb-2" for="filterPriceTo">Price Range To</label>
                                            <input type="number" class="form-control" name="max_price" id="filterPriceTo" value="{{ $input['max_price'] }}" step="0.01" placeholder="Max">
                                        </div>
                                    </div>
                                    <input type="hidden" name="price_range" id="priceRangeInput">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary me-3">Search</button>
                                <a href="{{ route('admin.orders') }}">Clear Search</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    @if (Auth::guard('web_admin')->user()->role == 'superadmin')
                        <a href="{{ route('admin.orders.create') }}" class="btn btn-primary">
                            <i class="fa fa-plus" aria-hidden="true"></i> Add New Order
                        </a>
                    @endif
                    <button type="button" class="btn btn-primary d-none" id="change-order-statuses" data-bs-toggle="modal" data-bs-target="#order-statuses">
                        Change Order Status
                    </button>
                    <button type="button" class="btn btn-primary d-none" id="change-order-lorry" data-bs-toggle="modal" data-bs-target="#assign-lorry">
                        Change Lorry
                    </button>
                </div>
                <div class="d-flex">
                    <form action="{{ route('admin.orders.export') . $query_params }}">
                        <input type="hidden" class="orders_id" name="orders_id">
                        <button type="submit" class="btn btn-success btn-download-excel me-1">
                            <i class="fa fa-file-excel-o" aria-hidden="true"></i> Export to Excel
                        </button>
                    </form>
                    <button class="btn btn-success status-action-button me-1" data-to_status="completed" data-status="{{ __('order.status.completed') }}" style="display: none;">
                        Change Status To {{ __('order.status.completed') }}
                    </button>
                    <button class="btn btn-success status-action-button download-zip me-1" data-to_status="completed" data-status="{{ __('order.status.completed') }}" title="Download selected Invoice & DO as zip" style="display: none;">
                        <i class="fa fa-download"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card shadow no-border mb-0">
                <div class="card-body">
                    <h5 class="mb-4">Orders</h5>
                    <div class="table-responsive">
                        <table id="orderTable" class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input checkall" id="order_checkall">
                                            <label class="form-check-label" for="order_checkall"> </label>
                                        </div>
                                    </th>
                                    <th>Option</th>
                                    <th>Order ID</th>
                                    {{-- <th>DO No</th> --}}
                                    <th>Order At</th>
                                    <th>Customer</th>
                                    <!--<th>Weight</th>-->
                                    <th>Products</th>
                                    <th>Quantity</th>
                                    <!--<th>Total Price</th>-->
                                    <!--<th>Payment Method</th>-->
                                    <th>Area</th>
                                    <th>Billing Address</th>
                                    <th>Shipping Address</th>
                                    <th>Lorry</th>
                                    <th>Status</th>
                                    <th>Last Updated At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orders as $index => $order)
                                    @php
                                        $customer = $order->customer;
                                    @endphp
                                    <tr>
                                        <td class="order-cbx-col">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input cs-checkbox" name="selected_orders[]" id="order_{{ $order->id }}" value="{{ $order->id }}">
                                                <label class="form-check-label" for="order_{{ $order->id }}"> </label>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Action
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item" href="{{ route('admin.orders.summary', $order->id) }}">Order Summary</a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item view-pdf" href="{{ route('admin.order.invoice', $order->id) }}#toolbar=0" data-url="{{ route('admin.order.invoice', $order->id) }}">View Invoice</a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item view-pdf" href="{{ route('admin.order.delivery-order', $order->id) }}#toolbar=0" data-url="{{ route('admin.order.delivery-order', $order->id) }}">View DO</a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item btn-change-lorry" href="javascript:void(0);" data-id="{{ encrypt($order->id) }}" data-lorry="{{ $order->driver_id }}" data-bs-toggle="modal" data-bs-target="#change-lorry">Change Lorry</a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item btn-add-order-weight" href="javascript:void(0);" data-id="{{ encrypt($order->id) }}" data-bs-toggle="modal" data-bs-target="#add-weight">Order Weight</a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                        <td>{{ $order->id }}</td>
                                        {{-- <td>{{ $order->do_no }}</td> --}}
                                        <td>{{ $order->created_at }}</td>
                                        <td>
                                            <a href="{{ route('admin.customers.edit', encrypt($customer->id)) }}" class="text-dark" target="_blank">
                                                {{ $customer->name }}
                                            </a>
                                        </td>
                                        <!--<td>{{ $order->order_weight ?? 0 }}KG</td>-->
                                        <td class="white-space-nowrap">{!! $order->order_products !!}</td>
                                        <td class="white-space-nowrap">{!! $order->order_qtys !!}</td>
                                        <!--<td>RM {{ $order->total_price }}</td>-->
                                        <!--<td>{{ __('user.payment_method.'.$order->payment_method) }}</td>-->
                                        <td>{{ $order->area }}</td>
                                        <td>{!! $order->billing_address !!}</td>
                                        <td>{!! $order->shipping_address !!}</td>
                                        <td>
                                            @if ($order->driver_id)
                                                {!! isset($drivers[$order->driver_id]) ? $drivers[$order->driver_id] : '<span class="text-danger">Lorry Deleted</span>' !!}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-center">{{ __('order.status.' . $order->status) }}</td>
                                        <td>{{ $order->updated_at }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="14">
                                        {{ $orders->appends(request()->query())->links('pagination::bootstrap-4') }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="pdfModal" tabindex="-1" aria-labelledby="pdfModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pdfModalLabel">PDF Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <iframe id="pdfFrame" style="width: 100%; height: 80vh; border: none;"></iframe>
                </div>
                <div class="modal-footer">
                    <a id="downloadLink" class="btn btn-primary" href="#" download>
                        <i class="fa fa-download"></i> Download PDF
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="order-statuses" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Order Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('admin.change-order-status') }}" method="POST" class="form-wrapper">
                    @csrf
                    <input type="hidden" class="orders_id" name="orders_id">
                    <div class="modal-body">
                        <div class="alert alert-warning d-none" id="order_weight-wrapper">
                            <strong>Orders for which the product weight has not yet been added will not be updated.</strong>
                        </div>
                        <div class="mb-4">
                            <label for="status" class="mb-2">Order Status</label>
                            <span class="text-danger"> *</span>
                            <select class="form-select" id="order_status" name="status" required>
                                <option value="">Choose...</option>
                                @foreach ($statuses as $key => $value)
                                    <option value="{{ $key }}">
                                        {{ $value }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>
                        <button type="submit" class="btn btn-primary">
                            Change Status
                            <div class="spinner-border spinner-border-sm d-none" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="change-lorry" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Lorry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('admin.change-order-lorry') }}" method="POST" class="form-wrapper">
                    @csrf
                    <input type="hidden" class="orders_id" name="orders_id">
                    <div class="modal-body">
                        <div class="mb-4">
                            <label class="mb-2">Change Lorry</label>
                            <span class="text-danger"> *</span>
                            <select class="form-select" id="order_driver_id" name="driver_id" required>
                                <option value="">Choose...</option>
                                @foreach ($drivers as $id => $driver)
                                    <option value="{{ $id }}">
                                        {{ $driver }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>
                        <button type="submit" class="btn btn-primary">
                            Submit
                            <div class="spinner-border spinner-border-sm d-none" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="add-weight" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Products Weight</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('admin.update-order-products-weight') }}" method="POST" class="form-wrapper">
                    @csrf
                    <input type="hidden" class="orders_id" name="orders_id">
                    <div class="modal-body"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>
                        <button type="submit" class="btn btn-primary">
                            Submit
                            <div class="spinner-border spinner-border-sm d-none" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="assign-lorry" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Lorry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('admin.assign-order-driver') }}" method="POST" class="form-wrapper">
                    @csrf
                    <input type="hidden" class="orders_id" name="orders_id">
                    <div class="modal-body">
                        <div class="mb-4">
                            <label class="mb-2" for="order_driver_id">Assign Lorry</label>
                            <span class="text-danger"> *</span>
                            <select class="form-select" id="order_driver_id" name="driver_id" required>
                                <option value="">Choose...</option>
                                @foreach ($drivers as $id => $driver)
                                    <option value="{{ $id }}">
                                        {{ $driver }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>
                        <button type="submit" class="btn btn-primary">
                            Submit
                            <div class="spinner-border spinner-border-sm d-none" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection
@section('script')

    <script src="{{ asset('assets/js/sweetalert.min.js') }}"></script>
    <script>
        $(document).ready(function() {

            $('#filterCustomer').select2();

            // Handle click on .view-pdf buttons
            $(".view-pdf").click(function(e) {
                e.preventDefault();

                // Get the PDF URL from the link's href (remove #toolbar=0 for download)
                var pdfUrl = $(this).attr("href").replace('#toolbar=0', '');
                var downloadUrl = $(this).data('url').replace('#toolbar=0', '');

                // Set the PDF source in the iframe
                $("#pdfFrame").attr("src", $(this).attr("href"));
                $("#downloadLink").attr("href", downloadUrl + '/download');

                // Show the PDF modal
                $("#pdfModal").modal("show");
            });

            // Clean up when modal is closed
            $("#pdfModal").on('hidden.bs.modal', function() {
                $("#pdfFrame").attr("src", '');
            });

            $('.btn-download-excel').on('click', function (e) {
                var selectedOrders = [];
                $("input[name='selected_orders[]']:checked").each(function() {
                    selectedOrders.push($(this).val());
                });
                $('.orders_id').val(selectedOrders);
                if (selectedOrders.length == 0) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Select Order',
                        text: 'Please select the orders to download an Excel sheet!',
                        icon: 'warning',
                        showCancelButton: true,
                    })
                }
            });

            // Update range values dynamically
            // var min_price = parseFloat("{{ $input['min_price'] }}");
            // var max_price = parseFloat("{{ $input['max_price'] }}");
            // var from_price = parseFloat("{{ $input['from_price'] }}");
            // var to_price = parseFloat("{{ $input['to_price'] }}");
            // $("#priceRangeSlider").slider({
            //     range: true, // Enable two handles
            //     min: min_price,
            //     max: max_price,
            //     values: [from_price, to_price], // Initial range values
            //     slide: function (event, ui) {
            //         $("#priceRangeValue").text(ui.values[0] + " - " + ui.values[1]);
            //         $("#priceRangeInput").val(ui.values[0] + "," + ui.values[1]);
            //     }
            // });

            // // Initialize the displayed range and input field
            // var initialRange = $("#priceRangeSlider").slider("option", "values");
            // $("#priceRangeValue").text(initialRange[0] + " - " + initialRange[1]);
            // $("#priceRangeInput").val(initialRange[0] + "," + initialRange[1]);

            $("#filterPriceFrom,#filterPriceTo").change(function(e){
                $("#priceRangeInput").val($("#filterPriceFrom").val() + "," + $("#filterPriceTo").val());
            });

            $("#order_status").change(function(e){
                if ($(this).val() == 'delivering') {
                    $('#order_weight-wrapper').removeClass('d-none');
                } else {
                    $('#order_weight-wrapper').addClass('d-none');
                }
            });

            $(".order-cbx-col input[type=checkbox]").on('click', function(e){
                $(".status-action-button").hide();
                if ($(".order-cbx-col input[type=checkbox]:checked").length) {
                    $(".status-action-button[data-status='" + "{{ __('order.status.completed') }}"+"']").show()
                    $('#change-order-statuses').removeClass('d-none');
                    $('#change-order-lorry').removeClass('d-none');
                } else {
                    $('#change-order-statuses').addClass('d-none');
                    $('#change-order-lorry').addClass('d-none');
                }
            });

            $(".status-action-button").click(function(){
                var selectedOrders = [];
                $("input[name='selected_orders[]']:checked").each(function() {
                    selectedOrders.push($(this).val());
                });

                // for download zip file
                if($(this).hasClass('download-zip')){
                    var field_name = 'order_ids[]';
                    var queryParameters = selectedOrders.join('&'+ field_name +'=');
                    window.location.href = "{{ url('/admin/order/batch-download-files') }}" + "?"+ field_name +"=" + queryParameters;
                    return;
                }

                var status = $(this).data('to_status');

                $.ajax({
                    type: "POST",
                    url: "{{ url('/admin/order/batch-update-status') }}", // Use the URL from the data attribute
                    data: {
                        _token: "{{ csrf_token() }}",
                        order_ids: selectedOrders,
                        status: status,
                    },
                    success: function (response) {
                        data = $.parseJSON(response);
                        if(data.success){
                            Swal.fire('Order Updated', 'The order status has been updated successfully', 'success').then(function(){
                                window.location.reload();
                            });
                        }else{
                            Swal.fire('Error', 'An error occurred while updating the order status', 'error');
                        }
                    },
                    error: function (error) {
                        // Handle errors, e.g., show an error message
                        Swal.fire('Error', 'An error occurred while updating the order status', 'error');
                    }
                });
            });
        });
    </script>

@endsection
