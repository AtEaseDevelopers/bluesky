<div class="modal fade" id="posSetupModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('customers.pos.title') }}</h5>
                <button type="button" class="btn-close" id="posSetupCloseBtn" aria-label="{{ __('ui.close') }}"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-4">{{ __('customers.pos.intro') }}</p>

                <div id="posSetupStepChoice">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card h-100 border">
                                <div class="card-body d-flex flex-column">
                                    <h6 class="fw-semibold">{{ __('customers.pos.guest_title') }}</h6>
                                    <p class="text-muted flex-grow-1">{{ __('customers.pos.guest_help') }}</p>
                                    <button type="button" class="btn btn-primary w-100" data-pos-mode="guest">
                                        {{ __('customers.pos.continue_guest') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100 border">
                                <div class="card-body d-flex flex-column">
                                    <h6 class="fw-semibold">{{ __('customers.pos.existing_title') }}</h6>
                                    <p class="text-muted flex-grow-1">{{ __('customers.pos.existing_help') }}</p>
                                    <button type="button" class="btn btn-success w-100" id="posChooseCustomerBtn">
                                        {{ __('customers.pos.choose_customer') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="posSetupStepCustomer" class="d-none">
                    <div class="mb-3">
                        <label class="mb-2" for="posCustomerSelect">{{ __('customers.pos.select_customer') }}</label>
                        <select id="posCustomerSelect" class="form-select">
                            <option value="">{{ __('customers.pos.choose_customer') }}</option>
                            @foreach ($customers ?? [] as $customer)
                                <option value="{{ $customer->id }}">
                                    {{ $customer->name }}
                                    @if ($customer->email)
                                        ({{ $customer->email }})
                                    @endif
                                    — {{ $customer->isCreditCustomer() ? __('customers.customer_type_credit') : __('customers.customer_type_cod') }}
                                </option>
                            @endforeach
                        </select>
                        <div class="text-danger mt-2 d-none" id="posCustomerError"></div>
                    </div>
                    <div class="d-flex justify-content-between gap-2">
                        <button type="button" class="btn btn-secondary" id="posBackToChoiceBtn">{{ __('ui.back') }}</button>
                        <button type="button" class="btn btn-primary" id="posStartCustomerBtn">{{ __('customers.pos.open_pos') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="posExitForm" action="{{ route('admin.pos.exit') }}" method="POST" class="d-none">@csrf</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const posReady = @json($posReady ?? false);
        const setupModalEl = document.getElementById('posSetupModal');
        const setupModal = setupModalEl ? new bootstrap.Modal(setupModalEl) : null;
        const stepChoice = document.getElementById('posSetupStepChoice');
        const stepCustomer = document.getElementById('posSetupStepCustomer');
        const customerSelect = $('#posCustomerSelect');
        const customerError = document.getElementById('posCustomerError');
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        function showSetupModal() {
            if (setupModal) {
                stepChoice.classList.remove('d-none');
                stepCustomer.classList.add('d-none');
                customerError.classList.add('d-none');
                setupModal.show();
            }
        }

        if (!posReady) {
            showSetupModal();
        }

        function postSession(payload, callback) {
            fetch(@json(route('admin.pos.session')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify(payload),
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    if (!result.ok) {
                        if (customerError) {
                            customerError.textContent = result.data.message || 'Unable to start POS session.';
                            customerError.classList.remove('d-none');
                        }
                        return;
                    }
                    window.location.reload();
                })
                .catch(function () {
                    if (customerError) {
                        customerError.textContent = 'Unable to start POS session.';
                        customerError.classList.remove('d-none');
                    }
                });
        }

        document.querySelectorAll('[data-pos-mode="guest"]').forEach(function (button) {
            button.addEventListener('click', function () {
                postSession({ mode: 'guest' });
            });
        });

        document.getElementById('posChooseCustomerBtn')?.addEventListener('click', function () {
            stepChoice.classList.add('d-none');
            stepCustomer.classList.remove('d-none');
            customerError.classList.add('d-none');
            if (customerSelect.data('select2')) {
                customerSelect.select2('open');
            }
        });

        document.getElementById('posBackToChoiceBtn')?.addEventListener('click', function () {
            stepCustomer.classList.add('d-none');
            stepChoice.classList.remove('d-none');
            customerError.classList.add('d-none');
        });

        document.getElementById('posStartCustomerBtn')?.addEventListener('click', function () {
            const customerId = customerSelect.val();
            if (!customerId) {
                customerError.textContent = @json(__('customers.pos.choose_customer_required'));
                customerError.classList.remove('d-none');
                return;
            }
            postSession({ mode: 'customer', customer_id: customerId });
        });

        document.getElementById('posSetupCloseBtn')?.addEventListener('click', function () {
            if (!posReady) {
                document.getElementById('posExitForm')?.submit();
                return;
            }

            if (setupModal) {
                stepCustomer.classList.add('d-none');
                stepChoice.classList.remove('d-none');
                customerError.classList.add('d-none');
                setupModal.hide();
            }
        });

        document.getElementById('posChangeCustomerBtn')?.addEventListener('click', function () {
            fetch(@json(route('admin.pos.reset')), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
            }).then(function () {
                showSetupModal();
            });
        });

        if (customerSelect.length) {
            customerSelect.select2({
                width: '100%',
                dropdownParent: $('#posSetupModal'),
                placeholder: @json(__('customers.pos.choose_customer')),
            });
        }
    });
</script>
