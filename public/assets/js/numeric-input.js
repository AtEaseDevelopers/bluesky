(function () {
    function parseNum(value) {
        var parsed = parseFloat(String(value || '').replace(/,/g, '').trim());
        return Number.isFinite(parsed) ? parsed : null;
    }

    function isZeroLike(value) {
        var parsed = parseNum(value);
        return parsed !== null && Math.abs(parsed) < 0.0000001;
    }

    function fieldToken(value) {
        return String(value || '').toLowerCase();
    }

    function isPricingInput(input) {
        if (!input || input.readOnly || input.disabled) {
            return false;
        }

        if (input.type !== 'number' && input.type !== 'text') {
            return false;
        }

        var haystack = [
            input.name,
            input.id,
            input.className,
            input.getAttribute('data-field'),
        ].map(fieldToken).join(' ');

        if (/quantity|qty|weight|nos|stock|preset|range-slider|filterpricefrom|filterpriceto/.test(haystack)) {
            return false;
        }

        if (/line-qty|line-weight|quantity-input|weight-input|add-products-quantity|add-products-weight|add-products-bill-weight/.test(haystack)) {
            return false;
        }

        var step = input.getAttribute('step');
        var stepNum = step !== null && step !== '' ? parseFloat(step) : null;
        if (stepNum !== null && !Number.isNaN(stepNum) && stepNum <= 0.01) {
            return true;
        }

        return /(?:^|[\[_])(amount|paid_amount|price|fee|adjustment|credit|payment|unit_price|delivery_fee|subtotal|total|min_price|max_price)(?:[\]_]|$)/.test(haystack)
            || haystack.indexOf('payment-amount') !== -1
            || haystack.indexOf('js-pay-amount') !== -1
            || haystack.indexOf('productprice') !== -1
            || haystack.indexOf('credit_amount') !== -1;
    }

    function zeroDefault(input) {
        var step = input.getAttribute('step');
        if (step && step.indexOf('.') !== -1) {
            return (0).toFixed(step.split('.')[1].length);
        }

        return '0.00';
    }

    document.addEventListener('focusin', function (event) {
        var input = event.target;
        if (!isPricingInput(input) || !isZeroLike(input.value)) {
            return;
        }

        input.dataset.autoClearDefault = input.value;
        input.dataset.autoClearActive = '1';
        input.value = '';

        if (typeof input.select === 'function') {
            window.requestAnimationFrame(function () {
                input.select();
            });
        }
    });

    document.addEventListener('focusout', function (event) {
        var input = event.target;
        if (input.dataset.autoClearActive !== '1') {
            return;
        }

        delete input.dataset.autoClearActive;

        if (String(input.value || '').trim() === '') {
            input.value = input.dataset.autoClearDefault || zeroDefault(input);
        }

        delete input.dataset.autoClearDefault;
    });
})();
