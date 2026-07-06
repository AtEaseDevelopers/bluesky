@extends('layouts.admin')
@section('title', __('product-daily-price.title'))
@section('content')

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card shadow no-border mb-0">
                <div class="card-body">
                    <h5 class="mb-4">{{ __('product-daily-price.filter') }}</h5>
                    <form method="GET" class="form-wrapper">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label for="filterFromDate">{{ __('product-daily-price.effective_date_from') }}</label>
                                    <input type="date" class="form-control" name="fdate" id="filterFromDate" value="{{ $input['fdate'] ?? '' }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label for="filterToDate">{{ __('product-daily-price.effective_date_to') }}</label>
                                    <input type="date" class="form-control" name="tdate" id="filterToDate" value="{{ $input['tdate'] ?? '' }}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary me-3">{{ __('ui.search') }}</button>
                                <a href="{{ url('/admin/product-daily-prices') }}">{{ __('ui.clear_search') }}</a>
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
                <a href="{{ url('/admin/product-daily-price/add/' . Carbon\Carbon::now()->addDay()->format('Y-m-d')) }}" class="btn btn-primary">
                    {{ __('product.set_daily_price') }}
                </a>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card shadow no-border mb-0">
                <div class="card-body">
                    <h5 class="mb-4">{{ __('product-daily-price.title') }}</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered w-100">
                            <thead>
                                <tr>
                                    <th>{{ __('product.option') }}</th>
                                    <th>{{ __('product-daily-price.date') }}</th>
                                    <th>{{ __('product.last_updated_at') }}</th>
                                    <th>{{ __('product.added_at') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($daily_prices as $index => $daily_price)
                                <tr>
                                    <td>
                                        <a href="{{ url('/admin/product-daily-price/add/' . $daily_price->date) }}" class="btn btn-sm btn-primary" title="{{ __('product-daily-price.view') }}">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                    </td>
                                    <td>{{ $daily_price->date }}</td>
                                    <td>{{ $daily_price->updated_at }}</td>
                                    <td>{{ $daily_price->created_at }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('script')

    <script src="{{ asset('assets/js/sweetalert.min.js') }}"></script>
    <script>
        $(document).ready(function(){
            function handleRemoveAction(dailyPriceId) {
                Swal.fire({
                    title: @json(__('product-daily-price.js.confirm')),
                    text: @json(__('product-daily-price.js.remove_confirm')),
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: @json(__('product-daily-price.js.yes_remove'))
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = "{{ url('/admin/product-daily-price/remove/') }}" + dailyPriceId;
                    }
                });
            }

            $('a[data-action="remove"]').on('click', function(e) {
                e.preventDefault();
                var dailyPriceId = $(this).data('id');
                handleRemoveAction(dailyPriceId);
            });
        });
    </script>

@endsection
