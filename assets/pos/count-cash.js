(function() {
        var form = document.getElementById('mx-count-form');
        if (!form) return;

        var inputs = form.querySelectorAll('.mx-denom-input');
        var subtotals = form.querySelectorAll('.mx-denom-subtotal');
        var totalEl = document.getElementById('mx-count-total');

        function formatCurrency(amount) {
            return '$' + amount.toFixed(2);
        }

        function recalc() {
            var total = 0;
            inputs.forEach(function(input, i) {
                var qty = parseInt(input.value, 10) || 0;
                if (qty < 0) qty = 0;
                var val = parseFloat(input.getAttribute('data-denom-value')) || 0;
                var subtotal = qty * val;
                if (subtotals[i]) {
                    subtotals[i].textContent = formatCurrency(subtotal);
                }
                total += subtotal;
            });
            if (totalEl) {
                totalEl.textContent = formatCurrency(total);
            }
        }

        inputs.forEach(function(input) {
            input.addEventListener('input', recalc);
        });

        recalc();
    })();

(function () {
        var form = document.getElementById('mx-count-form');

        if (!form) {
            return;
        }

        var inputs = Array.prototype.slice.call(form.querySelectorAll('.mx-denom-input'));
        var totalNode = document.getElementById('mx-count-total');

        function parseMoneyValue(input) {
            var raw = input.getAttribute('data-value') || input.dataset.value || '';

            raw = String(raw).replace(',', '.').replace(/[^0-9.]/g, '');

            var amount = Number.parseFloat(raw);

            if (!Number.isFinite(amount) || amount <= 0) {
                var row = input.closest('.mx-card__denom-row');
                var label = row ? row.querySelector('.mx-card__denom-label') : null;
                var labelText = label ? label.textContent : '';
                var match = labelText.match(/([0-9]+(?:[.,][0-9]+)?)/);

                if (match) {
                    amount = Number.parseFloat(match[1].replace(',', '.'));
                }
            }

            return Number.isFinite(amount) ? amount : 0;
        }

        function parseQuantity(input) {
            var qty = Number.parseInt(input.value || '0', 10);

            if (!Number.isFinite(qty) || qty < 0) {
                return 0;
            }

            return qty;
        }

        function formatMoney(value) {
            return value.toLocaleString('es-MX', {
                style: 'currency',
                currency: 'MXN',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function recalcCountCash() {
            var total = 0;

            inputs.forEach(function (input) {
                var row = input.closest('.mx-card__denom-row');
                var subtotalNode = row ? row.querySelector('.mx-denom-subtotal') : null;
                var amount = parseMoneyValue(input);
                var qty = parseQuantity(input);
                var subtotal = amount * qty;

                total += subtotal;

                if (subtotalNode) {
                    subtotalNode.textContent = formatMoney(subtotal);
                }
            });

            if (totalNode) {
                totalNode.textContent = formatMoney(total);
            }
        }

        inputs.forEach(function (input) {
            input.addEventListener('input', recalcCountCash);
            input.addEventListener('change', recalcCountCash);
        });

        recalcCountCash();
    })();
