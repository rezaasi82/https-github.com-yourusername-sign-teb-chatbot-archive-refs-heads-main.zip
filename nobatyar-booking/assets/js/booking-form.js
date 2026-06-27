(function () {
    'use strict';

    var form = document.getElementById('nobatyar-booking-form');

    if (!form || typeof nobatyarBooking === 'undefined') {
        return;
    }

    var serviceField  = form.querySelector('#nobatyar-service');
    var providerField = form.querySelector('#nobatyar-provider');
    var dateField      = form.querySelector('#nobatyar-date');
    var slotField      = form.querySelector('#nobatyar-slot');
    var messageBox     = form.querySelector('#nobatyar-booking-message');

    var recurrenceEnableField = form.querySelector('#nobatyar-recurrence-enable');
    var recurrenceFields      = form.querySelector('#nobatyar-recurrence-fields');
    var recurrenceFrequencyField   = form.querySelector('#nobatyar-recurrence-frequency');
    var recurrenceOccurrencesField = form.querySelector('#nobatyar-recurrence-occurrences');

    if (recurrenceEnableField && recurrenceFields) {
        recurrenceEnableField.addEventListener('change', function () {
            recurrenceFields.hidden = !recurrenceEnableField.checked;
        });
    }

    function setMessage(text, isError) {
        messageBox.textContent = text;
        messageBox.classList.toggle('is-error', !!isError);
    }

    function resetSlots(placeholder) {
        slotField.innerHTML = '';
        var option = document.createElement('option');
        option.value = '';
        option.textContent = placeholder;
        slotField.appendChild(option);
    }

    function loadAvailableSlots() {
        var providerId = providerField.value;
        var serviceId   = serviceField.value;
        var date        = dateField.value;

        if (!providerId || !serviceId || !date) {
            return;
        }

        resetSlots('در حال بارگذاری...');

        var url = nobatyarBooking.restUrl + 'availability?provider_id=' + encodeURIComponent(providerId) +
            '&service_id=' + encodeURIComponent(serviceId) + '&date=' + encodeURIComponent(date);

        fetch(url, { headers: { 'X-WP-Nonce': nobatyarBooking.nonce } })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                resetSlots('انتخاب کنید');

                (data.slots || []).forEach(function (slot) {
                    var option = document.createElement('option');
                    option.value = slot.start;
                    option.textContent = slot.start.substring(11, 16);

                    if (typeof slot.capacity_remaining !== 'undefined') {
                        option.textContent += ' (' + slot.capacity_remaining + ' جای خالی)';
                    }

                    slotField.appendChild(option);
                });

                if (!data.slots || data.slots.length === 0) {
                    resetSlots('بازه آزادی برای این تاریخ وجود ندارد');
                }
            })
            .catch(function () {
                resetSlots('خطا در بارگذاری بازه‌های زمانی');
            });
    }

    [serviceField, providerField, dateField].forEach(function (field) {
        field.addEventListener('change', loadAvailableSlots);
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        setMessage('', false);

        var payload = {
            provider_id:       providerField.value,
            service_id:        serviceField.value,
            booking_datetime:  slotField.value,
            customer_name:     form.querySelector('#nobatyar-customer-name').value,
            customer_phone:    form.querySelector('#nobatyar-customer-phone').value,
            customer_email:    form.querySelector('#nobatyar-customer-email').value,
        };

        var isRecurring = !!(recurrenceEnableField && recurrenceEnableField.checked);
        var endpoint     = 'bookings';

        if (isRecurring) {
            endpoint = 'bookings/recurring';
            payload.recurrence_frequency   = recurrenceFrequencyField.value;
            payload.recurrence_occurrences = recurrenceOccurrencesField.value;
        }

        fetch(nobatyarBooking.restUrl + endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nobatyarBooking.nonce,
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
                    setMessage(result.data.message || 'ثبت نوبت با خطا مواجه شد.', true);
                    return;
                }

                if (isRecurring && result.data.ids) {
                    setMessage('سری نوبت‌های تکرارشونده (' + result.data.ids.length + ' نوبت) با موفقیت ثبت شد.', false);
                } else {
                    setMessage('نوبت شما با موفقیت ثبت شد.', false);
                }

                form.reset();
                resetSlots('ابتدا سرویس‌دهنده، خدمت و تاریخ را انتخاب کنید');

                if (recurrenceFields) {
                    recurrenceFields.hidden = true;
                }
            })
            .catch(function () {
                setMessage('ثبت نوبت با خطا مواجه شد.', true);
            });
    });
})();
