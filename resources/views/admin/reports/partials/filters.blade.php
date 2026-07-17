@php
    $req = request();
@endphp
<div class="card shadow no-border">
    <div class="card-body">
        <form method="GET" class="form-wrapper" id="report-filters">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group mb-4">
                        <label class="mb-2" for="filterId">{{ __('ui.reports.order_id') }}</label>
                        <input type="text" class="form-control" name="id" id="filterId" value="{{ $req['id'] ?? '' }}" placeholder="{{ __('ui.search') }} ID">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-4">
                        @if (!empty($singleDateReport))
                            <label class="mb-2" for="reportDate">{{ __('ui.reports.report_date') }}</label>
                            <input
                                type="date"
                                class="form-control"
                                name="date"
                                id="reportDate"
                                value="{{ $req['date'] ?? ($reportDate ?? now()->toDateString()) }}"
                                max="{{ now()->toDateString() }}"
                            >
                            <small class="text-muted">{{ __('ui.reports.report_date_help') }}</small>
                        @else
                            <label class="mb-2" for="filterFromDate">{{ __('ui.reports.date_range') }}</label>
                            <div class="d-flex">
                                <input type="date" class="form-control mb-1 me-2" name="fdate" id="filterFromDate" value="{{ $req['fdate'] ?? ($startDate ?? now()->toDateString()) }}">
                                <input type="date" class="form-control mb-1" name="tdate" id="filterToDate" value="{{ $req['tdate'] ?? ($endDate ?? now()->toDateString()) }}">
                            </div>
                            <small class="text-muted">{{ __('ui.reports.date_range_help') }}</small>
                        @endif
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-4">
                        <label class="mb-2" for="status">{{ __('ui.reports.order_status') }}</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">{{ __('ui.all') }}</option>
                            @foreach ($statuses as $key => $value)
                                <option value="{{ $key }}" {{ ($req['status'] ?? '') == $key ? 'selected' : '' }}>
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
                        <label class="mb-2" for="driver">{{ __('ui.reports.driver_lorry') }}</label>
                        <select class="form-select" name="driver" id="driver">
                            <option value="">{{ __('ui.all') }}</option>
                            @foreach ($drivers as $driver)
                                <option value="{{ $driver->id }}" {{ ($req['driver'] ?? '') == $driver->id ? 'selected' : '' }}>
                                    {{ $driver->name ?: $driver->username }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-4">
                        <label class="mb-2" for="customer">{{ __('ui.reports.customer') }}</label>
                        <select class="form-select" name="customer" id="customer">
                            <option value="">{{ __('ui.all') }}</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" {{ ($req['customer'] ?? '') == $customer->id ? 'selected' : '' }}>
                                    {{ $customer->name }}@if($customer->email) — {{ $customer->email }}@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-4">
                        <label class="mb-2" for="payment_method">{{ __('ui.reports.payment_method') }}</label>
                        <select class="form-select" name="payment_method" id="payment_method">
                            <option value="">{{ __('ui.all') }}</option>
                            @foreach ($payment_methods as $key => $label)
                                <option value="{{ $key }}" {{ ($req['payment_method'] ?? '') == $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group mb-4">
                        <label class="mb-2" for="area">{{ __('ui.reports.area') }}</label>
                        <select class="form-select" id="area" name="area">
                            <option value="">{{ __('ui.all') }}</option>
                            @foreach ($areas as $area)
                                <option value="{{ $area->id }}" {{ ($req['area'] ?? '') == $area->id ? 'selected' : '' }}>
                                    {{ $area->area_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <button type="submit" class="btn btn-primary me-3">{{ __('ui.search') }}</button>
                    <a href="{{ route(Route::currentRouteName()) }}">{{ __('ui.clear_search') }}</a>
                </div>
            </div>
        </form>
    </div>
</div>
