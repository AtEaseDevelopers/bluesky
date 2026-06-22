@extends('layouts.admin')
@section('title', __('customers.manage'))
@section('content')

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card shadow no-border mb-0">
                <div class="card-body">
                    <h5 class="mb-4">{{ __('customers.filter') }}</h5>
                    <form method="GET" class="form-wrapper">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="filterName">{{ __('customers.name') }}</label>
                                    <input type="text" class="form-control" name="name" id="filterName" value="{{ $input['name'] ?? '' }}" placeholder="{{ __('customers.search_name') }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="filterEmail">{{ __('customers.email') }}</label>
                                    <input type="text" class="form-control" name="email" id="filterEmail" value="{{ $input['email'] ?? '' }}" placeholder="{{ __('customers.search_email') }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="filterCategory">{{ __('customers.category') }}</label>
                                    <select class="form-select" name="category" id="filterCategory">
                                        <option value="">{{ __('ui.all') }}</option>
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
                                    <label class="mb-2" for="customerState">{{ __('customers.shipping_state') }}</label>
                                    <select id="customerState" class="form-select" name="shipping_state">
                                        <option value="">{{ __('ui.all') }}</option>
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
                                    <label class="mb-2" for="filterStatus">{{ __('customers.status') }}</label>
                                    <select class="form-select" name="status" id="filterStatusStatus">
                                        <option value="">{{ __('ui.all') }}</option>
                                        <option value="active"{{ ($input['status'] ?? '') == 'active' ? ' selected' : '' }}>{{ __('customers.filter_status.active') }}</option>
                                        <option value="inactive"{{ ($input['status'] ?? '') == 'inactive' ? ' selected' : '' }}>{{ __('customers.filter_status.inactive') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="filterCustomerType">{{ __('customers.customer_type') }}</label>
                                    <select class="form-select" name="customer_type" id="filterCustomerType">
                                        <option value="">{{ __('ui.all') }}</option>
                                        <option value="cod"{{ ($input['customer_type'] ?? '') === 'cod' ? ' selected' : '' }}>{{ __('customers.customer_type_cod') }}</option>
                                        <option value="credit"{{ ($input['customer_type'] ?? '') === 'credit' ? ' selected' : '' }}>{{ __('customers.customer_type_credit') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="area">{{ __('customers.select_area') }}</label>
                                    <select class="form-select @error('area') is-invalid @enderror" id="area" name="area">
                                        <option value="">{{ __('ui.all') }}</option>
                                        @foreach ($areas as $area)
                                            <option value="{{ $area->id }}" {{ ($input['shipping_state'] ?? '') == $area->id ? 'selected' : '' }}>
                                                {{ $area->area_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary me-3">{{ __('ui.search') }}</button>
                                <a href="{{ route('admin.customers') }}">{{ __('ui.clear_search') }}</a>
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
                <button type="button" id="syncAutoCountBtn" class="btn btn-outline-secondary">
                    {{ __('customers.sync_autocount') }}
                </button>
                <button type="button" id="copyGuestLink" class="btn btn-primary" data-link="{{ route('public.guest.index') }}">
                    {{ __('customers.copy_guest_link') }}
                </button>
                <a href="{{ route('admin.customers.invite') }}" class="btn btn-success">
                    {{ __('customers.invite') }}
                </a>
                <a href="{{ route('admin.customers.create') }}" class="btn btn-primary">
                    {{ __('customers.add') }}
                </a>
                <a href="{{ route('admin.customers.export') }}?{{ $query_params }}" class="btn btn-success">
                    <i class="fa fa-file-excel-o me-2" aria-hidden="true"></i> {{ __('customers.export_excel') }}
                </a>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="mb-4">{{ __('customers.list') }}</h5>
                    <div class="table-responsive">
                        <table id="productTable" class="table table-bordered w-100">
                            <thead>
                                <tr>
                                    <th>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input customer-checkall" id="customer_checkall">
                                            <label class="form-check-label" for="customer_checkall"></label>
                                        </div>
                                    </th>
                                    <th>{{ __('customers.options') }}</th>
                                    <th>{{ __('customers.login_link') }}</th>
                                    <th>{{ __('customers.registration') }}</th>
                                    <th>{{ __('customers.name') }}</th>
                                    <th>{{ __('customers.email') }}</th>
                                    <th>{{ __('customers.customer_type') }}</th>
                                    <th>{{ __('customers.credit_balance') }}</th>
                                    <th>{{ __('customers.payment_term') }}</th>
                                    <th>{{ __('customers.category') }}</th>
                                    <th>{{ __('customers.area') }}</th>
                                    <th>{{ __('customers.billing_address') }}</th>
                                    <th>{{ __('customers.shipping_address') }}</th>
                                    <th>{{ __('customers.status') }}</th>
                                    <th>{{ __('customers.added_at') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $index => $user)
                                    <tr>
                                        <td class="customer-cbx-col">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input customer-checkbox" name="selected_customers[]" id="customer_{{ $user->id }}" value="{{ $user->id }}">
                                                <label class="form-check-label" for="customer_{{ $user->id }}"></label>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.customers.edit', encrypt($user->id)) }}" class="btn btn-sm btn-primary" title="{{ __('customers.edit_customer') }}">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        </td>
                                        <td>
                                            @if ($user->hasCompletedRegistration())
                                                <input type="text" class="form-control fast_link mb-2" style="width: 150px;" value="{{ url('fast-login/' . Crypt::encryptString($user->login_code)) }}" readonly />
                                                <p>
                                                    <a href="{{ route('admin.customers.generate-new-login-link', $user->id) }}" class="btn btn-sm btn-primary me-1" title="{{ __('customers.generate_new_login_link') }}">
                                                        <i class="fa fa-refresh"></i>
                                                    </a>
                                                    <a type="button" class="btn btn-sm btn-primary copylink">
                                                        <i class="fa fa-clipboard"></i>
                                                    </a>
                                                </p>
                                            @else
                                                <span class="text-muted">{{ __('customers.available_after_registration') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($user->isPendingRegistration() && $user->registrationUrl())
                                                <input type="text" class="form-control registration_link mb-2" style="width: 150px;" value="{{ $user->registrationUrl() }}" readonly />
                                                <a type="button" class="btn btn-sm btn-primary copy-registration-link">
                                                    <i class="fa fa-clipboard"></i> {{ __('customers.copy') }}
                                                </a>
                                            @elseif ($user->hasCompletedRegistration())
                                                <span class="badge bg-success">{{ __('customers.registered') }}</span>
                                            @else
                                                <a href="{{ route('admin.customers.generate-registration-link', $user->id) }}" class="btn btn-sm btn-outline-primary">{{ __('customers.generate_link') }}</a>
                                            @endif
                                        </td>
                                        <td>{{ $user->hasCompletedRegistration() ? $user->name : __('customers.pending_registration') }}</td>
                                        <td>{{ $user->email ?: '--' }}</td>
                                        <td>
                                            @if ($user->isCreditCustomer())
                                                <span class="badge bg-primary">{{ __('customers.customer_type_credit') }}</span>
                                            @else
                                                <span class="badge bg-secondary">{{ __('customers.customer_type_cod') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($user->isCreditCustomer())
                                                @php $balance = (float) ($user->credit_balance ?? 0); @endphp
                                                <span class="{{ $balance > 0 ? 'text-success fw-semibold' : ($balance < 0 ? 'text-danger fw-semibold' : 'text-muted') }}">
                                                    RM {{ number_format($balance, 2) }}
                                                </span>
                                            @else
                                                <span class="text-muted">{{ __('customers.payment_term_not_applicable') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($user->isCreditCustomer())
                                                {{ $user->paymentTermLabel() }}
                                            @else
                                                <span class="text-muted">{{ __('customers.payment_term_not_applicable') }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $user->category ?: '--' }}</td>
                                        <td>{{ $user->area ?? '-' }}</td>
                                        <td>{{ $user->billing_address }}</td>
                                        <td>{{ $user->shipping_address }}</td>
                                        <td>{{ __('user.status.' . $user->status) }}</td>
                                        <td>{{ $user->created_at }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="15">
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

    <form id="syncAutoCountForm" action="{{ route('admin.customers.sync-autocount') }}" method="POST" class="d-none">
        @csrf
    </form>

@endsection
@section('script')

    <script>
        $(document).ready(function() {
            const customersJs = {
                select_customer: @json(__('customers.js.select_customer')),
            };

            $("#customer_checkall").on('change', function() {
                $(".customer-checkbox").prop('checked', this.checked);
            });

            $("#syncAutoCountBtn").on('click', function() {
                const selectedCustomers = [];
                $("input[name='selected_customers[]']:checked").each(function() {
                    selectedCustomers.push($(this).val());
                });

                if (selectedCustomers.length === 0) {
                    alert(customersJs.select_customer);
                    return;
                }

                const form = $("#syncAutoCountForm");
                form.find('input[name="customer_ids[]"]').remove();

                selectedCustomers.forEach(function(customerId) {
                    form.append($('<input>', {
                        type: 'hidden',
                        name: 'customer_ids[]',
                        value: customerId,
                    }));
                });

                form.submit();
            });

            $(".copylink").click(function() {
                const linkToCopy = $(this).closest("td").children(".fast_link");
                linkToCopy.select();
                document.execCommand('copy');
                alert(@json(__('customers.js.link_copied')));
            });

            $(".copy-registration-link").click(function() {
                const linkToCopy = $(this).closest("td").children(".registration_link");
                linkToCopy.select();
                document.execCommand('copy');
                alert(@json(__('customers.js.registration_link_copied')));
            });

            $("#copyGuestLink").click(function() {
                const link = $(this).data('link');
                const temp = $('<input>').val(link).appendTo('body').select();
                document.execCommand('copy');
                temp.remove();
                alert(@json(__('customers.js.guest_link_copied')));
            });
        });
    </script>

@endsection
