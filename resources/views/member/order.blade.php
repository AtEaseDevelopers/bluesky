@extends('layouts.member')
@section('title', 'Orders')
@section('content')

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card shadow no-border mb-0">
                <div class="card-body">
                    <h5 class="mb-4">Filter Orders</h5>
                    <form method="GET">
                        <div class="row list-filter">
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label for="filterFromDate">Order Date From</label>
                                    <input type="date" class="form-control" name="fdate" id="filterFromDate" value="{{ $input['fdate'] ?? '' }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label for="filterToDate">Order Date To</label>
                                    <input type="date" class="form-control" name="tdate" id="filterToDate" value="{{ $input['tdate'] ?? '' }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label for="filterStatus">Status</label>
                                    <select class="form-select" name="status" id="filterStatus">
                                        <option value="">All</option>
                                        @foreach($status_options as $status)
                                        <option value="{{ $status }}"{{ ($input['status'] ?? '') == $status? " selected" : "" }}>{{ trans('order.status.'.$status) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label for="orderby">Order By</label>
                                    <select class="form-select" name="orderby" id="orderby">
                                        <option value="desc" {{ ($input['status'] ?? '') == 'desc'? " selected" : "" }}>Latest First</option>
                                        <option value="asc" {{ ($input['status'] ?? '') == 'asc'? " selected" : "" }}>Oldest First</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary me-3">Search</button>
                                <a href="{{ route('member.orders') }}">Clear Search</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="d-flex justify-content-end">
                <a href="{{ url('orders/export'.$query_params) }}" class="btn btn-success ml-auto">
                    <i class="fa fa-file-excel-o" aria-hidden="true"></i> Export to Excel
                </a>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="mb-4">My Orders</h5>
                    <div class="row">
                        @foreach($orders as $index => $order)
                            <div class="col-12 col-sm-6 col-md-3 mb-4">
                                <div class="card shadow-sm">
                                    <div class="card-header">
                                        <h5 class="m-0">Order #{{ $order->id }}</h5>
                                    </div>
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-0">Order At:</h6>
                                        <p>{{ $order->created_at }}</p>
                                        <!--<h6 class="fw-bold mb-0">Payment Method:</h6>-->
                                        <!--<p>{{ __('user.payment_method.'.$order->payment_method) }}</p>-->
                                        @if ($user->price_permission)
                                            <h6 class="fw-bold mb-0">Total Price:</h6>
                                            <p>RM {{ $order->total_price }}</p>
                                        @endif
                                        <h6 class="fw-bold mb-0">Status:</h6>
                                        <p>{{ __('order.status.'.$order->status) }}</p>
                                    </div>
                                    <div class="card-footer">
                                        <a href="{{ url('order/summary/' . encrypt($order->id)) }}" class="btn btn-sm btn-primary m-1" title="View Order Detail">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                        <a href="{{ url('order/buy-again/' . encrypt($order->id)) }}" class="btn btn-sm btn-primary m-1" title="Buy Again">
                                            <i class="fa fa-repeat"></i>
                                        </a>
                                        @if (in_array($order->status, ['cancelled', 'delivering', 'completed']) && $user->invoice_visibility)
                                            <a href="{{ $order->invoice_url }}" class="btn btn-sm btn-primary m-1 view-pdf"title="View Invoice" >
                                                <i class="fa fa-file-text-o"></i>
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div>
                        {{ $orders->appends(request()->query())->links('pagination::bootstrap-4') }}
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
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <iframe id="pdfFrame" style="width: 100%; height: 80vh;"></iframe>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('script')

    <script>
        $(document).ready(function() {
            // Handle click on .view-pdf buttons
            $(".view-pdf").click(function() {
                // Get the PDF URL from the button's data attribute
                var pdfUrl = $(this).attr("href");

                // Set the PDF source in the iframe
                $("#pdfFrame").attr("src", pdfUrl);

                // Show the PDF modal
                $("#pdfModal").modal("show");

                // Prevent the default behavior of the link
                return false;
            });
        });
    </script>

@endsection