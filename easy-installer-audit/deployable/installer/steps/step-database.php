<div class="ezi-card">
	<h1>اتصال به دیتابیس</h1>
	<p class="ezi-muted">
		این اطلاعات را از پنل میزبانی (cPanel) خود دریافت می‌کنید —
		معمولاً در بخش «MySQL Databases» قابل ساخت و مشاهده است.
	</p>

	<div id="ezi-db-error"></div>

	<form id="ezi-db-form" onsubmit="return false;">
		<div class="ezi-field">
			<label>نام دیتابیس</label>
			<input type="text" name="db_name" placeholder="مثال: username_wp" required>
		</div>

		<div class="ezi-field-row">
			<div class="ezi-field">
				<label>نام کاربری دیتابیس</label>
				<input type="text" name="db_user" placeholder="مثال: username_wp" required>
			</div>
			<div class="ezi-field">
				<label>رمز عبور دیتابیس</label>
				<input type="password" name="db_pass" placeholder="••••••••">
			</div>
		</div>

		<div class="ezi-field-row">
			<div class="ezi-field">
				<label>میزبان دیتابیس (Host)</label>
				<input type="text" name="db_host" value="localhost">
				<small>در اکثر هاست‌های اشتراکی همین مقدار درست است</small>
			</div>
			<div class="ezi-field">
				<label>پیشوند جداول</label>
				<input type="text" name="db_prefix" value="wp_">
			</div>
		</div>

		<div class="ezi-btn-row">
			<a href="?step=requirements" class="ezi-btn ezi-btn--ghost">بازگشت</a>
			<button type="submit" id="ezi-db-submit" class="ezi-btn ezi-btn--primary">
				تست اتصال و ادامه
			</button>
		</div>
	</form>
</div>

<script>
document.getElementById('ezi-db-form').addEventListener('submit', function () {
	const btn      = document.getElementById('ezi-db-submit');
	const errorBox = document.getElementById('ezi-db-error');
	errorBox.innerHTML = '';
	btn.disabled = true;
	btn.innerHTML = '<span class="ezi-spinner"></span> در حال تست اتصال...';

	const formData = new FormData(document.getElementById('ezi-db-form'));

	fetch('?action=test_database', { method: 'POST', body: formData })
		.then(r => r.json())
		.then(data => {
			if (data.success) {
				window.location.href = '?step=install_wp';
			} else {
				errorBox.innerHTML = `<div class="ezi-notice ezi-notice--error">${data.message}</div>`;
				btn.disabled = false;
				btn.textContent = 'تست اتصال و ادامه';
			}
		})
		.catch(() => {
			errorBox.innerHTML = '<div class="ezi-notice ezi-notice--error">خطای شبکه. مجدداً تلاش کنید.</div>';
			btn.disabled = false;
			btn.textContent = 'تست اتصال و ادامه';
		});
});
</script>
