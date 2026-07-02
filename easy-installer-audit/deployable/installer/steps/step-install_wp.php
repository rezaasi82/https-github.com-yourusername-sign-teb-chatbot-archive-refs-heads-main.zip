<div class="ezi-card">
	<h1>نصب هسته وردپرس</h1>
	<p class="ezi-muted">در حال دانلود و آماده‌سازی وردپرس. این مرحله ممکن است تا ۲ دقیقه طول بکشد.</p>

	<div class="ezi-progress-wrap">
		<div class="ezi-progress-bar"><div class="ezi-progress-fill" id="ezi-wp-progress"></div></div>
		<div class="ezi-progress-log" id="ezi-wp-log"></div>
	</div>

	<div id="ezi-wp-next" style="display:none;">
		<a href="?step=site_info" class="ezi-btn ezi-btn--primary">ادامه ←</a>
	</div>

	<div id="ezi-wp-error" style="display:none;">
		<div class="ezi-notice ezi-notice--info" style="margin-top:0.5rem;">
			اگر این خطا تکرار شد، احتمالاً سرور میزبانی شما دسترسی مستقیم به
			اینترنت برای دانلود ندارد. در این صورت می‌توانید وردپرس را
			دستی از <strong>fa.wordpress.org</strong> دانلود کرده و
			محتوای پوشه آن را در روت سایت (کنار همین install.php) آپلود
			کنید، سپس روی «ادامه» در زیر کلیک کنید.
		</div>
		<div class="ezi-btn-row">
			<a href="?step=database" class="ezi-btn ezi-btn--ghost">بازگشت</a>
			<button onclick="location.reload()" class="ezi-btn ezi-btn--primary">تلاش مجدد</button>
		</div>
		<a href="?step=site_info" class="ezi-btn ezi-btn--ghost" style="margin-top:0.625rem;">
			وردپرس را دستی آپلود کردم — ادامه بده
		</a>
	</div>
</div>

<script>
const wpLog = document.getElementById('ezi-wp-log');
const wpBar = document.getElementById('ezi-wp-progress');

function addLog(text, status) {
	const item = document.createElement('div');
	item.className = 'ezi-log-item is-' + status;
	const icon = status === 'done' ? '✅' : status === 'error' ? '❌' : '<span class="ezi-spinner"></span>';
	item.innerHTML = icon + ' ' + text;
	wpLog.appendChild(item);
	return item;
}

// fetch با timeout — اگر سرور تا 110 ثانیه پاسخ ندهد، درخواست لغو و خطای
// روشن نمایش داده می‌شود (به‌جای گیر کردن ابدی روی "در حال دانلود...")
function fetchWithTimeout(url, ms) {
	const controller = new AbortController();
	const timer = setTimeout(() => controller.abort(), ms);
	return fetch(url, { signal: controller.signal })
		.finally(() => clearTimeout(timer));
}

(async function () {
	const step1 = addLog('در حال دانلود فایل‌های وردپرس...', 'active');
	wpBar.style.width = '20%';

	try {
		const res  = await fetchWithTimeout('?action=download_wp', 110000);
		const data = await res.json();

		if (!data.success) {
			step1.className = 'ezi-log-item is-error';
			step1.innerHTML = '❌ ' + data.message;
			document.getElementById('ezi-wp-error').style.display = 'block';
			return;
		}

		step1.className = 'ezi-log-item is-done';
		step1.innerHTML = '✅ وردپرس دانلود و استخراج شد.';
		wpBar.style.width = '55%';

		const step2 = addLog('در حال راه‌اندازی پایه وردپرس...', 'active');

		// مرحله بعد (نصب واقعی) در صفحه اطلاعات سایت انجام می‌شود
		// چون نیاز به ورودی از کاربر (نام کاربری/رمز) دارد
		await new Promise(r => setTimeout(r, 600));

		step2.className = 'ezi-log-item is-done';
		step2.innerHTML = '✅ آماده برای دریافت اطلاعات سایت.';
		wpBar.style.width = '100%';

		document.getElementById('ezi-wp-next').style.display = 'block';

	} catch (e) {
		step1.className = 'ezi-log-item is-error';
		if (e.name === 'AbortError') {
			step1.innerHTML = '❌ سرور بیش از حد انتظار طول کشید (Timeout). ممکن است سرور به اینترنت دسترسی نداشته باشد.';
		} else {
			step1.innerHTML = '❌ خطای شبکه در ارتباط با سرور.';
		}
		document.getElementById('ezi-wp-error').style.display = 'block';
	}
})();
</script>
