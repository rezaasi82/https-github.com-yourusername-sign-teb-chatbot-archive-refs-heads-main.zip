<div class="ezi-card">
	<h1>بررسی سرور</h1>
	<p class="ezi-muted">در حال بررسی آماده بودن میزبانی شما برای نصب...</p>

	<div id="ezi-req-checklist" class="ezi-checklist">
		<div class="ezi-log-item is-active"><span class="ezi-spinner"></span> در حال بررسی...</div>
	</div>

	<div id="ezi-req-actions" style="display:none;">
		<div class="ezi-btn-row">
			<a href="?step=welcome" class="ezi-btn ezi-btn--ghost">بازگشت</a>
			<a href="?step=database" id="ezi-req-continue" class="ezi-btn ezi-btn--primary">ادامه ←</a>
		</div>
	</div>
</div>

<script>
fetch('?action=check_requirements')
	.then(r => r.json())
	.then(data => {
		const list = document.getElementById('ezi-req-checklist');
		list.innerHTML = '';

		data.checks.forEach(function (check) {
			const item = document.createElement('div');
			item.className = 'ezi-check-item' + (check.pass ? '' : ' is-fail');
			item.innerHTML = `
				<span class="ezi-check-icon">${check.pass ? '✅' : (check.fatal ? '❌' : '⚠️')}</span>
				<div class="ezi-check-text">
					<div class="ezi-check-label">${check.label}</div>
					<div class="ezi-check-detail">${check.detail}</div>
				</div>
				<div class="ezi-check-value">${check.value}</div>
			`;
			list.appendChild(item);
		});

		document.getElementById('ezi-req-actions').style.display = 'block';

		if (!data.all_pass) {
			const notice = document.createElement('div');
			notice.className = 'ezi-notice ezi-notice--error';
			notice.style.marginTop = '1rem';
			notice.textContent = 'برخی پیش‌نیازهای ضروری برقرار نیستند. لطفاً با شرکت میزبانی خود تماس بگیرید.';
			list.after(notice);
			document.getElementById('ezi-req-continue').classList.add('ezi-btn--ghost');
		}
	})
	.catch(() => {
		document.getElementById('ezi-req-checklist').innerHTML =
			'<div class="ezi-notice ezi-notice--error">خطا در بررسی سرور. صفحه را رفرش کنید.</div>';
	});
</script>
