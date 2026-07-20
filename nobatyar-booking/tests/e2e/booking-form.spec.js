// @ts-check
/**
 * Nobatyar booking-form wizard — end-to-end tests.
 *
 * Runs entirely in-browser with page.setContent() so no live WordPress
 * installation is needed. Fetch calls are intercepted via page.route()
 * to return canned responses.
 */

const { test, expect } = require('@playwright/test');
const fs               = require('fs');
const path             = require('path');

// ---------- helpers ----------

const ROOT = path.join(__dirname, '..', '..');

function readFile(rel) {
    return fs.readFileSync(path.join(ROOT, rel), 'utf8');
}

/** Minimal form HTML that mirrors what booking-form.php renders for a full feature set. */
const FORM_HTML = `
<div class="nby-wrap">
  <nav class="nby-steps" aria-label="مراحل رزرو">
    <div class="nby-step is-active" data-step="1" aria-current="step">
      <span class="nby-step__bubble">۱</span>
      <span class="nby-step__label">خدمت</span>
    </div>
    <span class="nby-step__connector" aria-hidden="true"></span>
    <div class="nby-step" data-step="2">
      <span class="nby-step__bubble">۲</span>
      <span class="nby-step__label">تاریخ</span>
    </div>
    <span class="nby-step__connector" aria-hidden="true"></span>
    <div class="nby-step" data-step="3">
      <span class="nby-step__bubble">۳</span>
      <span class="nby-step__label">اطلاعات</span>
    </div>
  </nav>

  <div id="nby-error" class="nby-error-bar" role="alert" hidden></div>

  <form id="nby-form" class="nby-form" novalidate>

    <div class="nby-panel" data-panel="1">
      <div class="nby-field">
        <label class="nby-label" for="nby-service">خدمت</label>
        <select id="nby-service" name="service_id" class="nby-select">
          <option value="">انتخاب کنید</option>
          <option value="1">برش مو</option>
          <option value="2">رنگ مو</option>
        </select>
      </div>
      <div class="nby-field">
        <label class="nby-label" for="nby-provider">سرویس‌دهنده</label>
        <select id="nby-provider" name="provider_id" class="nby-select">
          <option value="">انتخاب کنید</option>
          <option value="10">آرایشگر اول</option>
        </select>
      </div>
      <div class="nby-actions">
        <button type="button" id="nby-next-1" class="nby-btn nby-btn--primary nby-btn--block">مرحله بعد</button>
      </div>
    </div>

    <div class="nby-panel" data-panel="2" hidden>
      <div class="nby-field nobatyar-jalali-field">
        <label class="nby-label" for="nobatyar-date-display">تاریخ</label>
        <input type="text" id="nobatyar-date-display" class="nby-input nobatyar-jalali-display"
               readonly autocomplete="off" placeholder="انتخاب تاریخ" />
        <input type="hidden" id="nobatyar-date" name="date" />
      </div>
      <div class="nby-field">
        <label class="nby-label">بازه زمانی</label>
        <div id="nby-slots" class="nby-slots" role="group" aria-label="انتخاب بازه زمانی">
          <p class="nby-slots__hint">ابتدا تاریخ را انتخاب کنید</p>
        </div>
        <input type="hidden" id="nby-slot" name="booking_datetime" />
      </div>
      <div class="nby-actions nby-actions--split">
        <button type="button" id="nby-back-2" class="nby-btn nby-btn--ghost">بازگشت</button>
        <button type="button" id="nby-next-2" class="nby-btn nby-btn--primary">مرحله بعد</button>
      </div>
    </div>

    <div class="nby-panel" data-panel="3" hidden>
      <div class="nby-field">
        <label class="nby-label" for="nby-customer-name">مشتری</label>
        <input type="text" id="nby-customer-name" name="customer_name" class="nby-input" autocomplete="name" />
      </div>
      <div class="nby-field">
        <label class="nby-label" for="nby-customer-phone">شماره موبایل</label>
        <input type="tel"  id="nby-customer-phone" name="customer_phone" class="nby-input" dir="ltr" />
      </div>
      <div class="nby-field">
        <label class="nby-label" for="nby-customer-email">ایمیل (اختیاری)</label>
        <input type="email" id="nby-customer-email" name="customer_email" class="nby-input" dir="ltr" />
      </div>
      <div class="nby-actions nby-actions--split">
        <button type="button" id="nby-back-3" class="nby-btn nby-btn--ghost">بازگشت</button>
        <button type="submit" id="nby-submit" class="nby-btn nby-btn--success">ثبت نوبت</button>
      </div>
    </div>

    <div class="nby-panel nby-panel--success" data-panel="success" hidden>
      <div class="nby-success">
        <div class="nby-success__icon" aria-hidden="true">
          <svg viewBox="0 0 52 52" fill="none" width="64" height="64">
            <circle cx="26" cy="26" r="24" stroke="currentColor" stroke-width="2.5"/>
            <path d="M14 26.5l8 8 16-16" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <p id="nby-success-message" class="nby-success__message"></p>
        <button type="button" id="nby-book-another" class="nby-btn nby-btn--ghost">رزرو نوبت دیگر</button>
      </div>
    </div>

  </form>
</div>
`;

/** Bootstrap page with CSS + JS + mock globals, then inject HTML. */
async function mountForm(page, fetchHandler) {
    const css = readFile('assets/css/booking-form.css');
    const js  = readFile('assets/js/booking-form.js');

    // Intercept REST API calls
    if (fetchHandler) {
        await page.route('**/nobatyar/v1/**', fetchHandler);
    }

    await page.setContent(`<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <style>${css}</style>
</head>
<body>
  ${FORM_HTML}
  <script>
    // Mock the WordPress-localised object booking-form.js expects
    window.nobatyarBooking = {
      restUrl: 'https://example.test/wp-json/nobatyar/v1/',
      nonce:   'test-nonce-123',
    };
  </script>
  <script>${js}</script>
</body>
</html>`);
}

// ---------- tests ----------

test.describe('Booking Form Wizard', () => {

    test('Panel 1 is visible on load; panels 2 & 3 are hidden', async ({ page }) => {
        await mountForm(page, null);

        await expect(page.locator('[data-panel="1"]')).toBeVisible();
        await expect(page.locator('[data-panel="2"]')).toBeHidden();
        await expect(page.locator('[data-panel="3"]')).toBeHidden();
        await expect(page.locator('[data-panel="success"]')).toBeHidden();
    });

    test('Step 1: shows error when service not selected', async ({ page }) => {
        await mountForm(page, null);

        await page.click('#nby-next-1');
        await expect(page.locator('#nby-error')).toBeVisible();
        await expect(page.locator('#nby-error')).toContainText('خدمت');
        await expect(page.locator('[data-panel="1"]')).toBeVisible();
    });

    test('Step 1: shows error when provider not selected after service', async ({ page }) => {
        await mountForm(page, null);

        await page.selectOption('#nby-service', '1');
        await page.click('#nby-next-1');
        await expect(page.locator('#nby-error')).toBeVisible();
        await expect(page.locator('#nby-error')).toContainText('سرویس‌دهنده');
        await expect(page.locator('[data-panel="1"]')).toBeVisible();
    });

    test('Step 1 → Step 2: advances when both fields selected', async ({ page }) => {
        await mountForm(page, null);

        await page.selectOption('#nby-service', '1');
        await page.selectOption('#nby-provider', '10');
        await page.click('#nby-next-1');

        await expect(page.locator('[data-panel="1"]')).toBeHidden();
        await expect(page.locator('[data-panel="2"]')).toBeVisible();
        await expect(page.locator('#nby-error')).toBeHidden();

        // Step 1 bubble should be "done", step 2 "active"
        await expect(page.locator('.nby-step[data-step="1"]')).toHaveClass(/is-done/);
        await expect(page.locator('.nby-step[data-step="2"]')).toHaveClass(/is-active/);
    });

    test('Step 2 → Step 1: back button works', async ({ page }) => {
        await mountForm(page, null);

        await page.selectOption('#nby-service', '1');
        await page.selectOption('#nby-provider', '10');
        await page.click('#nby-next-1');
        await page.click('#nby-back-2');

        await expect(page.locator('[data-panel="1"]')).toBeVisible();
        await expect(page.locator('[data-panel="2"]')).toBeHidden();
    });

    test('Step 2: shows error when date not selected', async ({ page }) => {
        await mountForm(page, null);

        await page.selectOption('#nby-service', '1');
        await page.selectOption('#nby-provider', '10');
        await page.click('#nby-next-1');
        await page.click('#nby-next-2');

        await expect(page.locator('#nby-error')).toBeVisible();
        await expect(page.locator('#nby-error')).toContainText('تاریخ');
    });

    test('Step 2: shows error when slot not selected after date', async ({ page }) => {
        await mountForm(page, async (route) => {
            // Return one slot for availability call
            await route.fulfill({
                status:      200,
                contentType: 'application/json',
                body:        JSON.stringify({ slots: [{ start: '2025-09-01 10:00:00' }] }),
            });
        });

        await mountForm(page, async (route) => {
            await route.fulfill({
                status:      200,
                contentType: 'application/json',
                body:        JSON.stringify({ slots: [{ start: '2025-09-01 10:00:00' }] }),
            });
        });

        await page.selectOption('#nby-service', '1');
        await page.selectOption('#nby-provider', '10');
        await page.click('#nby-next-1');

        // Set date programmatically (simulates Jalali picker)
        await page.evaluate(() => {
            const hidden = document.getElementById('nobatyar-date');
            hidden.value = '2025-09-01';
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
        });

        // Wait for slots to render, then try to advance without picking one
        await page.waitForTimeout(300);
        await page.click('#nby-next-2');

        await expect(page.locator('#nby-error')).toBeVisible();
        await expect(page.locator('#nby-error')).toContainText('بازه زمانی');
    });

    test('Slots render as chips when availability returns slots', async ({ page }) => {
        await page.route('**/nobatyar/v1/**', async (route) => {
            await route.fulfill({
                status:      200,
                contentType: 'application/json',
                body:        JSON.stringify({
                    slots: [
                        { start: '2025-09-01 09:00:00' },
                        { start: '2025-09-01 10:30:00', capacity_remaining: 2 },
                    ],
                }),
            });
        });

        await mountForm(page, null);   // route already set above

        await page.selectOption('#nby-service', '1');
        await page.selectOption('#nby-provider', '10');
        await page.click('#nby-next-1');

        await page.evaluate(() => {
            const hidden = document.getElementById('nobatyar-date');
            hidden.value = '2025-09-01';
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
        });

        await page.waitForSelector('.nby-slot-chip');
        const chips = page.locator('.nby-slot-chip');
        await expect(chips).toHaveCount(2);
        await expect(chips.first()).toContainText('09:00');
        await expect(chips.nth(1)).toContainText('10:30');
        await expect(chips.nth(1)).toContainText('2 جا');
    });

    test('Selecting a slot chip marks it selected and sets hidden input', async ({ page }) => {
        await page.route('**/nobatyar/v1/**', async (route) => {
            await route.fulfill({
                status:      200,
                contentType: 'application/json',
                body:        JSON.stringify({ slots: [{ start: '2025-09-01 09:00:00' }] }),
            });
        });

        await mountForm(page, null);

        await page.selectOption('#nby-service', '1');
        await page.selectOption('#nby-provider', '10');
        await page.click('#nby-next-1');

        await page.evaluate(() => {
            const hidden = document.getElementById('nobatyar-date');
            hidden.value = '2025-09-01';
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
        });

        await page.waitForSelector('.nby-slot-chip');
        await page.locator('.nby-slot-chip').first().click();

        await expect(page.locator('.nby-slot-chip.is-selected')).toHaveCount(1);

        const slotValue = await page.locator('#nby-slot').inputValue();
        expect(slotValue).toBe('2025-09-01 09:00:00');
    });

    test('Step 2 → Step 3 with date + slot selected', async ({ page }) => {
        await page.route('**/nobatyar/v1/**', async (route) => {
            await route.fulfill({
                status:      200,
                contentType: 'application/json',
                body:        JSON.stringify({ slots: [{ start: '2025-09-01 09:00:00' }] }),
            });
        });

        await mountForm(page, null);

        await page.selectOption('#nby-service', '1');
        await page.selectOption('#nby-provider', '10');
        await page.click('#nby-next-1');

        await page.evaluate(() => {
            const hidden = document.getElementById('nobatyar-date');
            hidden.value = '2025-09-01';
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
        });

        await page.waitForSelector('.nby-slot-chip');
        await page.locator('.nby-slot-chip').first().click();
        await page.click('#nby-next-2');

        await expect(page.locator('[data-panel="3"]')).toBeVisible();
        await expect(page.locator('[data-panel="2"]')).toBeHidden();
    });

    test('Step 3: shows error when name missing on submit', async ({ page }) => {
        await page.route('**/nobatyar/v1/**', async (route) => {
            await route.fulfill({
                status:      200,
                contentType: 'application/json',
                body:        JSON.stringify({ slots: [{ start: '2025-09-01 09:00:00' }] }),
            });
        });

        await mountForm(page, null);

        // Navigate to step 3
        await page.selectOption('#nby-service', '1');
        await page.selectOption('#nby-provider', '10');
        await page.click('#nby-next-1');
        await page.evaluate(() => {
            const h = document.getElementById('nobatyar-date');
            h.value = '2025-09-01';
            h.dispatchEvent(new Event('change', { bubbles: true }));
        });
        await page.waitForSelector('.nby-slot-chip');
        await page.locator('.nby-slot-chip').first().click();
        await page.click('#nby-next-2');

        // Submit without name
        await page.click('#nby-submit');

        await expect(page.locator('#nby-error')).toBeVisible();
        await expect(page.locator('#nby-error')).toContainText('نام');
    });

    test('Step 3 → Step 2: back button works', async ({ page }) => {
        await page.route('**/nobatyar/v1/**', async (route) => {
            await route.fulfill({
                status:      200,
                contentType: 'application/json',
                body:        JSON.stringify({ slots: [{ start: '2025-09-01 09:00:00' }] }),
            });
        });

        await mountForm(page, null);

        await page.selectOption('#nby-service', '1');
        await page.selectOption('#nby-provider', '10');
        await page.click('#nby-next-1');
        await page.evaluate(() => {
            const h = document.getElementById('nobatyar-date');
            h.value = '2025-09-01';
            h.dispatchEvent(new Event('change', { bubbles: true }));
        });
        await page.waitForSelector('.nby-slot-chip');
        await page.locator('.nby-slot-chip').first().click();
        await page.click('#nby-next-2');
        await page.click('#nby-back-3');

        await expect(page.locator('[data-panel="2"]')).toBeVisible();
        await expect(page.locator('[data-panel="3"]')).toBeHidden();
    });

    test('Full happy-path: shows success panel after successful POST', async ({ page }) => {
        let requestBody = null;

        await page.route('**/nobatyar/v1/**', async (route) => {
            const url = route.request().url();

            if (url.includes('availability')) {
                await route.fulfill({
                    status:      200,
                    contentType: 'application/json',
                    body:        JSON.stringify({ slots: [{ start: '2025-09-01 09:00:00' }] }),
                });
                return;
            }

            if (url.includes('/bookings') && route.request().method() === 'POST') {
                requestBody = JSON.parse(route.request().postData() || '{}');
                await route.fulfill({
                    status:      201,
                    contentType: 'application/json',
                    body:        JSON.stringify({ id: 42, status: 'pending' }),
                });
                return;
            }

            await route.continue();
        });

        await mountForm(page, null);

        // Step 1
        await page.selectOption('#nby-service', '1');
        await page.selectOption('#nby-provider', '10');
        await page.click('#nby-next-1');

        // Step 2
        await page.evaluate(() => {
            const h = document.getElementById('nobatyar-date');
            h.value = '2025-09-01';
            h.dispatchEvent(new Event('change', { bubbles: true }));
        });
        await page.waitForSelector('.nby-slot-chip');
        await page.locator('.nby-slot-chip').first().click();
        await page.click('#nby-next-2');

        // Step 3
        await page.fill('#nby-customer-name', 'علی محمدی');
        await page.fill('#nby-customer-phone', '09123456789');
        await page.click('#nby-submit');

        await expect(page.locator('[data-panel="success"]')).toBeVisible();
        await expect(page.locator('#nby-success-message')).toContainText('موفقیت');

        // Verify POST body
        expect(requestBody).toMatchObject({
            provider_id:      '10',
            service_id:       '1',
            booking_datetime: '2025-09-01 09:00:00',
            customer_name:    'علی محمدی',
            customer_phone:   '09123456789',
        });
    });

    test('Server error on POST shows error bar (not success)', async ({ page }) => {
        await page.route('**/nobatyar/v1/**', async (route) => {
            const url = route.request().url();

            if (url.includes('availability')) {
                await route.fulfill({
                    status:      200,
                    contentType: 'application/json',
                    body:        JSON.stringify({ slots: [{ start: '2025-09-01 09:00:00' }] }),
                });
                return;
            }

            await route.fulfill({
                status:      409,
                contentType: 'application/json',
                body:        JSON.stringify({ code: 'nobatyar_booking_conflict', message: 'این بازه قبلاً رزرو شده است.' }),
            });
        });

        await mountForm(page, null);

        await page.selectOption('#nby-service', '1');
        await page.selectOption('#nby-provider', '10');
        await page.click('#nby-next-1');
        await page.evaluate(() => {
            const h = document.getElementById('nobatyar-date');
            h.value = '2025-09-01';
            h.dispatchEvent(new Event('change', { bubbles: true }));
        });
        await page.waitForSelector('.nby-slot-chip');
        await page.locator('.nby-slot-chip').first().click();
        await page.click('#nby-next-2');
        await page.fill('#nby-customer-name', 'علی محمدی');
        await page.fill('#nby-customer-phone', '09123456789');
        await page.click('#nby-submit');

        await expect(page.locator('#nby-error')).toBeVisible();
        await expect(page.locator('#nby-error')).toContainText('رزرو شده');
        await expect(page.locator('[data-panel="success"]')).toBeHidden();
    });

    test('"رزرو نوبت دیگر" resets form back to step 1', async ({ page }) => {
        await page.route('**/nobatyar/v1/**', async (route) => {
            const url = route.request().url();
            if (url.includes('availability')) {
                await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ slots: [{ start: '2025-09-01 09:00:00' }] }) });
            } else {
                await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ id: 1, status: 'pending' }) });
            }
        });

        await mountForm(page, null);

        await page.selectOption('#nby-service', '1');
        await page.selectOption('#nby-provider', '10');
        await page.click('#nby-next-1');
        await page.evaluate(() => {
            const h = document.getElementById('nobatyar-date');
            h.value = '2025-09-01';
            h.dispatchEvent(new Event('change', { bubbles: true }));
        });
        await page.waitForSelector('.nby-slot-chip');
        await page.locator('.nby-slot-chip').first().click();
        await page.click('#nby-next-2');
        await page.fill('#nby-customer-name', 'علی محمدی');
        await page.fill('#nby-customer-phone', '09123456789');
        await page.click('#nby-submit');

        await expect(page.locator('[data-panel="success"]')).toBeVisible();

        await page.click('#nby-book-another');

        await expect(page.locator('[data-panel="1"]')).toBeVisible();
        await expect(page.locator('[data-panel="success"]')).toBeHidden();
    });

});
