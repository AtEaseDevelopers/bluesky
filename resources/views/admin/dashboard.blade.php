@extends('layouts.admin')
@section('title', __('dashboard.title'))
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
                        <i class="fa fa-file-o" aria-hidden="true"></i> {{ __('dashboard.total_orders') }}<br />
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
                        <i class="fa fa-file-o" aria-hidden="true"></i> {{ __('dashboard.total_orders_month') }}<br />
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
                        <i class="fa fa-file-o" aria-hidden="true"></i> {{ __('dashboard.total_orders_today') }}<br />
                        <h4>{{ $summary['total_orders_today'] }}</h4>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('admin.orders') }}" class="text-decoration-none">
                <div class="card shadow no-border box-bg-4 mb-4">
                    <div class="card-body">
                        <i class="fa fa-usd" aria-hidden="true"></i> {{ __('dashboard.total_sales') }}<br />
                        <h4>RM {{ number_format($summary['total_sales'], 2) }}</h4>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('admin.orders') }}?fdate={{ $firstOfMonth }}&tdate={{ $lastOfMonth }}"
                class="text-decoration-none">
                <div class="card shadow no-border box-bg-5 mb-4">
                    <div class="card-body">
                        <i class="fa fa-usd" aria-hidden="true"></i> {{ __('dashboard.total_sales_month') }}<br />
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
                        <i class="fa fa-usd" aria-hidden="true"></i> {{ __('dashboard.today_total_sales') }}<br />
                        <h4>RM {{ number_format($summary['total_sales_today'], 2) }}</h4>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-12 col-md-7">
            <div class="card shadow no-border mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <h5 class="mb-4">{{ __('dashboard.today_orders') }}</h5>
                        <div class="status-filter">
                            <button class="btn btn-sm btn-primary status-filter-button mb-1 active"
                                data-status="">{{ __('ui.all') }}</button>
                            <button class="btn btn-sm btn-primary status-filter-button mb-1"
                                data-status="{{ __('order.status.pending') }}">{{ __('order.status.pending') }}</button>
                            <button class="btn btn-sm btn-primary status-filter-button mb-1"
                                data-status="{{ __('order.status.in_route') }}">{{ __('order.status.in_route') }}</button>
                            <button class="btn btn-sm btn-primary status-filter-button mb-1"
                                data-status="{{ __('order.status.delivered') }}">{{ __('order.status.delivered') }}</button>
                            <button class="btn btn-sm btn-primary status-filter-button mb-1"
                                data-status="{{ __('order.status.cancelled') }}">{{ __('order.status.cancelled') }}</button>
                            <button class="btn btn-sm btn-success mb-1 status-action-button" data-to_status="in_route"
                                data-status="{{ __('order.status.in_route') }}" style="display: none;">{{ __('orders.move_to', ['status' => __('order.status.in_route')]) }}</button>
                            <button class="btn btn-sm btn-success mb-1 status-action-button" data-to_status="delivered"
                                data-status="{{ __('order.status.delivered') }}" style="display: none;">{{ __('orders.move_to', ['status' => __('order.status.delivered')]) }}</button>
                            <button class="btn btn-sm btn-success mb-1 status-action-button download-zip"
                                title="{{ __('orders.download_selected_zip') }}" data-to_status="delivered"
                                data-status="{{ __('order.status.delivered') }}" style="display: none;"><i
                                    class="fa fa-download"></i></button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="orderTable" class="table table-bordered w-100">
                            <thead>
                                <tr>
                                    <th class="order-cbx-col">{{ __('dashboard.select') }}</th>
                                    <th></th>
                                    <th>{{ __('orders.order_at') }}</th>
                                    <th>{{ __('orders.customer') }}</th>
                                    <th class="text-right">{{ __('ui.reports.total_price') }}</th>
                                    <th>{{ __('ui.reports.payment_method') }}</th>
                                    <th>{{ __('dashboard.status') }}</th>
                                    <th>{{ __('orders.last_updated_at') }}</th>
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
                                            <a title="{{ __('dashboard.view') }}" href="{{ route('admin.orders.summary', $order->id) }}">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                        </td>
                                        <td>{{ Carbon::parse($order->created_at)->format('h:i a') }}</td>
                                        <td>
                                            @if ($customer)
                                                <a class="text-dark" target="_blank"
                                                    href="{{ route('admin.customers.edit', encrypt($customer->id)) }}">
                                                    {{ $customer->name }}
                                                </a>
                                            @else
                                                {{ $order->walk_in_name ?? __('orders.walk_in_public') }}
                                            @endif
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
                    <h5 class="mb-4">{{ __('dashboard.daily_sales_this_month') }}</h5>
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
            var orderTable = $('#orderTable').DataTable({
                paging: true,
                lengthMenu: [10, 25, 50, 100],
                searching: true,
                ordering: true,
                info: false,
                responsive: true,
                language: @json(__('drivers.datatable')),
                columnDefs: [{
                        targets: [0],
                        orderable: false
                    }
                ],
                order: [
                    [2, 'desc']
                ],
            });

            $('.status-filter-button').on('click', function() {
                $(".order-cbx-col input[type=checkbox]").prop('checked', false);
                $('.status-filter-button').removeClass('active');
                $(".status-action-button").hide();
                $(this).addClass('active');

                var status = $(this).data('status');
                orderTable.columns(6).search(status).draw();

                if (status == @json(__('order.status.pending'))) {
                    $(".order-cbx-col").show();
                } else {
                    $(".order-cbx-col").hide();
                }
            });

            $(".order-cbx-col input[type=checkbox]").on('click', function(e) {
                $(".status-action-button").hide();
                if ($(".order-cbx-col input[type=checkbox]:checked").length) {
                    var status = $('.status-filter-button.active').data('status');
                    if (status == @json(__('order.status.pending'))) {
                        $(".status-action-button[data-status='" + @json(__('order.status.in_route')) + "']").show();
                    } else if (status == @json(__('order.status.in_route'))) {
                        $(".status-action-button[data-status='" + @json(__('order.status.delivered')) + "']").show();
                    } else if (status == @json(__('order.status.delivered'))) {
                        $(".status-action-button.download-zip").show();
                    }
                }
            });

            $(".status-action-button").click(function() {
                var selectedOrders = [];
                $("input[name='selected_orders[]']:checked").each(function() {
                    selectedOrders.push($(this).val());
                });

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
                    url: "{{ url('/admin/order/batch-update-status') }}",
                    data: {
                        _token: "{{ csrf_token() }}",
                        order_ids: selectedOrders,
                        status: status,
                    },
                    success: function(response) {
                        data = $.parseJSON(response);
                        if (data.success) {
                            Swal.fire(@json(__('orders.js.order_updated')),
                                    @json(__('orders.js.order_status_updated')), 'success')
                                .then(function() {
                                    window.location.reload();
                                });
                        } else {
                            Swal.fire(@json(__('orders.js.error')),
                                @json(__('orders.js.order_status_error')), 'error'
                                );
                        }
                    },
                    error: function(error) {
                        Swal.fire(@json(__('orders.js.error')), @json(__('orders.js.order_status_error')),
                            'error');
                    }
                });
            });

            $(".status-filter-button[data-status='']").trigger('click');

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
                            label: @json(__('dashboard.daily_sales')),
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
