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

    var couponCodeField   = form.querySelector('#nobatyar-coupon-code');
    var couponApplyBtn    = form.querySelector('#nobatyar-coupon-apply-btn');
    var couponResultField = form.querySelector('#nobatyar-coupon-result');
    var appliedCouponCode = '';

    function resetCouponResult(text, isError) {
        if (!couponResultField) {
            return;
        }

        couponResultField.textContent = text || '';
        couponResultField.classList.toggle('is-error', !!isError);
    }

    if (couponApplyBtn && couponCodeField) {
        couponApplyBtn.addEventListener('click', function () {
            var code      = couponCodeField.value.trim();
            var serviceId = serviceField.value;

            appliedCouponCode = '';

            if (!code) {
                resetCouponResult('کد تخفیف را وارد کنید.', true);
                return;
            }

            if (!serviceId) {
                resetCouponResult('ابتدا خدمت را انتخاب کنید.', true);
                return;
            }

            resetCouponResult('در حال بررسی...', false);

            var url = nobatyarBooking.restUrl + 'coupons/validate?code=' + encodeURIComponent(code) +
                '&service_id=' + encodeURIComponent(serviceId);

            fetch(url, { headers: { 'X-WP-Nonce': nobatyarBooking.nonce } })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    if (!result.ok) {
                        resetCouponResult(result.data.message || 'کد تخفیف معتبر نیست.', true);
                        return;
                    }

                    appliedCouponCode = code;

                    var discountLabel = result.data.discount_type === 'percent'
                        ? (result.data.discount_value + '%')
                        : result.data.discount_value;

                    resetCouponResult('کد تخفیف اعمال شد (' + discountLabel + ').', false);
                })
                .catch(function () {
                    resetCouponResult('خطا در بررسی کد تخفیف.', true);
                });
        });
    }

    var usePackageField     = form.querySelector('#nobatyar-use-package');
    var packageFields        = form.querySelector('#nobatyar-package-fields');
    var packageLookupBtn     = form.querySelector('#nobatyar-package-lookup-btn');
    var packagePurchaseField = form.querySelector('#nobatyar-package-purchase');
    var packagePurchases     = [];

    if (usePackageField && packageFields) {
        usePackageField.addEventListener('change', function () {
            packageFields.hidden = !usePackageField.checked;
            serviceField.disabled = usePackageField.checked;

            if (recurrenceEnableField) {
                recurrenceEnableField.disabled = usePackageField.checked;

                if (usePackageField.checked) {
                    recurrenceEnableField.checked = false;

                    if (recurrenceFields) {
                        recurrenceFields.hidden = true;
                    }
                }
            }
        });
    }

    function resetPackagePurchaseOptions(placeholder) {
        packagePurchaseField.innerHTML = '';
        var option = document.createElement('option');
        option.value = '';
        option.textContent = placeholder;
        packagePurchaseField.appendChild(option);
    }

    if (packageLookupBtn && packagePurchaseField) {
        packageLookupBtn.addEventListener('click', function () {
            var phone = form.querySelector('#nobatyar-customer-phone').value;

            if (!phone) {
                setMessage('برای بررسی اعتبار پکیج ابتدا شماره موبایل را وارد کنید.', true);
                return;
            }

            setMessage('', false);
            resetPackagePurchaseOptions('در حال بررسی...');

            var url = nobatyarBooking.restUrl + 'packages/purchases/lookup?phone=' + encodeURIComponent(phone);

            fetch(url, { headers: { 'X-WP-Nonce': nobatyarBooking.nonce } })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    packagePurchases = data.purchases || [];

                    if (!packagePurchases.length) {
                        resetPackagePurchaseOptions('پکیج فعالی برای این شماره یافت نشد');
                        return;
                    }

                    resetPackagePurchaseOptions('انتخاب کنید');

                    packagePurchases.forEach(function (purchase) {
                        var option = document.createElement('option');
                        option.value = purchase.id;
                        option.textContent = purchase.package_name + ' (' + purchase.sessions_remaining + ' از ' + purchase.sessions_total + ' باقی‌مانده)';
                        packagePurchaseField.appendChild(option);
                    });
                })
                .catch(function () {
                    resetPackagePurchaseOptions('خطا در بررسی اعتبار پکیج');
                });
        });
    }

    if (packagePurchaseField) {
        packagePurchaseField.addEventListener('change', function () {
            var selected = packagePurchases.filter(function (purchase) {
                return String(purchase.id) === packagePurchaseField.value;
            })[0];

            if (selected) {
                serviceField.value = selected.service_id;
                loadAvailableSlots();
            }
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

        var isUsingPackage = !!(usePackageField && usePackageField.checked);
        var isRecurring     = !isUsingPackage && !!(recurrenceEnableField && recurrenceEnableField.checked);
        var endpoint        = 'bookings';
        var payload;

        if (isUsingPackage) {
            endpoint = 'bookings/package-redeem';
            payload = {
                package_purchase_id: packagePurchaseField.value,
                provider_id:         providerField.value,
                booking_datetime:    slotField.value,
                customer_name:       form.querySelector('#nobatyar-customer-name').value,
                customer_phone:      form.querySelector('#nobatyar-customer-phone').value,
                customer_email:      form.querySelector('#nobatyar-customer-email').value,
            };
        } else {
            payload = {
                provider_id:       providerField.value,
                service_id:        serviceField.value,
                booking_datetime:  slotField.value,
                customer_name:     form.querySelector('#nobatyar-customer-name').value,
                customer_phone:    form.querySelector('#nobatyar-customer-phone').value,
                customer_email:    form.querySelector('#nobatyar-customer-email').value,
            };

            if (appliedCouponCode) {
                payload.coupon_code = appliedCouponCode;
            }

            if (isRecurring) {
                endpoint = 'bookings/recurring';
                payload.recurrence_frequency   = recurrenceFrequencyField.value;
                payload.recurrence_occurrences = recurrenceOccurrencesField.value;
            }
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
                serviceField.disabled = false;
                appliedCouponCode = '';
                resetCouponResult('', false);

                if (recurrenceFields) {
                    recurrenceFields.hidden = true;
                }

                if (packageFields) {
                    packageFields.hidden = true;
                    resetPackagePurchaseOptions('ابتدا شماره موبایل را بررسی کنید');
                }

                if (recurrenceEnableField) {
                    recurrenceEnableField.disabled = false;
                }
            })
            .catch(function () {
                setMessage('ثبت نوبت با خطا مواجه شد.', true);
            });
    });
})();
