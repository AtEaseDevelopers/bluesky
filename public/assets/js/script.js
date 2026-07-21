const appUrl = document.querySelector('meta[name="app-url"]').getAttribute('content');
const productInfoUrl = document.querySelector('meta[name="product-info-url"]')?.getAttribute('content')
    || (appUrl + '/add-to-cart-product-info');
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

function orderUi(key, fallback, replacements) {
    var labels = window.orderUiLabels || {};
    var text = labels[key] || fallback || '';

    if (replacements) {
        Object.keys(replacements).forEach(function (replaceKey) {
            text = text.split(':' + replaceKey).join(replacements[replaceKey]);
        });
    }

    return text;
}

function orderJs(key, fallback) {
    var labels = window.orderUiLabels || {};
    return (labels.js && labels.js[key]) || fallback || '';
}

function orderLineBillAmount(sellIn, qty, weight, billByWeight) {
    sellIn = sellIn || 'qty';
    qty = parseFloat(qty) || 0;
    weight = parseFloat(weight) || 0;
    billByWeight = billByWeight !== false;

    if (sellIn === 'qty') {
        return qty;
    }

    if (sellIn === 'qty_bill_weight') {
        return billByWeight && weight > 0 ? weight : qty;
    }

    if (sellIn === 'weight') {
        return weight;
    }

    return qty || weight;
}

function formatProductMetaLine(key, value) {
    if (key === 'sell_in') {
        var sellInLabels = (window.orderUiLabels && window.orderUiLabels.sell_in) || {};
        var sellInLabel = orderUi('sell_in_label', 'Sell in');
        return sellInLabel + ': ' + (sellInLabels[value] || value);
    }

    return key + ': ' + value;
}

document.addEventListener('DOMContentLoaded', function () {
    if (document.querySelectorAll(".form-wrapper")) {
        const forms = document.querySelectorAll(".form-wrapper");
        forms.forEach(form => {
            form.addEventListener("submit", function (event) {
                var button = this.querySelector('button[type="submit"]');
                if (button) {
                    button.disabled = true;
                    var spinnerBorder = button.querySelector('.spinner-border');
                    if (spinnerBorder) {
                        spinnerBorder.classList.remove('d-none');
                    }
                }

                document.querySelectorAll("*").forEach(function (element) {
                    element.style.cursor = "wait";
                });
            });
        });
    }

    if (document.getElementById('product-search')) {
        const searchInput = document.getElementById('product-search');
        const productList = document.getElementById('productList');

        searchInput.addEventListener('input', function() {
            const searchValue = this.value.toLowerCase().trim();
            const productCards = productList.querySelectorAll('.products-card');
    
            if (searchValue === '') {
                productCards.forEach(card => card.classList.remove('d-none'));
            } else {
                productCards.forEach(card => {
                    const name = card.getAttribute('data-name').toLowerCase();
                    const sku = card.getAttribute('data-sku').toLowerCase();
                    if (name.includes(searchValue) || sku.includes(searchValue)) {
                        card.classList.remove('d-none');
                    } else {
                        card.classList.add('d-none');
                    }
                });
            }
        });
    }

    if (document.getElementById('addProductModal')) {
        document.getElementById("addProductModal").addEventListener('shown.bs.modal', function(e) {
            const productListElement = document.getElementById("productList");
            if (productListElement.innerHTML.trim() == '') {
                const form = new FormData();
                form.append('id', getOrderCustomerId());
        
                fetch(appUrl + `/get-products-list`, {
                        method: 'POST',
                        body: form,
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                        },
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById("productList").innerHTML = data.view;
                        } else {
                            Swal.fire(orderJs('error', 'Error'), orderJs('error_occurred', 'An error occurred.'), 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire(orderJs('error', 'Error'), orderJs('error_occurred', 'An error occurred.'), 'error');
                    });
            } else {
                init_pre_order_data();
            }
        });
    }

    if (document.getElementById('add-products')) {
        document.getElementById('add-products').addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('.products:checked');
            var isValid = true;
            var isOrder = document.getElementById('order_customer');
            
            checkboxes.forEach(function(checkbox) {
                const product_id = checkbox.value;
                if (!document.getElementById('sel-product-' + product_id)) {
                    var productOptions = {};
                    const card = document.getElementById('product-card-' + product_id);
    
                    var inputs = card.querySelectorAll('.product-option-section select, .product-option-section input:not([type="checkbox"]), .product-option-section textarea');
                    inputs.forEach(function(element) {
                        var optionName = element.name;
                        if (!optionName) {
                            return;
                        }

                        var selectedValue = element.value;

                        if (isOrder && element.hasAttribute('required')) {
                            var numVal = element.type === 'number' ? parseFloat(selectedValue) : null;
                            if (element.type === 'number' && (isNaN(numVal) || numVal <= 0)) {
                                element.focus();
                                isValid = false;
                                return;
                            }
                            if (element.type !== 'number' && !selectedValue) {
                                element.focus();
                                isValid = false;
                                return;
                            }
                        }

                        productOptions[optionName] = selectedValue;
                    });
    
                    if (isValid) {
                        var sellIn = card.getAttribute('data-sell-in') || 'qty';
                        productOptions['sell_in'] = sellIn;
                        productOptions['total_price'] = parseFloat(productOptions['price'])
                            * orderLineBillAmount(sellIn, productOptions['quantity'], productOptions['weight']);
                        selected_products.push(productOptions);
                    }
                }
            });

            if (!isValid) {
                Swal.fire({
                    title: orderJs('warning', 'Warning'),
                    text: orderJs('fill_required_fields', 'Please fill all required fields.'),
                    icon: 'warning',
                });
            } else if (selected_products.length != 0) {
                display_selected_products();
            } else {
                Swal.fire({
                    title: orderJs('warning', 'Warning'),
                    text: orderJs('select_product_for_bag', 'Please select any product to add to the bag.'),
                    icon: 'warning',
                });
            }
        });
    }

    if (document.getElementById('order_customer')) {
        document.getElementById('order_customer').addEventListener('change', function() {
            if (window.__formDraftRestoring) {
                return;
            }
            init_customer_details();
        });
    }

    if (document.getElementById('payment_method')) {
        document.getElementById('payment_method').addEventListener('change', function() {
            toggleTransferSlip();
        });
    }

    if (document.querySelector(".btns-order-action")) {
        document.querySelectorAll(".btns-order-action").forEach(function(btn) {
            btn.addEventListener("click", function(e) {
                e.preventDefault();
        
                if (this.classList.contains('back')) {
                    if (step === 'select_products') {
                        document.getElementById("customer_info").classList.toggle('d-none');
                        document.getElementById("add-product-info").classList.toggle('d-none');
                        document.querySelector("button.back").classList.toggle('d-none');
                        if (!isWalkInOrder()) {
                            document.getElementById("order_customer").disabled = false;
                        }
                        step = 'customer_info';
                    }
                } 
                
                if (this.classList.contains('next')) {
                    let allow_continue = true;
                    let requiredFields = document.querySelectorAll('form [required]');
        
                    requiredFields.forEach(function(itm) {
                        if (!itm.value && itm.getAttribute('name')) {
                            Swal.fire({
                                title: orderJs('warning', 'Warning'),
                                text: orderJs('fill_required_before_proceed', 'Please fill in all the required input before proceeding.'),
                                icon: 'warning',
                            });
                            allow_continue = false;
                            return false;
                        }
                    });
        
                    if (!allow_continue) {
                        return false;
                    }
        
                    if (step === 'customer_info') {
                        if (isWalkInOrder()) {
                            var walkInName = document.getElementById('walk_in_name');
                            var walkInPhone = document.getElementById('walk_in_phone');
                            if (!walkInName.value.trim()) {
                                Swal.fire({
                                    title: orderJs('warning', 'Warning'),
                                    text: orderJs('walk_in_name_required', 'Please enter walk-in customer name.'),
                                    icon: 'warning',
                                });
                                return false;
                            }
                            document.getElementById('attn_name').value = walkInName.value.trim();
                            document.getElementById('attn_contact').value = walkInPhone.value.trim();
                        }

                        document.getElementById("customer_info").classList.toggle('d-none');
                        document.getElementById("add-product-info").classList.toggle('d-none');
                        document.querySelector("button.back").classList.toggle('d-none');
                        document.getElementById("order_customer").disabled = true;
                        step = 'select_products';
                    } else if (step === 'select_products') {
                        if (!selected_products.length) {
                            Swal.fire({
                                title: orderJs('warning', 'Warning'),
                                text: orderJs('add_product_to_checkout', 'Please click "Add Product" to add product into the bag to checkout.'),
                                icon: 'warning',
                            });
                            return false;
                        }
        
                        var allowQr = window.orderAllowGenerateQr === true;
                        Swal.fire({
                            title: order_text,
                            text: order_subtext,
                            icon: 'info',
                            showCancelButton: true,
                            showDenyButton: allowQr,
                            confirmButtonColor: allowQr ? '#ff5d3b' : '#28a745',
                            denyButtonColor: '#023e7d',
                            cancelButtonColor: '#d33',
                            confirmButtonText: allowQr ? (window.orderQrConfirmText || 'Create & Generate QR') : orderJs('yes', 'Yes'),
                            denyButtonText: window.orderNoQrText || 'Create without QR'
                        }).then((result) => {
                            if (result.isConfirmed || result.isDenied) {
                                var form = document.getElementById('admin-order-create-form') || document.querySelector('form');
                                if (allowQr && form) {
                                    var flag = form.querySelector('input[name="generate_qr"]');
                                    if (!flag) {
                                        flag = document.createElement('input');
                                        flag.type = 'hidden';
                                        flag.name = 'generate_qr';
                                        form.appendChild(flag);
                                    }
                                    flag.value = result.isConfirmed ? '1' : '0';
                                }
                                if (window.FormDraft && form) {
                                    FormDraft.clear(form);
                                }
                                if (form && typeof form.requestSubmit === 'function') {
                                    form.requestSubmit();
                                } else if (form) {
                                    form.submit();
                                }
                            }
                        });
                    }
                }
            });
        });
    }

    if (document.querySelector('input[name="quantity[]"]')) {
        calculateTotal();
    }

    if (document.querySelector('.checkall')) {
        document.querySelector('.checkall').addEventListener('change', function () {
            const isChecked = this.checked;
        
            const checkboxes = document.querySelectorAll('.cs-checkbox');
        
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = isChecked;
            });

            if (document.querySelectorAll(".order-cbx-col input[type=checkbox]:checked").length) {
                document.querySelector(".download-zip").style.display = "block";
                document.getElementById('change-order-statuses').classList.remove('d-none');
                document.getElementById('change-order-lorry').classList.remove('d-none');
            } else {
                document.getElementById('change-order-statuses').classList.add('d-none');
                document.getElementById('change-order-lorry').classList.add('d-none');
                document.querySelector(".download-zip").style.display = "none";
            }
        });
    }

    if (document.getElementById('change-order-statuses')) {
        document.getElementById('change-order-statuses').addEventListener('click', function() {
            var selectedOrders = [];
            document.querySelectorAll("input[name='selected_orders[]']:checked").forEach(function(checkbox) {
                selectedOrders.push(checkbox.value);
            });
            document.querySelector('#order-statuses .orders_id').value = selectedOrders;
        });
    }

    if (document.getElementById('change-order-lorry')) {
        document.getElementById('change-order-lorry').addEventListener('click', function() {
            var selectedOrders = [];
            document.querySelectorAll("input[name='selected_orders[]']:checked").forEach(function(checkbox) {
                selectedOrders.push(checkbox.value);
            });
            document.querySelector('#assign-lorry .orders_id').value = selectedOrders;
        });
    }

    if (document.querySelector('.btn-change-lorry')) {
        document.querySelectorAll('.btn-change-lorry').forEach(function(button) {
            button.addEventListener('click', function() {
                var modal = document.querySelector('#change-lorry');
                modal.querySelector('.orders_id').value = this.dataset.id;

                var fulfillmentSelect = modal.querySelector('#modal_fulfillment_type');
                fulfillmentSelect.value = this.dataset.fulfillment || 'delivery';
                fulfillmentSelect.dispatchEvent(new Event('change', { bubbles: true }));

                var dateSelect = modal.querySelector('#modal_delivery_date');
                if (dateSelect) {
                    var deliveryDate = this.dataset.deliveryDate || '';
                    if (typeof window.ensureModalDeliveryDateOption === 'function') {
                        window.ensureModalDeliveryDateOption(deliveryDate);
                    }
                    dateSelect.value = deliveryDate;
                }

                modal.querySelector('#order_driver_id').value = this.dataset.lorry || '';
                modal.querySelector('#order_driver_id').dispatchEvent(new Event('change', { bubbles: true }));

                if (typeof window.prefillChangeLorryDeliverySlots === 'function') {
                    window.prefillChangeLorryDeliverySlots(this.dataset.deliverySlot, this.dataset.orderId);
                }
            });
        });
    }

    if (document.querySelector('.btn-add-order-weight')) {
        document.querySelectorAll('.btn-add-order-weight').forEach(function(button) {
            button.addEventListener('click', function() {
                document.querySelector('#add-weight .orders_id').value = this.dataset.id;
                const modalBody = document.querySelector('#add-weight .modal-body');
                modalBody.innerHTML = `<div class="text-center p-4">
                    <div class="spinner-border spinner-border-sm" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>`;

                const form = new FormData();
                form.append('id', this.dataset.id);

                fetch(appUrl + `/admin/order-products-list`, {
                    method: 'POST',
                    body: form,
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    }
                })
                .then(response => response.json())
                .then(data => {
                    modalBody.innerHTML = data.view;
                })
                .catch(error => {
                    Swal.fire(orderJs('error', 'Error'), orderJs('error_occurred', 'An error occurred.'), 'error');
                    console.log(error);
                });
            });
        });
    }

    if (document.querySelector('.btn-add-to-cart')) {
        document.querySelectorAll('.btn-add-to-cart').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const modalBody = document.getElementById('add-to-cart-form').querySelector('.modal-body');
                modalBody.innerHTML = `
                    <div class="p-5 text-center">
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>`;

                document.getElementById('add-to-cart-form').setAttribute('action', btn.getAttribute('data-action'));
                
                const form = new FormData();
                form.append('id', btn.getAttribute('data-id'));

                fetch(productInfoUrl, {
                    method: "POST",
                    body: form,
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    },
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    modalBody.innerHTML = data.view;
                    modalBody.querySelectorAll('.situation-btn-group').forEach(function (group) {
                        const hiddenInput = document.getElementById(group.dataset.target);
                        if (!hiddenInput || hiddenInput.value) {
                            return;
                        }
                        const firstBtn = group.querySelector('.situation-preset-btn');
                        if (firstBtn) {
                            firstBtn.classList.add('active');
                            hiddenInput.value = firstBtn.dataset.value;
                        }
                    });
                })
                .catch(error => {
                    modalBody.innerHTML = '<div class="alert alert-danger mb-0">Unable to load product details. Please try again.</div>';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire(orderJs('error', 'Error'), orderJs('error_occurred', 'An error occurred.'), 'error');
                    }
                });
            });
        });
    }

    if (document.getElementById('select-all')) {
        document.getElementById('select-all').addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('.products-card input[type="checkbox"]');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = !checkbox.checked;
            });
        });        
    }

    if (document.getElementById('report-filters')) {
        const exportBtn = document.getElementById('export-excel-btn');
        const form = document.getElementById('report-filters');

        exportBtn.classList.add('disabled');

        form.addEventListener('input', function() {
            const hasValue = Array.from(form.elements).some(element => {
                return (element.tagName === 'INPUT' || element.tagName === 'SELECT') && element.value.trim() !== '';
            });

            if (hasValue) {
                exportBtn.classList.remove('disabled');
            } else {
                exportBtn.classList.add('disabled');
            }
        });

        form.addEventListener('change', function() {
            const hasValue = Array.from(form.elements).some(element => {
                return (element.tagName === 'INPUT' || element.tagName === 'SELECT') && element.value.trim() !== '';
            });

            if (hasValue) {
                exportBtn.classList.remove('disabled');
            } else {
                exportBtn.classList.add('disabled');
            }
        });
    }

    if (document.getElementById('export-excel-btn')) {
        document.getElementById('export-excel-btn').addEventListener('click', function(e) {
            e.preventDefault();
        
            // Get the form values
            var filterId = document.getElementById('filterId').value;
            var filterFromDate = document.getElementById('filterFromDate').value;
            var filterToDate = document.getElementById('filterToDate').value;
            var status = document.getElementById('status').value;
            var driver = document.getElementById('driver').value;
            var customer = document.querySelector('select[name="customer"]').value;
            var area = document.getElementById('area').value;
        
            // Construct the query string
            var queryString = `?id=${encodeURIComponent(filterId)}&fdate=${encodeURIComponent(filterFromDate)}&tdate=${encodeURIComponent(filterToDate)}&status=${encodeURIComponent(status)}&driver=${encodeURIComponent(driver)}&customer=${encodeURIComponent(customer)}&area=${encodeURIComponent(area)}`;
        
            // Get the export URL
            var baseUrl = this.getAttribute('href');
        
            // Redirect to the new URL with query parameters
            window.location.href = baseUrl + queryString;
        });
    }
});
    
document.addEventListener('change', function(event) {
    if (event.target.matches('.toggle-product-options')) {
        if (document.getElementById('order_customer')) {
            const el = document.getElementById('product-option-' + event.target.value);
            if (event.target.checked) {
                el.classList.remove('d-none');
            } else {
                el.classList.add('d-none');
            }
        }
    }

    if (event.target.matches('input[name="quantity[]"], input[name="weight[]"]')) {
        syncBagQuantityToSelectedProducts(event.target);
        calculateTotal();
    }
});

document.addEventListener('input', function(event) {
    if (event.target.matches('input[name="quantity[]"], input[name="weight[]"]')) {
        syncBagQuantityToSelectedProducts(event.target);
        calculateTotal();
    }
});

document.addEventListener('click', function(event) {
    const adjustBtn = event.target.closest('.btn-adjust-qty');
    if (adjustBtn) {
        const input = document.getElementById(adjustBtn.dataset.target);
        if (input) {
            const step = parseFloat(input.step) || 1;
            const min = parseFloat(input.min) || 0.001;
            let value = parseFloat(input.value) || min;

            if (adjustBtn.dataset.action === 'plus') {
                value += step;
            } else {
                value = Math.max(min, value - step);
            }

            input.value = Number(value.toFixed(3));
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
        return;
    }

    if (event.target.closest('.remove-from-bag')) {
        var index = Array.from(document.querySelectorAll('.remove-from-bag')).indexOf(event.target.closest('.remove-from-bag'));

        const sel = event.target.closest('.sel-product');
        if (sel.getAttribute('data-id')) {
            const form = new FormData();
            form.append('id', sel.getAttribute('data-id'));

            fetch(appUrl + '/admin/delete-customer-visibility-product', {
                method: "POST",
                body: form,
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                },
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                selected_products.splice(index, 1);
                sel.remove();
                display_selected_products();
            })
            .catch(error => {
                Swal.fire(orderJs('error', 'Error'), orderJs('error_occurred', 'An error occurred.'), 'error');
            });
        } else {
            selected_products.splice(index, 1);
            sel.remove();
            display_selected_products();
        }
    }
    
    if (event.target.closest('.btn-plus')) {
        const quantityInput = document.getElementById('quantity');
        let currentValue = parseInt(quantityInput.value);
        quantityInput.value = currentValue + 1;
        updateButtonState(currentValue + 1);
    }

    if (event.target.closest('.btn-minus')) {
        const quantityInput = document.getElementById('quantity');
        let currentValue = parseInt(quantityInput.value);
        if (currentValue > 1) {
            quantityInput.value = currentValue - 1;
            updateButtonState(currentValue - 1);
        }
    }
    
     if (event.target.closest('.btn-plus-weight')) {
        const quantityInput = document.getElementById('weight');
        let currentValue = parseInt(quantityInput.value);
        quantityInput.value = currentValue + 1;
        updateButtonState(currentValue + 1);
    }

    if (event.target.closest('.btn-minus-weight')) {
        const quantityInput = document.getElementById('weight');
        let currentValue = parseInt(quantityInput.value);
        if (currentValue > 1) {
            quantityInput.value = currentValue - 1;
            updateButtonState(currentValue - 1);
        }
    }
});

function isWalkInOrder() {
    var walkInCheckbox = document.getElementById('is_walk_in');
    return walkInCheckbox && walkInCheckbox.checked;
}

function getOrderCustomerId() {
    if (isWalkInOrder()) {
        return 'products_visibility';
    }

    if (document.getElementById('order_customer') && document.getElementById('order_customer').value) {
        return document.getElementById('order_customer').value;
    }

    return 'products_visibility';
}

function populateOrderPaymentMethods(methods) {
    var paymentMethodElement = document.getElementById('payment_method');
    if (!paymentMethodElement) {
        return;
    }

    var placeholder = window.selectPaymentMethodPlaceholder || '-- Select Payment Method --';
    paymentMethodElement.innerHTML = '<option value="" selected>' + placeholder + '</option>';

    Object.keys(methods).forEach(function(key) {
        var newOption = document.createElement('option');
        newOption.value = key;
        newOption.textContent = methods[key];
        paymentMethodElement.appendChild(newOption);
    });

    if (paymentMethodElement.getAttribute('data-selected')) {
        paymentMethodElement.value = paymentMethodElement.getAttribute('data-selected');
    }

    if (window.jQuery && jQuery(paymentMethodElement).data('select2')) {
        jQuery(paymentMethodElement).val(paymentMethodElement.value).trigger('change');
    }
}

function init_customer_details(options) {
    options = options || {};
    var order_customer = document.getElementById('order_customer');
    var customerInfo = document.getElementById('customer_info');
    var submitButton = document.querySelector("form button[type=submit]");

    if (!order_customer) {
        return Promise.resolve(null);
    }

    if (!options.paymentMethodsOnly) {
        customerInfo.classList.add('d-none');
        if (submitButton) {
            submitButton.classList.add('d-none');
        }
    }

    if (!order_customer.value) {
        return Promise.resolve(null);
    }

    const form = new FormData();
    form.append('id', order_customer.value);

    return fetch(appUrl + `/admin/order/get-customer-info`, {
        method: 'POST',
        body: form,
        headers: {
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.order_payment_methods) {
            populateOrderPaymentMethods(data.order_payment_methods);
        }

        if (!options.paymentMethodsOnly) {
            Object.keys(data.customer).forEach(function(field) {
                if (field === 'payment_method') {
                    return;
                }

                if (document.getElementById(field)) {
                    document.getElementById(field).value = data.customer[field];
                }
            });

            if (window.jQuery && jQuery('#area').length && jQuery('#area').data('select2')) {
                jQuery('#area').trigger('change.select2');
            }
        }

        customerInfo.classList.remove('d-none');
        var nextBtn = document.querySelector("form button.next");
        if (nextBtn) {
            nextBtn.classList.remove('d-none');
        }
        document.getElementById('transferSlipGroup').style.display = 'none';

        return data;
    })
    .catch(error => {
        Swal.fire(orderJs('error', 'Error'), orderJs('error_occurred', 'An error occurred.'), 'error');
        console.log(error);
        throw error;
    });
}

function toggleTransferSlip() {
    var paymentMethod = document.getElementById('payment_method').value;
    var transferSlipGroup = document.getElementById('transferSlipGroup');
    var transferSlip = document.getElementById('transfer_slip');

    if (paymentMethod === 'bank-transfer') {
        transferSlipGroup.style.display = 'block';
        transferSlipGroup.setAttribute('required', true);
        transferSlip.setAttribute('required', true);
    } else {
        transferSlipGroup.style.display = 'none';
        transferSlipGroup.removeAttribute('required');
        transferSlip.removeAttribute('required');
    }
}

function display_selected_products() {
    var totalPrice = 0;
    var productHtml = '';
    
    selected_products.forEach(function(product, index) {
        var optionHtml = '';
        var optionHtml1 = '';
        
        if (document.getElementById('order_customer')) {
            for (var key in product) {
                if (product.hasOwnProperty(key) && !['product_id', 'product_name', 'price', 'quantity',  'weight', 'remark', 'total_price', ''].includes(key)) {
                    optionHtml += `<input type="hidden" name="product_options[${index}][${key}]" value="${product[key]}"/>`;
                    optionHtml1 += `<p class="mb-1">${formatProductMetaLine(key, product[key])}</p>`;
                    
                    if (document.getElementById('productOption-' + key)) {
                        const selectElement = document.getElementById('productOption-' + key);
                        selectElement.value = product[key];
                    }
                }
            }
        }

        let totalPriceForProduct = parseFloat(product.total_price);
        productHtml += `<div class="sel-product mb-3" id="sel-product-${product.product_id}" ${product.id ? `data-id="${product.product_id}"` : ''}>
            <input type="hidden" name="product_id[]" value="${product.product_id}"/>
                <div class="card"><div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-2">
                        <h5>${product.product_name}</h5>
                        <div class="remove-from-bag">
                            <a role="button"><i class="fa fa-trash"></i> ${orderUi('remove', 'Remove')}</a>
                        </div>
                    </div>`;

        if (document.getElementById('order_customer')) {
            var sellIn = product.sell_in || 'qty';

            if (sellIn === 'qty_bill_weight') {
                productHtml += `
                ${optionHtml}
                <div class="form-group mb-3">
                    <label class="mb-2">${orderUi('quantity', 'Quantity')}</label>
                    <span class="text-danger"> *</span>
                    <div class="btn-group w-100" role="group">
                        <button type="button" class="btn btn-outline-primary btn-adjust-qty" data-target="bagQty_${product.product_id}" data-action="minus">
                            <i class="fa fa-minus"></i>
                        </button>
                        <input type="number" class="form-control text-center add-products-quantity" id="bagQty_${product.product_id}" name="quantity[]" value="${product.quantity || 1}" data-pid="${product.product_id}" data-price="${product.price}" data-field="quantity" data-sell-in="qty_bill_weight" min="0.001" step="0.001" required>
                        <button type="button" class="btn btn-outline-primary btn-adjust-qty" data-target="bagQty_${product.product_id}" data-action="plus">
                            <i class="fa fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group mb-3">
                    <label class="mb-2">${orderUi('estimated_weight', 'Estimated Weight (KG)')} ${orderUi('optional', '(Optional)')}</label>
                    <div class="btn-group w-100" role="group">
                        <button type="button" class="btn btn-outline-primary btn-adjust-qty" data-target="bagBillWeight_${product.product_id}" data-action="minus">
                            <i class="fa fa-minus"></i>
                        </button>
                        <input type="number" class="form-control text-center add-products-bill-weight" id="bagBillWeight_${product.product_id}" name="weight[]" value="${product.weight || ''}" data-pid="${product.product_id}" data-price="${product.price}" data-field="bill_weight" data-sell-in="qty_bill_weight" min="0" step="0.001">
                        <button type="button" class="btn btn-outline-primary btn-adjust-qty" data-target="bagBillWeight_${product.product_id}" data-action="plus">
                            <i class="fa fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group mb-3">
                    <label class="mb-2">${orderUi('remark', 'Remark')}</label>
                    <textarea class="form-control" name="remark[]">${product.remark || ''}</textarea>
                </div>
            `;
            } else if (sellIn === 'weight') {
                productHtml += `
                ${optionHtml}
                <div class="form-group mb-3">
                    <label class="mb-2">${orderUi('order_qty_kg', 'Order Qty (KG)')}</label>
                    <span class="text-danger"> *</span>
                    <div class="btn-group w-100" role="group">
                        <button type="button" class="btn btn-outline-primary btn-adjust-qty" data-target="bagWeight_${product.product_id}" data-action="minus">
                            <i class="fa fa-minus"></i>
                        </button>
                        <input type="number" class="form-control text-center add-products-weight" id="bagWeight_${product.product_id}" name="weight[]" value="${product.weight || 1}" data-pid="${product.product_id}" data-price="${product.price}" data-field="weight" data-sell-in="weight" min="0.001" step="0.001" required>
                        <button type="button" class="btn btn-outline-primary btn-adjust-qty" data-target="bagWeight_${product.product_id}" data-action="plus">
                            <i class="fa fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group mb-3">
                    <label class="mb-2">${orderUi('remark', 'Remark')}</label>
                    <textarea class="form-control" name="remark[]">${product.remark || ''}</textarea>
                </div>
            `;
            } else {
                productHtml += `
                ${optionHtml}
                <div class="form-group mb-3">
                    <label class="mb-2">${orderUi('quantity', 'Quantity')}</label>
                    <span class="text-danger"> *</span>
                    <div class="btn-group w-100" role="group">
                        <button type="button" class="btn btn-outline-primary btn-adjust-qty" data-target="bagQty_${product.product_id}" data-action="minus">
                            <i class="fa fa-minus"></i>
                        </button>
                        <input type="number" class="form-control text-center add-products-quantity" id="bagQty_${product.product_id}" name="quantity[]" value="${product.quantity || 1}" data-pid="${product.product_id}" data-price="${product.price}" data-field="quantity" data-sell-in="qty" min="0.001" step="0.001" required>
                        <button type="button" class="btn btn-outline-primary btn-adjust-qty" data-target="bagQty_${product.product_id}" data-action="plus">
                            <i class="fa fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group mb-3">
                    <label class="mb-2">${orderUi('remark', 'Remark')}</label>
                    <textarea class="form-control" name="remark[]">${product.remark || ''}</textarea>
                </div>
            `;
            }
           
            var priceLine = orderUi('price_label', 'Price: RM :price', { price: product.price });
            var totalAmountHtml = '<span id="product-' + product.product_id + '-total">' + totalPriceForProduct.toFixed(2) + '</span>';
            var totalLine = orderUi('total_price_label', 'Total Price: RM :amount', { amount: totalAmountHtml });

            productHtml += `
                ${optionHtml1}
                <p class="mb-1">${priceLine}</p>
                <p class="mb-1"><strong>${totalLine}</strong></p>
            `;
        }
        
        productHtml += `</div></div></div>`;
        totalPrice += totalPriceForProduct;
    });

    document.getElementById('product_bag-item').innerHTML = productHtml;
    if (document.getElementById('total-price')) {
        document.getElementById('total-price').textContent = totalPrice.toFixed(2);
    }

    var modal = bootstrap.Modal.getInstance(document.getElementById('addProductModal'));
    if (modal) {
        modal.hide();
    }
}

function syncBagQuantityToSelectedProducts(input) {
    const pid = input.getAttribute('data-pid');
    const value = input.value;
    const field = input.getAttribute('data-field') || 'quantity';
    const product = selected_products.find(function(item) {
        return String(item.product_id) === String(pid);
    });

    if (!product) {
        return;
    }

    if (field === 'bill_weight') {
        product.weight = value;
    } else if (field === 'weight') {
        product.weight = value;
        delete product.quantity;
    } else {
        product.quantity = value;
        if (product.sell_in !== 'qty_bill_weight' && product.sell_in !== 'weight') {
            delete product.weight;
        }
    }

    product.total_price = parseFloat(product.price)
        * orderLineBillAmount(product.sell_in, product.quantity, product.weight);
}

function calculateTotal() {
    let total = 0;
    const pids = new Set();

    document.querySelectorAll('input[data-pid]').forEach(function(input) {
        pids.add(input.getAttribute('data-pid'));
    });

    pids.forEach(function(pid) {
        const qtyInput = document.querySelector('input[data-pid="' + pid + '"][data-field="quantity"]');
        const weightInput = document.querySelector('input[data-pid="' + pid + '"][data-field="bill_weight"]')
            || document.querySelector('input[data-pid="' + pid + '"][data-field="weight"]');
        const anchor = qtyInput || weightInput;

        if (!anchor) {
            return;
        }

        const sellIn = anchor.getAttribute('data-sell-in') || 'qty';
        const price = parseFloat(anchor.getAttribute('data-price')) || 0;
        const qty = qtyInput ? (parseFloat(qtyInput.value) || 0) : 0;
        const weight = weightInput ? (parseFloat(weightInput.value) || 0) : 0;
        const lineTotal = price * orderLineBillAmount(sellIn, qty, weight);
        total += lineTotal;

        const totalEl = document.getElementById('product-' + pid + '-total');
        if (totalEl) {
            totalEl.innerHTML = lineTotal.toFixed(2);
        }
    });

    if (document.getElementById('total-price')) {
        document.getElementById('total-price').textContent = total.toFixed(2);
    }
}

function updateButtonState(quantity) {
    const minusButton = document.querySelector('.btn-minus');
    const minusButtonWeight = document.querySelector('.btn-minus-weight');
   
    if(minusButton != undefined)
        minusButton.disabled = quantity <= 1;
    if(minusButtonWeight != undefined)
         minusButtonWeight.disabled = quantity <= 1;
}

function init_pre_order_data() {
    // select aleady chosed products
    if (typeof productIds !== 'undefined') {
        const checkboxes = document.querySelectorAll('.toggle-product-options');
        checkboxes.forEach(checkbox => {
            if (productIds.includes(parseInt(checkbox.value))) {
                checkbox.checked = true;
                const cardBody = checkbox.closest('.card-body');
                if (cardBody && cardBody.querySelector('.product-option-section')) {
                    cardBody.querySelector('.product-option-section').classList.remove('d-none');
                }
            }
        });
    }

    // select product options selected
    if (selected_products.length != 0) {
        selected_products.forEach(function(product, index) {
            for (var key in product) {
                if (key == 'product_id') {
                    const quantityElement = document.getElementById('productQuantity_' + product['product_id']);
                    if (quantityElement && product['quantity']) {
                        quantityElement.value = product['quantity'];
                    }

                    const weightElement = document.getElementById('productWeight_' + product['product_id']);
                    if (weightElement && product['weight']) {
                        weightElement.value = product['weight'];
                    }
                    
                    const remarkElement = document.getElementById('productRemark_' + product['product_id']);
                    if (remarkElement) {
                        remarkElement.value = product['remark'];
                    }
                    
                    // const nosElement = document.getElementById('nos_' + product['product_id']);
                    // if (nosElement) {
                    //     nosElement.value = product['nos'];
                    // }
                } else if (product.hasOwnProperty(key) && !['product_id', 'product_name', 'price', 'quantity', 'remark', 'total_price', ''].includes(key)) {
                    if (document.getElementById('productOption-' + key)) {
                        const selectElement = document.getElementById('productOption-' + key);
                        selectElement.value = product[key];
                    }
                }
            }
        });
    }
}

document.addEventListener('click', function (event) {
    const weightBtn = event.target.closest('.weight-preset-btn');
    if (weightBtn) {
        const target = document.getElementById(weightBtn.dataset.target);
        if (target) {
            target.value = weightBtn.dataset.value;
            target.dispatchEvent(new Event('input', { bubbles: true }));
            target.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    const situationBtn = event.target.closest('.situation-preset-btn');
    if (situationBtn) {
        const group = situationBtn.closest('.situation-btn-group');
        if (!group) {
            return;
        }
        group.querySelectorAll('.situation-preset-btn').forEach(function (btn) {
            btn.classList.remove('active');
        });
        situationBtn.classList.add('active');
        const hiddenInput = document.getElementById(group.dataset.target);
        if (hiddenInput) {
            hiddenInput.value = situationBtn.dataset.value;
        }
    }
});
