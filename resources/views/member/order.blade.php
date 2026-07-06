@extends('layouts.member')
@section('title', __('orders.member.page_title'))
@section('content')

    @if ($pendingReviewCount > 0)
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="alert alert-warning mb-0">
                    <strong>{{ __('orders.member.pending_review_alert', ['count' => $pendingReviewCount]) }}</strong>
                    {{ __('orders.member.pending_review_help') }}
                </div>
            </div>
        </div>
    @endif

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card shadow no-border mb-0">
                <div class="card-body">
                    <h5 class="mb-4">{{ __('orders.filter') }}</h5>
                    <form method="GET">
                        <div class="row list-filter">
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label for="filterFromDate">{{ __('orders.order_date_from') }}</label>
                                    <input type="date" class="form-control" name="fdate" id="filterFromDate" value="{{ $input['fdate'] ?? '' }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label for="filterToDate">{{ __('orders.order_date_to') }}</label>
                                    <input type="date" class="form-control" name="tdate" id="filterToDate" value="{{ $input['tdate'] ?? '' }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label for="filterStatus">{{ __('orders.status') }}</label>
                                    <select class="form-select" name="status" id="filterStatus">
                                        <option value="">{{ __('ui.all') }}</option>
                                        @foreach($status_options as $status)
                                        <option value="{{ $status }}"{{ ($input['status'] ?? '') == $status ? ' selected' : '' }}>{{ trans('order.status.'.$status) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label for="orderby">{{ __('orders.order_by') }}</label>
                                    <select class="form-select" name="orderby" id="orderby">
                                        <option value="desc" {{ ($input['orderby'] ?? 'desc') == 'desc' ? ' selected' : '' }}>{{ __('orders.latest_first') }}</option>
                                        <option value="asc" {{ ($input['orderby'] ?? '') == 'asc' ? ' selected' : '' }}>{{ __('orders.oldest_first') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary me-3">{{ __('ui.search') }}</button>
                                <a href="{{ route('member.orders') }}">{{ __('ui.clear_search') }}</a>
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
                    <i class="fa fa-file-excel-o" aria-hidden="true"></i> {{ __('ui.export_excel') }}
                </a>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="mb-4">{{ __('ui.nav.my_orders') }}</h5>
                    <div class="row">
                        @forelse($orders as $order)
                            <div class="col-12 col-sm-6 col-md-3 mb-4">
                                <div class="card shadow-sm {{ $order->status === 'customer_reviewing' ? 'border-warning' : '' }}">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="m-0">{{ __('orders.member.order_number', ['id' => $order->id]) }}</h5>
                                        @if ($order->status === 'customer_reviewing')
                                            <span class="badge bg-warning text-dark">{{ __('orders.member.review_badge') }}</span>
                                        @endif
                                    </div>
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-0">{{ __('orders.member.order_at') }}</h6>
                                        <p>{{ $order->created_at->format('d M Y') }}</p>
                                        @if ($order->delivery_date)
                                            <h6 class="fw-bold mb-0">{{ __('orders.member.delivery_label') }}</h6>
                                            <p>{{ $order->delivery_date->format('d M Y') }} {{ $order->delivery_time_slot }}</p>
                                        @endif
                                        @if ($user->price_permission)
                                            <h6 class="fw-bold mb-0">{{ __('orders.member.total_label') }}</h6>
                                            <p>RM {{ number_format($order->total_price, 2) }}</p>
                                        @endif
                                        <h6 class="fw-bold mb-0">{{ __('orders.member.status_colon') }}</h6>
                                        <p>{{ __('order.status.'.$order->status) }}</p>
                                        <h6 class="fw-bold mb-0">{{ __('orders.member.payment_colon') }}</h6>
                                        <p>
                                            @php
                                                $paymentBadgeClass = match ($order->payment_status) {
                                                    'payment_due' => 'bg-danger',
                                                    'paid' => 'bg-success',
                                                    'pending' => 'bg-warning text-dark',
                                                    'partial' => 'bg-warning text-dark',
                                                    default => 'bg-secondary',
                                                };
                                            @endphp
                                            <span class="badge {{ $paymentBadgeClass }}">
                                                {{ __('order.payment_status.'.$order->payment_status) }}
                                            </span>
                                        </p>
                                    </div>
                                    <div class="card-footer">
                                        <a href="{{ route('member.orders.summary', encrypt($order->id)) }}" class="btn btn-sm btn-primary m-1" title="{{ __('orders.member.view_detail') }}">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                        @if ($order->status === 'customer_reviewing')
                                            <a href="{{ route('member.orders.review', encrypt($order->id)) }}" class="btn btn-sm btn-warning m-1" title="{{ __('orders.member.review_approve') }}">
                                                <i class="fa fa-check"></i>
                                            </a>
                                        @endif
                                        @if (!in_array($order->status, ['pending', 'cancelled']))
                                            <a href="{{ url('order/buy-again/' . encrypt($order->id)) }}" class="btn btn-sm btn-primary m-1" title="{{ __('orders.member.buy_again') }}">
                                                <i class="fa fa-repeat"></i>
                                            </a>
                                        @endif
                                        @if ($order->canShowInvoiceToCustomer($user))
                                            <a href="{{ $order->invoice_url }}" class="btn btn-sm btn-primary m-1 view-pdf" title="{{ __('orders.member.view_invoice') }}">
                                                <i class="fa fa-file-text-o"></i>
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <p class="text-muted mb-0">{{ __('orders.member.no_orders') }}</p>
                            </div>
                        @endforelse
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
                    <h5 class="modal-title" id="pdfModalLabel">{{ __('orders.pdf_preview') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('ui.close') }}">
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
            $(".view-pdf").click(function() {
                var pdfUrl = $(this).attr("href");
                $("#pdfFrame").attr("src", pdfUrl);
                $("#pdfModal").modal("show");
                return false;
            });
        });
    </script>

@endsection
