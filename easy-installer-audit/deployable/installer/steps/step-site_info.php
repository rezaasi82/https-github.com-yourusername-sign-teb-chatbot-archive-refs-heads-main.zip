<div class="ezi-card">
	<h1>اطلاعات سایت و مدیر</h1>
	<p class="ezi-muted">این اطلاعات برای ورود به پیشخوان مدیریت سایت استفاده می‌شود.</p>

	<div id="ezi-site-error"></div>

	<form id="ezi-site-form" onsubmit="return false;">
		<div class="ezi-field">
			<label>نام سایت</label>
			<input type="text" name="site_title" placeholder="مثال: کلینیک دکتر احمدی" required>
		</div>

		<div class="ezi-field">
			<label>نام کاربری مدیر</label>
			<input type="text" name="admin_user" placeholder="نام کاربری ورود به پیشخوان" required autocomplete="off">
			<small>پیشنهاد می‌شود از «admin» استفاده نکنید — امنیت بالاتر</small>
		</div>

		<div class="ezi-field">
			<label>رمز عبور مدیر</label>
			<div class="ezi-input-with-btn">
				<input type="text" name="admin_pass" id="ezi-admin-pass" placeholder="رمز عبور قوی" required autocomplete="off">
				<button type="button" id="ezi-gen-pass">تولید رمز</button>
			</div>
			<small>این رمز را یادداشت کنید — پس از نصب نمایش داده نخواهد شد</small>
		</div>

		<div class="ezi-field">
			<label>ایمیل مدیر</label>
			<input type="email" name="admin_email" placeholder="email@example.com" required>
		</div>

		<div class="ezi-btn-row">
			<a href="?step=install_wp" class="ezi-btn ezi-btn--ghost">بازگشت</a>
			<button type="submit" id="ezi-site-submit" class="ezi-btn ezi-btn--primary">نصب وردپرس</button>
		</div>
	</form>

	<div id="ezi-site-manual" style="display:none;margin-top:1rem;">
		<div class="ezi-notice ezi-notice--info">
			اگر مطمئن هستید نصب در پس‌زمینه انجام شده (مثلاً با ورود به
			<code>/wp-admin/</code> در تب جدید تأیید کرده‌اید)، می‌توانید
			به‌صورت دستی ادامه دهید.
		</div>
		<a href="?step=install_package" class="ezi-btn ezi-btn--ghost">نصب انجام شد — ادامه بده</a>
	</div>
</div>

<script>
document.getElementById('ezi-gen-pass').addEventListener('click', function () {
	const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#%';
	let pass = '';
	for (let i = 0; i < 14; i++) pass += chars[Math.floor(Math.random() * chars.length)];
	document.getElementById('ezi-admin-pass').value = pass;
});

function fetchWithTimeout(url, options, ms) {
	const controller = new AbortController();
	const timer = setTimeout(() => controller.abort(), ms);
	return fetch(url, { ...options, signal: controller.signal })
		.finally(() => clearTimeout(timer));
}

document.getElementById('ezi-site-form').addEventListener('submit', function () {
	const btn      = document.getElementById('ezi-site-submit');
	const errorBox = document.getElementById('ezi-site-error');
	const manualBox = document.getElementById('ezi-site-manual');
	errorBox.innerHTML = '';
	manualBox.style.display = 'none';
	btn.disabled = true;
	btn.innerHTML = '<span class="ezi-spinner"></span> در حال نصب وردپرس...';

	const formData = new FormData(document.getElementById('ezi-site-form'));

	fetchWithTimeout('?action=install_wp', { method: 'POST', body: formData }, 60000)
		.then(async function (res) {
			const rawText = await res.text();
			let data;
			try {
				data = JSON.parse(rawText);
			} catch (parseErr) {
				// پاسخ سرور JSON معتبر نبود — اما این به معنای شکست نصب نیست؛
				// معمولاً یعنی نصب در پس‌زمینه انجام شده ولی خروجی غیرمنتظره‌ای
				// (مثل یک هشدار PHP) قبل از JSON چاپ شده است.
				errorBox.innerHTML = `
					<div class="ezi-notice ezi-notice--warning">
						پاسخ سرور نامعتبر بود، اما نصب احتمالاً با موفقیت انجام شده
						است. لطفاً با باز کردن <code>/wp-admin/</code> در یک تب
						جدید بررسی کنید. در صورت تأیید، از دکمه زیر ادامه دهید.
					</div>`;
				manualBox.style.display = 'block';
				btn.disabled = false;
				btn.textContent = 'نصب وردپرس';
				return;
			}

			if (data.success) {
				window.location.href = '?step=install_package';
			} else {
				errorBox.innerHTML = `<div class="ezi-notice ezi-notice--error">${data.message}</div>`;
				btn.disabled = false;
				btn.textContent = 'نصب وردپرس';
			}
		})
		.catch(function (e) {
			if (e.name === 'AbortError') {
				errorBox.innerHTML = `
					<div class="ezi-notice ezi-notice--warning">
						سرور بیش از حد انتظار طول کشید، اما نصب ممکن است در
						پس‌زمینه کامل شده باشد. <code>/wp-admin/</code> را در
						تب جدید بررسی کنید.
					</div>`;
				manualBox.style.display = 'block';
			} else {
				errorBox.innerHTML = '<div class="ezi-notice ezi-notice--error">خطای شبکه. مجدداً تلاش کنید.</div>';
			}
			btn.disabled = false;
			btn.textContent = 'نصب وردپرس';
		});
});
</script>
