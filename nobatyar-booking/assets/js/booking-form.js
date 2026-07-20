(function () {
    'use strict';

    const form     = document.getElementById('nby-form');
    const errorBar = document.getElementById('nby-error');

    if (!form || typeof nobatyarBooking === 'undefined') {
        return;
    }

    const wrap = form.closest('.nby-wrap');

    // --- Field refs ---
    const serviceField          = document.getElementById('nby-service');
    const providerField         = document.getElementById('nby-provider');
    const dateHidden            = document.getElementById('nobatyar-date');   // written by jalali-datepicker.js
    const slotsBox              = document.getElementById('nby-slots');
    const slotHidden            = document.getElementById('nby-slot');
    const customerNameField     = document.getElementById('nby-customer-name');
    const customerPhoneField    = document.getElementById('nby-customer-phone');
    const customerEmailField    = document.getElementById('nby-customer-email');
    const couponCodeField       = document.getElementById('nby-coupon-code');
    const couponApplyBtn        = document.getElementById('nby-coupon-apply');
    const couponResultEl        = document.getElementById('nby-coupon-result');
    const giftCardCodeField     = document.getElementById('nby-gift-card-code');
    const giftCardApplyBtn      = document.getElementById('nby-gift-card-apply');
    const giftCardResultEl      = document.getElementById('nby-gift-card-result');
    const usePackageField       = document.getElementById('nby-use-package');
    const packageFieldsBox      = document.getElementById('nby-package-fields');
    const packageLookupBtn      = document.getElementById('nby-package-lookup');
    const packagePurchaseField  = document.getElementById('nby-package-purchase');
    const recurrenceEnableField = document.getElementById('nby-recurrence-enable');
    const recurrenceFieldsBox   = document.getElementById('nby-recurrence-fields');
    const recurrenceFreqField   = document.getElementById('nby-recurrence-frequency');
    const recurrenceOccField    = document.getElementById('nby-recurrence-occurrences');
    const successMessageEl      = document.getElementById('nby-success-message');
    const submitBtn             = document.getElementById('nby-submit');

    // --- State ---
    let appliedCouponCode   = '';
    let appliedGiftCardCode = '';
    let packagePurchases    = [];
    let slotDebounceTimer   = null;

    // --- Unified fetch wrapper ---
    function apiFetch(url, options) {
        const opts    = options || {};
        opts.headers  = Object.assign({ 'X-WP-Nonce': nobatyarBooking.nonce }, opts.headers || {});

        return fetch(url, opts).then(function (res) {
            return res.json().then(function (data) {
                return { ok: res.ok, data: data };
            });
        });
    }

    // --- Error / success display ---
    function showError(message) {
        errorBar.textContent = message;
        errorBar.hidden      = false;
        errorBar.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function hideError() {
        errorBar.hidden      = true;
        errorBar.textContent = '';
    }

    // --- Step navigation ---
    function goToStep(step) {
        if (wrap) {
            wrap.querySelectorAll('.nby-step[data-step]').forEach(function (el) {
                const s = parseInt(el.getAttribute('data-step'), 10);
                el.classList.toggle('is-active', s === step);
                el.classList.toggle('is-done',   s < step);
                if (s === step) {
                    el.setAttribute('aria-current', 'step');
                } else {
                    el.removeAttribute('aria-current');
                }
            });
        }

        form.querySelectorAll('.nby-panel[data-panel]').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-panel') !== String(step);
        });

        hideError();
    }

    function showSuccess(message) {
        form.querySelectorAll('.nby-panel[data-panel]').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-panel') !== 'success';
        });

        if (successMessageEl) {
            successMessageEl.textContent = message;
        }

        if (wrap) {
            wrap.querySelectorAll('.nby-step[data-step]').forEach(function (el) {
                el.classList.remove('is-active', 'is-done');
                el.removeAttribute('aria-current');
            });
        }

        hideError();
    }

    // --- Slot chips ---
    function setSlotHint(message) {
        if (slotsBox) {
            slotsBox.innerHTML = '<p class="nby-slots__hint">'
                + message.replace(/&/g, '&amp;').replace(/</g, '&lt;')
                + '</p>';
        }
        if (slotHidden) { slotHidden.value = ''; }
    }

    function renderSlots(slots) {
        if (!slotsBox) { return; }
        if (slotHidden) { slotHidden.value = ''; }
        slotsBox.innerHTML = '';

        if (!slots || slots.length === 0) {
            setSlotHint('بازه آزادی برای این تاریخ وجود ندارد');
            return;
        }

        slots.forEach(function (slot) {
            const label       = document.createElement('label');
            label.className   = 'nby-slot-chip';

            const radio       = document.createElement('input');
            radio.type        = 'radio';
            radio.name        = 'nby_slot_radio';
            radio.value       = slot.start;
            radio.className   = 'nby-slot-chip__input';

            radio.addEventListener('change', function () {
                if (slotHidden) { slotHidden.value = slot.start; }
                slotsBox.querySelectorAll('.nby-slot-chip').forEach(function (chip) {
                    chip.classList.remove('is-selected');
                });
                label.classList.add('is-selected');
            });

            const timeSpan    = document.createElement('span');
            timeSpan.className = 'nby-slot-chip__time';
            timeSpan.textContent = slot.start.substring(11, 16);

            label.appendChild(radio);
            label.appendChild(timeSpan);

            if (typeof slot.capacity_remaining !== 'undefined') {
                const capSpan      = document.createElement('span');
                capSpan.className  = 'nby-slot-chip__cap';
                capSpan.textContent = slot.capacity_remaining + ' جا';
                label.appendChild(capSpan);
            }

            slotsBox.appendChild(label);
        });
    }

    function loadSlots() {
        if (!serviceField || !providerField || !dateHidden) { return; }

        const providerId = providerField.value;
        const serviceId  = serviceField.value;
        const date       = dateHidden.value;

        if (!providerId || !serviceId || !date) { return; }

        setSlotHint('در حال بارگذاری...');

        const url = nobatyarBooking.restUrl + 'availability'
            + '?provider_id=' + encodeURIComponent(providerId)
            + '&service_id='  + encodeURIComponent(serviceId)
            + '&date='        + encodeURIComponent(date);

        apiFetch(url)
            .then(function (result) {
                renderSlots(result.data.slots || []);
            })
            .catch(function () {
                setSlotHint('خطا در بارگذاری بازه‌های زمانی');
            });
    }

    function debouncedLoadSlots() {
        clearTimeout(slotDebounceTimer);
        slotDebounceTimer = setTimeout(loadSlots, 180);
    }

    if (dateHidden) {
        dateHidden.addEventListener('change', debouncedLoadSlots);
    }

    // --- Step 1 → 2 ---
    const next1Btn = document.getElementById('nby-next-1');
    if (next1Btn) {
        next1Btn.addEventListener('click', function () {
            if (!serviceField || !serviceField.value) {
                showError('لطفاً خدمت را انتخاب کنید.');
                return;
            }
            if (!providerField || !providerField.value) {
                showError('لطفاً سرویس‌دهنده را انتخاب کنید.');
                return;
            }
            goToStep(2);
        });
    }

    // --- Step 2 navigation ---
    const back2Btn = document.getElementById('nby-back-2');
    const next2Btn = document.getElementById('nby-next-2');

    if (back2Btn) {
        back2Btn.addEventListener('click', function () { goToStep(1); });
    }

    if (next2Btn) {
        next2Btn.addEventListener('click', function () {
            if (!dateHidden || !dateHidden.value) {
                showError('لطفاً تاریخ را انتخاب کنید.');
                return;
            }
            if (!slotHidden || !slotHidden.value) {
                showError('لطفاً بازه زمانی را انتخاب کنید.');
                return;
            }
            goToStep(3);
        });
    }

    // --- Step 3 back ---
    const back3Btn = document.getElementById('nby-back-3');
    if (back3Btn) {
        back3Btn.addEventListener('click', function () { goToStep(2); });
    }

    // --- Promo result helper ---
    function setPromoResult(el, text, isOk) {
        if (!el) { return; }
        el.textContent = text || '';
        el.className   = 'nby-promo-result' + (text ? (isOk ? ' is-ok' : ' is-error') : '');
    }

    // --- Coupon apply ---
    if (couponApplyBtn && couponCodeField) {
        couponApplyBtn.addEventListener('click', function () {
            const code      = couponCodeField.value.trim();
            const serviceId = serviceField ? serviceField.value : '';
            appliedCouponCode = '';

            if (!code) {
                setPromoResult(couponResultEl, 'کد تخفیف را وارد کنید.', false);
                return;
            }
            if (!serviceId) {
                setPromoResult(couponResultEl, 'ابتدا خدمت را انتخاب کنید.', false);
                return;
            }

            setPromoResult(couponResultEl, 'در حال بررسی...', true);

            const url = nobatyarBooking.restUrl + 'coupons/validate'
                + '?code='       + encodeURIComponent(code)
                + '&service_id=' + encodeURIComponent(serviceId);

            apiFetch(url)
                .then(function (result) {
                    if (!result.ok) {
                        setPromoResult(couponResultEl, result.data.message || 'کد تخفیف معتبر نیست.', false);
                        return;
                    }
                    appliedCouponCode = code;
                    const disc = result.data.discount_type === 'percent'
                        ? result.data.discount_value + '%'
                        : result.data.discount_value;
                    setPromoResult(couponResultEl, 'کد تخفیف اعمال شد (' + disc + ').', true);
                })
                .catch(function () {
                    setPromoResult(couponResultEl, 'خطا در بررسی کد تخفیف.', false);
                });
        });
    }

    // --- Gift card apply ---
    if (giftCardApplyBtn && giftCardCodeField) {
        giftCardApplyBtn.addEventListener('click', function () {
            const code = giftCardCodeField.value.trim();
            appliedGiftCardCode = '';

            if (!code) {
                setPromoResult(giftCardResultEl, 'کد کارت هدیه را وارد کنید.', false);
                return;
            }

            setPromoResult(giftCardResultEl, 'در حال بررسی...', true);

            const url = nobatyarBooking.restUrl + 'gift-cards/validate?code=' + encodeURIComponent(code);

            apiFetch(url)
                .then(function (result) {
                    if (!result.ok) {
                        setPromoResult(giftCardResultEl, result.data.message || 'کد کارت هدیه معتبر نیست.', false);
                        return;
                    }
                    appliedGiftCardCode = code;
                    setPromoResult(giftCardResultEl, 'کارت هدیه اعمال شد (موجودی: ' + result.data.remaining_balance + ').', true);
                })
                .catch(function () {
                    setPromoResult(giftCardResultEl, 'خطا در بررسی کارت هدیه.', false);
                });
        });
    }

    // --- Package toggle ---
    if (usePackageField && packageFieldsBox) {
        usePackageField.addEventListener('change', function () {
            packageFieldsBox.hidden = !usePackageField.checked;

            if (serviceField) { serviceField.disabled = usePackageField.checked; }

            if (recurrenceEnableField) {
                recurrenceEnableField.disabled = usePackageField.checked;
                if (usePackageField.checked) {
                    recurrenceEnableField.checked = false;
                    if (recurrenceFieldsBox) { recurrenceFieldsBox.hidden = true; }
                }
            }
        });
    }

    // --- Package lookup ---
    function resetPackageOptions(placeholder) {
        if (!packagePurchaseField) { return; }
        packagePurchaseField.innerHTML = '';
        const opt     = document.createElement('option');
        opt.value     = '';
        opt.textContent = placeholder;
        packagePurchaseField.appendChild(opt);
    }

    if (packageLookupBtn && packagePurchaseField) {
        packageLookupBtn.addEventListener('click', function () {
            const phone = customerPhoneField ? customerPhoneField.value.trim() : '';

            if (!phone) {
                showError('برای بررسی اعتبار پکیج ابتدا شماره موبایل را وارد کنید.');
                return;
            }

            hideError();
            resetPackageOptions('در حال بررسی...');

            const url = nobatyarBooking.restUrl + 'packages/purchases/lookup?phone=' + encodeURIComponent(phone);

            apiFetch(url)
                .then(function (result) {
                    packagePurchases = result.data && result.data.purchases ? result.data.purchases : [];

                    if (!packagePurchases.length) {
                        resetPackageOptions('پکیج فعالی برای این شماره یافت نشد');
                        return;
                    }

                    resetPackageOptions('انتخاب کنید');
                    packagePurchases.forEach(function (purchase) {
                        const opt       = document.createElement('option');
                        opt.value       = purchase.id;
                        opt.textContent = purchase.package_name
                            + ' (' + purchase.sessions_remaining
                            + ' از ' + purchase.sessions_total + ' باقی‌مانده)';
                        packagePurchaseField.appendChild(opt);
                    });
                })
                .catch(function () {
                    resetPackageOptions('خطا در بررسی اعتبار پکیج');
                });
        });
    }

    if (packagePurchaseField) {
        packagePurchaseField.addEventListener('change', function () {
            const selected = packagePurchases.filter(function (p) {
                return String(p.id) === packagePurchaseField.value;
            })[0];

            if (selected && serviceField) {
                serviceField.value = selected.service_id;
                debouncedLoadSlots();
            }
        });
    }

    // --- Recurring toggle ---
    if (recurrenceEnableField && recurrenceFieldsBox) {
        recurrenceEnableField.addEventListener('change', function () {
            recurrenceFieldsBox.hidden = !recurrenceEnableField.checked;
        });
    }

    // --- Form reset helper ---
    function resetForm() {
        form.reset();
        if (slotHidden)  { slotHidden.value = ''; }
        setSlotHint('ابتدا تاریخ را انتخاب کنید');
        if (serviceField) { serviceField.disabled = false; }
        appliedCouponCode   = '';
        appliedGiftCardCode = '';
        setPromoResult(couponResultEl,   '', true);
        setPromoResult(giftCardResultEl, '', true);
        packagePurchases = [];
        resetPackageOptions('ابتدا شماره موبایل را بررسی کنید');
        if (packageFieldsBox)    { packageFieldsBox.hidden    = true; }
        if (recurrenceFieldsBox) { recurrenceFieldsBox.hidden = true; }
        if (recurrenceEnableField) { recurrenceEnableField.disabled = false; }
    }

    // --- Form submit ---
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        hideError();

        // Step 3 validation
        if (customerNameField && !customerNameField.value.trim()) {
            showError('لطفاً نام مشتری را وارد کنید.');
            return;
        }
        if (customerPhoneField && !customerPhoneField.value.trim()) {
            showError('لطفاً شماره موبایل را وارد کنید.');
            return;
        }

        const isUsingPackage = !!(usePackageField && usePackageField.checked);
        const isRecurring    = !isUsingPackage && !!(recurrenceEnableField && recurrenceEnableField.checked);
        let endpoint;
        let payload;

        if (isUsingPackage) {
            endpoint = 'bookings/package-redeem';
            payload  = {
                package_purchase_id: packagePurchaseField ? packagePurchaseField.value           : '',
                provider_id:         providerField        ? providerField.value                  : '',
                booking_datetime:    slotHidden           ? slotHidden.value                     : '',
                customer_name:       customerNameField    ? customerNameField.value.trim()       : '',
                customer_phone:      customerPhoneField   ? customerPhoneField.value.trim()      : '',
                customer_email:      customerEmailField   ? customerEmailField.value.trim()      : '',
            };
        } else {
            endpoint = 'bookings';
            payload  = {
                provider_id:      providerField      ? providerField.value             : '',
                service_id:       serviceField       ? serviceField.value              : '',
                booking_datetime: slotHidden         ? slotHidden.value                : '',
                customer_name:    customerNameField  ? customerNameField.value.trim()  : '',
                customer_phone:   customerPhoneField ? customerPhoneField.value.trim() : '',
                customer_email:   customerEmailField ? customerEmailField.value.trim() : '',
            };

            if (appliedCouponCode)   { payload.coupon_code    = appliedCouponCode; }
            if (appliedGiftCardCode) { payload.gift_card_code = appliedGiftCardCode; }

            if (isRecurring) {
                endpoint                      = 'bookings/recurring';
                payload.recurrence_frequency   = recurrenceFreqField ? recurrenceFreqField.value : '';
                payload.recurrence_occurrences = recurrenceOccField  ? recurrenceOccField.value  : '';
            }
        }

        if (submitBtn) {
            submitBtn.disabled    = true;
            submitBtn.textContent = 'در حال ثبت...';
        }

        apiFetch(nobatyarBooking.restUrl + endpoint, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload),
        })
            .then(function (result) {
                if (submitBtn) {
                    submitBtn.disabled    = false;
                    submitBtn.textContent = 'ثبت نوبت';
                }

                if (!result.ok) {
                    showError(result.data.message || 'ثبت نوبت با خطا مواجه شد.');
                    return;
                }

                const message = isRecurring && result.data.ids
                    ? 'سری نوبت‌های تکرارشونده (' + result.data.ids.length + ' نوبت) با موفقیت ثبت شد.'
                    : 'نوبت شما با موفقیت ثبت شد.';

                resetForm();
                showSuccess(message);
            })
            .catch(function () {
                if (submitBtn) {
                    submitBtn.disabled    = false;
                    submitBtn.textContent = 'ثبت نوبت';
                }
                showError('ثبت نوبت با خطا مواجه شد.');
            });
    });

    // --- Book another ---
    const bookAnotherBtn = document.getElementById('nby-book-another');
    if (bookAnotherBtn) {
        bookAnotherBtn.addEventListener('click', function () { goToStep(1); });
    }

})();
