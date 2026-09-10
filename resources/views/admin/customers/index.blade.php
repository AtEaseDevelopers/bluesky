@extends('layouts.admin')
@section('title', __('customers.manage'))
@section('content')
@php
    $admin = Auth::guard('web_admin')->user();
@endphp
    <div class="row mb-5 mb-md-5">
        <div class="col-md-12">
            @include('admin.includes.collapsible-filter-start', [
                'panelId' => 'customersFilterPanel',
                'title' => __('customers.filter'),
                'expanded' => false,
                'expandWhenFilled' => ['name', 'email', 'category', 'status', 'customer_type'],
            ])
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
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary me-3">{{ __('ui.search') }}</button>
                                <a href="{{ route('admin.customers') }}">{{ __('ui.clear_search') }}</a>
                            </div>
                        </div>
                    </form>
            @include('admin.includes.collapsible-filter-end')
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="d-flex justify-content-end align-items-center flex-wrap gap-3">
                @if ($admin->canModule('customers', 'edit'))
                    <button type="button" id="syncAutoCountBtn" class="btn btn-outline-secondary">
                        {{ __('customers.sync_autocount') }}
                    </button>
                @endif
                <button type="button" id="copyGuestLink" class="btn btn-primary" data-link="{{ route('public.guest.index') }}">
                    {{ __('customers.copy_guest_link') }}
                </button>
                @if ($admin->canModule('customers', 'create'))
                    <a href="{{ route('admin.customers.invite') }}" class="btn btn-success">
                        {{ __('customers.invite') }}
                    </a>
                    <a href="{{ route('admin.customers.create') }}" class="btn btn-primary">
                        {{ __('customers.add') }}
                    </a>
                @endif
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
                                    <th>
                                        @php
                                            $codeSortActive = ($input['sort'] ?? 'name') === 'sql_customer_code';
                                            $codeSortDir = $codeSortActive ? ($input['dir'] ?? 'asc') : 'asc';
                                            $codeSortNextDir = $codeSortActive && $codeSortDir === 'asc' ? 'desc' : 'asc';
                                            $codeSortQuery = array_merge($input ?? [], [
                                                'sort' => 'sql_customer_code',
                                                'dir' => $codeSortNextDir,
                                            ]);
                                        @endphp
                                        <a href="{{ route('admin.customers') . '?' . http_build_query($codeSortQuery) }}"
                                            class="text-decoration-none text-dark d-inline-flex align-items-center gap-1">
                                            {{ __('customers.customer_code') }}
                                            @if ($codeSortActive)
                                                <i class="fa fa-sort-{{ $codeSortDir === 'asc' ? 'up' : 'down' }}" aria-hidden="true"></i>
                                            @else
                                                <i class="fa fa-sort text-muted" aria-hidden="true"></i>
                                            @endif
                                        </a>
                                    </th>
                                    <th>{{ __('customers.email') }}</th>
                                    <th>{{ __('customers.customer_type') }}</th>
                                    <th>{{ __('customers.credit_balance') }}</th>
                                    <th>{{ __('customers.payment_term') }}</th>
                                    <th>{{ __('customers.category') }}</th>
                                    <th>{{ __('customers.area') }}</th>
                                    <th>{{ __('customers.billing_address') }}</th>
                                    <th>{{ __('customers.shipping_address') }}</th>
                                    <th>{{ __('customers.status') }}</th>
                                    <th>{{ __('customers.sync_status') }}</th>
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
                                            @if ($admin->canModule('customers', 'edit'))
                                                <a href="{{ route('admin.customers.edit', encrypt($user->id)) }}" class="btn btn-sm btn-primary" title="{{ __('customers.edit_customer') }}">
                                                    <i class="fa fa-edit"></i>
                                                </a>
                                                @if (($user->orders_count ?? 0) === 0)
                                                    <button type="button"
                                                        class="btn btn-sm btn-danger btn-delete-customer"
                                                        title="{{ __('customers.delete') }}"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#deleteCustomerModal"
                                                        data-action="{{ route('admin.customers.destroy', encrypt($user->id)) }}"
                                                        data-name="{{ $user->name }}">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                @elseif ($user->isActiveCustomer())
                                                    <button type="button"
                                                        class="btn btn-sm btn-warning btn-deactivate-customer"
                                                        title="{{ __('customers.deactivate') }}"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#deactivateCustomerModal"
                                                        data-action="{{ route('admin.customers.deactivate', encrypt($user->id)) }}"
                                                        data-name="{{ $user->name }}">
                                                        <i class="fa fa-ban"></i>
                                                    </button>
                                                @endif
                                            @endif
                                        </td>
                                        <td>
                                            @if ($user->hasCompletedRegistration())
                                                <input type="text" class="form-control fast_link mb-2" style="min-width: 280px;" value="{{ $user->fastLoginUrl() }}" readonly />
                                                <p>
                                                    @if ($admin->canModule('customers', 'edit'))
                                                        <a href="{{ route('admin.customers.generate-new-login-link', $user->id) }}" class="btn btn-sm btn-primary me-1" title="{{ __('customers.generate_new_login_link') }}">
                                                            <i class="fa fa-refresh"></i>
                                                        </a>
                                                    @endif
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
                                            @elseif ($admin->canModule('customers', 'edit'))
                                                <a href="{{ route('admin.customers.generate-registration-link', $user->id) }}" class="btn btn-sm btn-outline-primary">{{ __('customers.generate_link') }}</a>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($user->hasCompletedRegistration())
                                                {{ $user->name }}
                                            @else
                                                {{ __('customers.pending_registration') }}
                                            @endif
                                        </td>
                                        <td class="customer-code-col" style="min-width: 110px;">
                                            @if ($admin->canModule('customers', 'edit'))
                                                <span class="customer-code-display d-inline-block w-100 {{ $user->sql_customer_code ? '' : 'text-muted' }}"
                                                    data-customer-id="{{ encrypt($user->id) }}"
                                                    data-update-url="{{ route('admin.customers.update-code', encrypt($user->id)) }}"
                                                    title="{{ __('customers.customer_code_dblclick_edit') }}">
                                                    {{ $user->sql_customer_code ?: '--' }}
                                                </span>
                                            @else
                                                {{ $user->sql_customer_code ?: '--' }}
                                            @endif
                                        </td>
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
                                        <td>{{ $user->statusLabel() }}</td>
                                        <td class="text-center">
                                            @if (!$user->hasCompletedRegistration())
                                                <span class="badge bg-secondary">{{ __('customers.autocount_sync_status.not_applicable') }}</span>
                                            @else
                                                @php
                                                    $syncStatusKey = $user->autocountSyncStatusKey();
                                                    $syncStatusClass = match ($syncStatusKey) {
                                                        'synced', 'synced_successfully' => 'bg-success',
                                                        'pending_sync' => 'bg-warning text-dark',
                                                        'pending_inactive' => 'bg-danger',
                                                        'sync_error' => 'bg-danger',
                                                        'skipped' => 'bg-secondary',
                                                        default => 'bg-light text-dark',
                                                    };
                                                @endphp
                                                <span class="badge {{ $syncStatusClass }}">
                                                    {{ __('customers.autocount_sync_status.' . $syncStatusKey) }}
                                                </span>
                                            @endif
                                        </td>
                                        <td>{{ $user->created_at }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="17">
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

    <div class="modal" id="deleteCustomerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('customers.delete') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
                </div>
                <div class="modal-body">
                    <p>{{ __('customers.delete_confirm') }}</p>
                    <p class="mb-0 fw-semibold" id="deleteCustomerName"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.close') }}</button>
                    <form action="" method="POST" id="deleteCustomerForm" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-danger">{{ __('ui.delete') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="deactivateCustomerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('customers.deactivate') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
                </div>
                <div class="modal-body">
                    <p>{{ __('customers.deactivate_confirm') }}</p>
                    <p class="mb-0 fw-semibold" id="deactivateCustomerName"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.close') }}</button>
                    <form action="" method="POST" id="deactivateCustomerForm" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-warning">{{ __('customers.deactivate') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('script')

    <script>
        $(document).ready(function() {
            const customersJs = {
                select_customer: @json(__('customers.js.select_customer')),
                customer_code_saved: @json(__('customers.js.customer_code_saved')),
                customer_code_save_failed: @json(__('customers.js.customer_code_save_failed')),
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

            document.addEventListener('click', function (event) {
                const deleteBtn = event.target.closest('.btn-delete-customer');
                if (deleteBtn) {
                    document.getElementById('deleteCustomerForm').setAttribute('action', deleteBtn.getAttribute('data-action'));
                    document.getElementById('deleteCustomerName').textContent = deleteBtn.getAttribute('data-name');
                }

                const deactivateBtn = event.target.closest('.btn-deactivate-customer');
                if (deactivateBtn) {
                    document.getElementById('deactivateCustomerForm').setAttribute('action', deactivateBtn.getAttribute('data-action'));
                    document.getElementById('deactivateCustomerName').textContent = deactivateBtn.getAttribute('data-name');
                }
            });

            let activeCustomerCodeEditor = null;

            function finishCustomerCodeEdit(save) {
                if (!activeCustomerCodeEditor) {
                    return;
                }

                const wrap = activeCustomerCodeEditor.wrap;
                const display = activeCustomerCodeEditor.display;
                const input = activeCustomerCodeEditor.input;
                const original = activeCustomerCodeEditor.original;
                const updateUrl = display.getAttribute('data-update-url');

                activeCustomerCodeEditor = null;

                if (!save) {
                    display.textContent = original || '--';
                    display.classList.toggle('text-muted', !original);
                    wrap.replaceChild(display, input);
                    return;
                }

                const value = input.value.trim();

                fetch(updateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ sql_customer_code: value }),
                })
                .then(response => response.json().then(data => ({ ok: response.ok, data })))
                .then(({ ok, data }) => {
                    if (!ok || !data.success) {
                        const message = (data && data.message) ? data.message : customersJs.customer_code_save_failed;
                        if (data && data.errors && data.errors.sql_customer_code) {
                            alert(data.errors.sql_customer_code[0]);
                        } else {
                            alert(message);
                        }
                        display.textContent = original || '--';
                        display.classList.toggle('text-muted', !original);
                        wrap.replaceChild(display, input);
                        return;
                    }

                    const saved = (data.sql_customer_code || '').trim();
                    display.textContent = saved || '--';
                    display.classList.toggle('text-muted', !saved);
                    wrap.replaceChild(display, input);
                })
                .catch(() => {
                    alert(customersJs.customer_code_save_failed);
                    display.textContent = original || '--';
                    display.classList.toggle('text-muted', !original);
                    wrap.replaceChild(display, input);
                });
            }

            document.querySelectorAll('.customer-code-display').forEach(function (display) {
                display.style.cursor = 'pointer';

                display.addEventListener('dblclick', function () {
                    if (activeCustomerCodeEditor) {
                        finishCustomerCodeEdit(false);
                    }

                    const wrap = display.parentElement;
                    const original = display.textContent.trim() === '--' ? '' : display.textContent.trim();
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.className = 'form-control form-control-sm';
                    input.value = original;
                    input.maxLength = 30;

                    wrap.replaceChild(input, display);
                    input.focus();
                    input.select();

                    activeCustomerCodeEditor = { wrap, display, input, original };

                    input.addEventListener('keydown', function (event) {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            finishCustomerCodeEdit(true);
                        } else if (event.key === 'Escape') {
                            event.preventDefault();
                            finishCustomerCodeEdit(false);
                        }
                    });

                    input.addEventListener('blur', function () {
                        setTimeout(function () {
                            if (activeCustomerCodeEditor && activeCustomerCodeEditor.input === input) {
                                finishCustomerCodeEdit(true);
                            }
                        }, 0);
                    });
                });
            });
        });
    </script>

@endsection
