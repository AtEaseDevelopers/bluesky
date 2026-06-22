@php
    $selectedDriverIds = array_map('intval', (array) ($selectedDriverIds ?? old('driver_ids', [])));
    $showDriverName = $showDriverName ?? false;
@endphp

<div class="driver-picker @error('driver_ids') is-invalid @enderror"
    data-driver-picker
    data-selection-order="{{ json_encode(array_values($selectedDriverIds)) }}">
    @forelse ($drivers as $driver)
        @php
            $driverId = (int) $driver->id;
            $isSelected = in_array($driverId, $selectedDriverIds, true);
            $display = $showDriverName && !empty($driver->name)
                ? $driver->name . ' (' . $driver->lorry_number . ')'
                : $driver->lorry_number;
        @endphp
        <label class="driver-picker__chip {{ $isSelected ? 'is-selected' : '' }}">
            <input type="checkbox"
                class="driver-picker__input"
                value="{{ $driverId }}"
                data-driver-id="{{ $driverId }}"
                {{ $isSelected ? 'checked' : '' }}>
            <span class="driver-picker__text">{{ $display }}</span>
            <span class="driver-picker__default-badge">{{ __('customers.default_driver_badge') }}</span>
        </label>
    @empty
        <span class="text-muted small">{{ __('customers.no_drivers_available') }}</span>
    @endforelse

    <div class="driver-picker__hidden-inputs"></div>
</div>
<small class="text-muted d-block mt-2">{{ __('customers.assigned_drivers_help') }}</small>
@error('driver_ids')
    <span class="text-danger d-block mt-1" role="alert">
        <strong>{{ $message }}</strong>
    </span>
@enderror

@once
    <script>
        (function () {
            function parseSelectionOrder(picker) {
                try {
                    const raw = picker.getAttribute('data-selection-order') || '[]';
                    const parsed = JSON.parse(raw);

                    return Array.isArray(parsed)
                        ? parsed.map(function (id) { return parseInt(id, 10); }).filter(Boolean)
                        : [];
                } catch (error) {
                    return [];
                }
            }

            function initPickerState(picker) {
                if (picker.dataset.driverPickerReady === '1') {
                    return;
                }

                let order = parseSelectionOrder(picker);

                picker.querySelectorAll('.driver-picker__input:checked').forEach(function (input) {
                    const id = parseInt(input.value, 10);
                    if (id && order.indexOf(id) === -1) {
                        order.push(id);
                    }
                });

                picker._driverSelectionOrder = order;
                picker.dataset.driverPickerReady = '1';
                renderDriverPicker(picker);
            }

            function renderDriverPicker(picker) {
                const order = picker._driverSelectionOrder || [];
                const orderSet = new Set(order);
                let defaultChip = null;

                picker.querySelectorAll('.driver-picker__chip').forEach(function (chip) {
                    const input = chip.querySelector('.driver-picker__input');
                    const id = parseInt(input.value, 10);
                    const selected = orderSet.has(id);

                    input.checked = selected;
                    chip.classList.toggle('is-selected', selected);
                    chip.classList.remove('is-default');

                    if (selected && order[0] === id) {
                        defaultChip = chip;
                    }
                });

                if (defaultChip) {
                    defaultChip.classList.add('is-default');
                }

                const hiddenWrap = picker.querySelector('.driver-picker__hidden-inputs');
                hiddenWrap.innerHTML = '';

                order.forEach(function (id) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'driver_ids[]';
                    hidden.value = id;
                    hiddenWrap.appendChild(hidden);
                });

                picker.setAttribute('data-selection-order', JSON.stringify(order));
            }

            function handleDriverToggle(input) {
                const picker = input.closest('[data-driver-picker]');
                if (!picker) {
                    return;
                }

                initPickerState(picker);

                const id = parseInt(input.value, 10);
                let order = picker._driverSelectionOrder.slice();

                if (input.checked) {
                    if (order.indexOf(id) === -1) {
                        order.push(id);
                    }
                } else {
                    order = order.filter(function (value) { return value !== id; });
                }

                picker._driverSelectionOrder = order;
                renderDriverPicker(picker);
            }

            function initDriverPickers() {
                document.querySelectorAll('[data-driver-picker]').forEach(initPickerState);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initDriverPickers);
            } else {
                initDriverPickers();
            }

            document.addEventListener('change', function (event) {
                if (!event.target.classList.contains('driver-picker__input')) {
                    return;
                }

                handleDriverToggle(event.target);
            });
        })();
    </script>
@endonce
