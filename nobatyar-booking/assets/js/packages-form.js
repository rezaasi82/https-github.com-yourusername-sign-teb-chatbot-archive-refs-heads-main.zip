(function () {
    'use strict';

    var purchaseButtons = document.querySelectorAll('.nobatyar-package-purchase-btn');
    var form            = document.getElementById('nobatyar-package-purchase-form');

    if (!purchaseButtons.length || !form || typeof nobatyarPackages === 'undefined') {
        return;
    }

    var packageIdField = form.querySelector('#nobatyar-package-id');
    var messageBox      = form.querySelector('#nobatyar-package-message');

    function setMessage(text, isError) {
        messageBox.textContent = text;
        messageBox.classList.toggle('is-error', !!isError);
    }

    purchaseButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            packageIdField.value = button.getAttribute('data-package-id');
            setMessage('', false);
            form.hidden = false;
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        setMessage('', false);

        var payload = {
            package_id:     packageIdField.value,
            customer_name:  form.querySelector('#nobatyar-package-customer-name').value,
            customer_phone: form.querySelector('#nobatyar-package-customer-phone').value,
            customer_email: form.querySelector('#nobatyar-package-customer-email').value,
        };

        fetch(nobatyarPackages.restUrl + 'packages/purchase', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nobatyarPackages.nonce,
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
                    setMessage(result.data.message || 'خرید پکیج با خطا مواجه شد.', true);
                    return;
                }

                setMessage('پکیج با موفقیت خریداری شد. می‌توانید با شماره موبایل خود برای رزرو نشست‌ها اقدام کنید.', false);
                form.reset();
            })
            .catch(function () {
                setMessage('خرید پکیج با خطا مواجه شد.', true);
            });
    });
})();
