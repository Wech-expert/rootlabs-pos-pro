(function () {
    'use strict';

    function getLabels() {
        return window.mxPosAdminLabels || {};
    }

    function bindToggle(buttonId, formId, openLabelKey, closedLabelKey) {
        var button = document.getElementById(buttonId);
        var form = document.getElementById(formId);
        var labels = getLabels();

        if (!button || !form) {
            return;
        }

        button.addEventListener('click', function () {
            var isHidden = form.style.display === 'none' || form.style.display === '';
            form.style.display = isHidden ? 'block' : 'none';
            button.textContent = isHidden
                ? (labels[closedLabelKey] || 'Cancelar')
                : (labels[openLabelKey] || button.textContent);
        });
    }

    function bindToggles() {
        bindToggle('mx-pos-toggle-branch-form', 'mx-pos-branch-form', 'addBranch', 'cancel');
        bindToggle('mx-pos-toggle-register-form', 'mx-pos-register-form', 'addRegister', 'cancel');
        bindToggle('mx-pos-toggle-employee-form', 'mx-pos-employee-form', 'addEmployee', 'cancel');
        bindToggle('mx-pos-toggle-payment-method-form', 'mx-pos-payment-method-form', 'addPaymentMethod', 'cancel');
    }

    function printWindowWhenReady(win) {
        var fontReady = win.document.fonts && win.document.fonts.ready
            ? win.document.fonts.ready.catch(function () {})
            : Promise.resolve();

        var imageReady = Array.prototype.slice.call(win.document.images).map(function (image) {
            if (image.complete) {
                return Promise.resolve();
            }

            return new Promise(function (resolve) {
                image.addEventListener('load', resolve, { once: true });
                image.addEventListener('error', resolve, { once: true });
            });
        });

        Promise.race([
            Promise.all([fontReady].concat(imageReady)),
            new Promise(function (resolve) { setTimeout(resolve, 900); })
        ]).then(function () {
            win.print();
        });

        win.addEventListener('afterprint', function () {
            win.close();
        });
    }

    function bindCutReprint() {
        var config = document.getElementById('mx-pos-cut-reprint-config');

        if (!config) {
            return;
        }

        var restUrl = config.getAttribute('data-rest-url') || '';
        var restNonce = config.getAttribute('data-rest-nonce') || '';
        var popupMessage = config.getAttribute('data-popup-message') || 'Could not open the print window.';

        function reprintCut(cutId) {
            var url = restUrl + cutId + '/ticket';
            var win = window.open('', '_blank', 'width=420,height=760');

            if (!win) {
                window.alert(popupMessage);
                return;
            }

            window.fetch(url, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': restNonce,
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Error al obtener el ticket');
                    }

                    return response.json();
                })
                .then(function (data) {
                    if (!data || typeof data.html !== 'string') {
                        throw new Error('Respuesta inválida');
                    }

                    if (data.html.indexOf('data-ticket-width="58mm"') !== -1) {
                        try {
                            win.resizeTo(340, 680);
                        } catch (e) {}
                    }

                    win.document.write(data.html);
                    win.document.close();
                    win.focus();
                    printWindowWhenReady(win);
                })
                .catch(function (err) {
                    window.alert('Error: ' + (err.message || 'No se pudo generar el ticket'));
                    win.close();
                });
        }

        document.querySelectorAll('.mx-pos-reprint-cut').forEach(function (button) {
            button.addEventListener('click', function () {
                var cutId = parseInt(button.getAttribute('data-cut-id'), 10);

                if (cutId > 0) {
                    reprintCut(cutId);
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            bindToggles();
            bindCutReprint();
        });
    } else {
        bindToggles();
        bindCutReprint();
    }
})();
