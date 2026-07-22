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
		<div class="ezi-notice ezi-notice--warning">
			<strong>راهنمای رفع مشکل — به ترتیب امتحان کنید:</strong><br>
			۱) چند لحظه صبر کنید و دوباره «نصب وردپرس» را بزنید — اگر نصب در
			پس‌زمینه کامل شده باشد، نصب‌کننده خودش تشخیص می‌دهد و رد نمی‌شود.<br>
			۲) وضعیت دقیق را با
			<a href="diagnose.php" target="_blank"><code>diagnose.php</code></a>
			بررسی کنید (نشان می‌دهد wp-config.php ساخته شده یا نه، و روت قابل
			نوشتن هست یا نه).<br>
			۳) اگر روت قابل نوشتن نبود، دسترسی پوشه را به <code>0755</code>
			(یا <code>0775</code>) تغییر دهید.<br>
			۴) اگر کد HTTP خطا 403/406 بود، فایروال هاست (ModSecurity) درخواست
			را مسدود می‌کند — از پشتیبانی هاست بخواهید آن را برای دامنه‌تان
			موقتاً غیرفعال کند.
		</div>
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

	// مهلت ۱۵۰ ثانیه: نصب روی دیتابیس‌های کند هاست اشتراکی می‌تواند طول بکشد؛
	// قطع زودهنگام درخواست باعث کشته شدن اسکریپت سمت سرور وسط نصب می‌شود.
	fetchWithTimeout('?action=install_wp', { method: 'POST', body: formData }, 150000)
		.then(async function (res) {
			const rawText = await res.text();
			let data;
			try {
				data = JSON.parse(rawText);
			} catch (parseErr) {
				console.error('EZI install_wp non-JSON response (HTTP ' + res.status + '):', rawText.slice(0, 500));
				// پاسخ سرور JSON معتبر نبود. نصب‌کننده به‌گونه‌ای طراحی شده که
				// همیشه JSON معتبر برمی‌گرداند (چه موفق چه ناموفق)، پس پاسخ
				// غیر-JSON یعنی چیزی واقعاً اشتباه است — تقریباً همیشه یعنی روت
				// قابل نوشتن نیست و wp-config.php ساخته نشده. این را صادقانه
				// نشان بده و اجازه‌ی ادامه‌ی اشتباه (که به setup-config.php ختم
				// می‌شد) را نده.
				errorBox.innerHTML = `
					<div class="ezi-notice ezi-notice--error">
						نصب کامل نشد: سرور پاسخ نامعتبری برگرداند
						(کد HTTP: ${Number(res.status)}). راهنمای رفع مشکل در
						کادر زیر آمده است؛ جزئیات فنی پاسخ در Console مرورگر
						(F12) ثبت شد.
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
						سرور بیش از حد انتظار طول کشید. ممکن است نصب هنوز در حال
						انجام باشد؛ چند لحظه صبر کنید و دوباره «نصب وردپرس» را بزنید.
						اگر تکرار شد، <code>/wp-admin/</code> را در تب جدید بررسی کنید.
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
