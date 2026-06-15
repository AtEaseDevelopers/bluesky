@extends('layouts.admin')
@section('title', 'Dashboard')
@section('css')

    <link href="{{ asset('assets/datatables/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" type="text/css">
    <style>
        #productTable_wrapper {
            width: 100% !important;
        }

        .dataTables_length {
            display: none;
        }

        .powerbi-container {
            position: relative;
            width: 100%;
            padding-bottom: 62.25%;
            /* 16:10 aspect ratio */
            height: 0;
            overflow: hidden;
        }

        .powerbi-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 0;
        }
    </style>

@endsection
@section('content')

    @php
        use Carbon\Carbon;
        $firstOfMonth = Carbon::now()->firstOfMonth()->format('Y-m-d');
        $lastOfMonth = Carbon::now()->lastOfMonth()->format('Y-m-d');
        $currentDate = Carbon::now()->format('Y-m-d');
    @endphp

    <div class="row mb-5">
        <div class="col-md-4">
            <a href="{{ route('admin.orders') }}" class="text-decoration-none">
                <div class="card shadow no-border box-bg-1 mb-4">
                    <div class="card-body">
                        <i class="fa fa-file-o" aria-hidden="true"></i> Total Orders<br />
                        <h4>{{ $summary['total_orders'] }}</h4>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('admin.orders') }}?fdate={{ $firstOfMonth }}&tdate={{ $lastOfMonth }}"
                class="text-decoration-none">
                <div class="card shadow no-border box-bg-2 mb-4">
                    <div class="card-body">
                        <i class="fa fa-file-o" aria-hidden="true"></i> This Month Total Orders<br />
                        <h4>{{ $summary['total_orders_month'] }}</h4>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('admin.orders') }}?fdate={{ $currentDate }}&tdate={{ $currentDate }}"
                class="text-decoration-none">
                <div class="card shadow no-border box-bg-3 mb-4">
                    <div class="card-body">
                        <i class="fa fa-file-o" aria-hidden="true"></i> Total Orders Today<br />
                        <h4>{{ $summary['total_orders_today'] }}</h4>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('admin.orders') }}" class="text-decoration-none">
                <div class="card shadow no-border box-bg-4 mb-4">
                    <div class="card-body">
                        <i class="fa fa-usd" aria-hidden="true"></i> Total Sales<br />
                        <h4>RM {{ number_format($summary['total_sales'], 2) }}</h4>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('admin.orders') }}??fdate={{ $firstOfMonth }}&tdate={{ $lastOfMonth }}"
                class="text-decoration-none">
                <div class="card shadow no-border box-bg-5 mb-4">
                    <div class="card-body">
                        <i class="fa fa-usd" aria-hidden="true"></i> This Month Sales<br />
                        <h4>RM {{ number_format($summary['total_sales_month'], 2) }}</h4>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('admin.orders') }}?fdate={{ $currentDate }}&tdate={{ $currentDate }}"
                class="text-decoration-none">
                <div class="card shadow no-border box-bg-6 mb-4">
                    <div class="card-body">
                        <i class="fa fa-usd" aria-hidden="true"></i> Today Total Orders<br />
                        <h4>{{ $summary['total_sales_today'] }}</h4>
                    </div>
                </div>
            </a>
        </div>
    </div>

    {{-- Power BI Analytics --}}
    <div class="row mb-5">
        <div class="col-12 col-md-6">
            <div class="card shadow no-border mb-4">
                <div class="card-body">
                    <h5 class="mb-3">Area(s)</h5>
                    <div class="powerbi-container">
                        <iframe title="demo_Area(s)"
                            src="https://app.powerbi.com/view?r=eyJrIjoiMmMwOGExOGItMGEyNi00ZjEwLWJmM2YtYmU1MzQ1Nzg5N2UzIiwidCI6IjljYmQ5ZDgwLWYyMmItNDcxMi1hMTcyLWRkN2ZlOTJiMWM5YiJ9"
                            allowFullScreen="true"></iframe>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card shadow no-border mb-4">
                <div class="card-body">
                    <h5 class="mb-3">Area vs Order Count</h5>
                    <div class="powerbi-container">
                        <iframe title="demo_Area-vs-order-count"
                            src="https://app.powerbi.com/view?r=eyJrIjoiMzc2ODA3ZTEtOTYzNi00NDJkLTg4ZTMtMDQxMmQ5OGQ1ZDE2IiwidCI6IjljYmQ5ZDgwLWYyMmItNDcxMi1hMTcyLWRkN2ZlOTJiMWM5YiJ9"
                            allowFullScreen="true"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-12 col-md-6">
            <div class="card shadow no-border mb-4">
                <div class="card-body">
                    <h5 class="mb-3">Customer(s)</h5>
                    <div class="powerbi-container">
                        <iframe title="demo_customer(s)"
                            src="https://app.powerbi.com/view?r=eyJrIjoiNGFmNWMwODUtOThkOS00MzMwLWFlNTgtNDdmNDExMmRjMmY4IiwidCI6IjljYmQ5ZDgwLWYyMmItNDcxMi1hMTcyLWRkN2ZlOTJiMWM5YiJ9"
                            allowFullScreen="true"></iframe>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card shadow no-border mb-4">
                <div class="card-body">
                    <h5 class="mb-3">Product & Price</h5>
                    <div class="powerbi-container">
                        <iframe title="demo_product&price"
                            src="https://app.powerbi.com/view?r=eyJrIjoiMDgzM2E4ZGYtNjhjNC00MTVhLTg3OWEtMWQ5NTAwY2M4OWQxIiwidCI6IjljYmQ5ZDgwLWYyMmItNDcxMi1hMTcyLWRkN2ZlOTJiMWM5YiJ9"
                            allowFullScreen="true"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-12">
            <div class="card shadow no-border mb-4">
                <div class="card-body">
                    <h5 class="mb-3">Revenue Over Time</h5>
                    <div class="powerbi-container">
                        <iframe title="demo_revenue-over-time"
                            src="https://app.powerbi.com/view?r=eyJrIjoiMTY3ZmQxZGEtZTg4Mi00OWYzLWI3NDktMmUyYzAwZmU4YjhiIiwidCI6IjljYmQ5ZDgwLWYyMmItNDcxMi1hMTcyLWRkN2ZlOTJiMWM5YiJ9"
                            allowFullScreen="true"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-12 col-md-7">
            <div class="card shadow no-border mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <h5 class="mb-4">Today Orders</h5>
                        <div class="status-filter">
                            <button class="btn btn-sm btn-primary status-filter-button mb-1 active"
                                data-status="">All</button>
                            {{-- <button class="btn btn-sm btn-primary status-filter-button mb-1" data-status="{{ __('order.status.pending') }}">{{ __('order.status.pending') }}</button> --}}
                            <button class="btn btn-sm btn-primary status-filter-button mb-1"
                                data-status="{{ __('order.status.processing') }}">{{ __('order.status.processing') }}</button>
                            {{-- <button class="btn btn-sm btn-primary status-filter-button mb-1" data-status="{{ __('order.status.delivering') }}">{{ __('order.status.delivering') }}</button> --}}
                            <button class="btn btn-sm btn-primary status-filter-button mb-1"
                                data-status="{{ __('order.status.completed') }}">{{ __('order.status.completed') }}</button>
                            <button class="btn btn-sm btn-primary status-filter-button mb-1"
                                data-status="{{ __('order.status.cancelled') }}">{{ __('order.status.cancelled') }}</button>
                            <button class="btn btn-sm btn-success mb-1 status-action-button" data-to_status="processing"
                                data-status="{{ __('order.status.processing') }}" style="display: none;">Change Status To
                                {{ __('order.status.processing') }}</button>
                            <button class="btn btn-sm btn-success mb-1 status-action-button" data-to_status="completed"
                                data-status="{{ __('order.status.completed') }}" style="display: none;">Change Status To
                                {{ __('order.status.completed') }}</button>
                            <button class="btn btn-sm btn-success mb-1 status-action-button download-zip"
                                title="Download selected Invoice & DO as zip" data-to_status="completed"
                                data-status="{{ __('order.status.completed') }}" style="display: none;"><i
                                    class="fa fa-download"></i></button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="orderTable" class="table table-bordered w-100">
                            <thead>
                                <tr>
                                    <th class="order-cbx-col">Select</th>
                                    <th></th>
                                    <th>Order At</th>
                                    <th>Customer</th>
                                    <th class="text-right">Total Price</th>
                                    <th>Payment Method</th>
                                    <th>Status</th>
                                    <th>Last Updated At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($today_orders as $index => $order)
                                    @php
                                        $customer = $order->customer;
                                    @endphp
                                    <tr>
                                        <td class="order-cbx-col">
                                            <input type="checkbox" name="selected_orders[]" value="{{ $order->id }}">
                                        </td>
                                        <td>
                                            <a title="View" href="{{ route('admin.orders.summary', $order->id) }}">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                        </td>
                                        <td>{{ Carbon::parse($order->created_at)->format('h:i a') }}</td>
                                        <td>
                                            <a class="text-dark" target="_blank"
                                                href="{{ route('admin.customers.edit', encrypt($customer->id)) }}">
                                                {{ $customer->name }}
                                            </a>
                                        </td>
                                        <td align="right">{{ $order->total_price }}</td>
                                        <td>{{ $order->payment_method ? __('user.payment_method.' . $order->payment_method) : '' }}
                                        </td>
                                        <td>{{ __('order.status.' . $order->status) }}</td>
                                        <td>{{ $order->updated_at }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-5">
            <div class="card shadow no-border mb-4">
                <div class="card-body">
                    <h5 class="mb-4">Daily Sales This Month</h5>
                    <canvas id="dailySalesChart"></canvas>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('script')

    <script src="{{ asset('assets/datatables/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/datatables/js/dataTables.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('assets/js/chart.js') }}"></script>
    <script src="{{ asset('assets/js/sweetalert.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            var orderTable = $('#orderTable').DataTable({
                paging: true,
                lengthMenu: [10, 25, 50, 100],
                searching: true,
                ordering: true,
                info: false,
                responsive: true,
                columnDefs: [{
                        targets: [0],
                        orderable: false
                    } // Disable sorting for the first column
                ],
                order: [
                    [2, 'desc']
                ], // Sort by the second column in ascending order initially
                language: {
                    emptyTable: "No data available in the table"
                }
            });

            // Add a click event handler for the status filter buttons
            $('.status-filter-button').on('click', function() {
                $(".order-cbx-col input[type=checkbox]").prop('checked', false);
                $('.status-filter-button').removeClass('active');
                $(".status-action-button").hide();
                $(this).addClass('active');

                var status = $(this).data('status');
                orderTable.columns(6).search(status).draw();

                if (status == "{{ __('order.status.processing') }}") {
                    $(".order-cbx-col").show();
                } else {
                    $(".order-cbx-col").hide();
                }
            });

            $(".order-cbx-col input[type=checkbox]").on('click', function(e) {
                $(".status-action-button").hide();
                if ($(".order-cbx-col input[type=checkbox]:checked").length) {
                    var status = $('.status-filter-button.active').data('status');
                    if (status == "{{ __('order.status.pending') }}") {
                        $(".status-action-button[data-status='" + "{{ __('order.status.processing') }}" +
                            "']").show()
                    } else if (status == "{{ __('order.status.processing') }}") {
                        $(".status-action-button[data-status='" + "{{ __('order.status.completed') }}" +
                            "']").show()
                    }
                }
            });

            $(".status-action-button").click(function() {
                var selectedOrders = [];
                $("input[name='selected_orders[]']:checked").each(function() {
                    selectedOrders.push($(this).val());
                });

                // for download zip file
                if ($(this).hasClass('download-zip')) {
                    var field_name = 'order_ids[]';
                    var queryParameters = selectedOrders.join('&' + field_name + '=');
                    window.location.href = "{{ url('/admin/order/batch-download-files') }}" + "?" +
                        field_name + "=" + queryParameters;
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
                    success: function(response) {
                        data = $.parseJSON(response);
                        if (data.success) {
                            Swal.fire('Order Updated',
                                    'The order status has been updated successfully', 'success')
                                .then(function() {
                                    window.location.reload();
                                });
                        } else {
                            Swal.fire('Error',
                                'An error occurred while updating the order status', 'error'
                                );
                        }
                    },
                    error: function(error) {
                        // Handle errors, e.g., show an error message
                        Swal.fire('Error', 'An error occurred while updating the order status',
                            'error');
                    }
                });
            });

            $(".status-filter-button[data-status='']").trigger('click');

            // Chart.js Graph
            generate_daily_sales_chart();

            function generate_daily_sales_chart(dates, sales) {
                var data = {!! json_encode($charts['daily_sales']) !!};
                var dates = data.dates;
                var sales = data.sales;
                var ctx = $('#dailySalesChart')[0].getContext('2d');

                var chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: dates,
                        datasets: [{
                            label: 'Daily Sales',
                            data: sales,
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1,
                        }],
                    },
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true,
                            },
                        },
                    },
                });
            }
        });
    </script>

@endsection
