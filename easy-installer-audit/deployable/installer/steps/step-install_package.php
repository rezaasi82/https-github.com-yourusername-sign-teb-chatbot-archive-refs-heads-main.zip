<div class="ezi-card">
	<h1>نصب قالب و ابزارها</h1>
	<p class="ezi-muted">در حال نصب و فعال‌سازی قالب و افزونه‌های همراه سایت...</p>

	<div class="ezi-progress-wrap">
		<div class="ezi-progress-bar"><div class="ezi-progress-fill" id="ezi-pkg-progress"></div></div>
		<div class="ezi-progress-log" id="ezi-pkg-log"></div>
	</div>

	<div id="ezi-pkg-next" style="display:none;">
		<a href="?step=finish" class="ezi-btn ezi-btn--primary">ادامه ←</a>
	</div>
</div>

<script>
const pkgLog = document.getElementById('ezi-pkg-log');
const pkgBar = document.getElementById('ezi-pkg-progress');

function pkgAddLog(text, status) {
	const item = document.createElement('div');
	item.className = 'ezi-log-item is-' + status;
	const icon = status === 'done' ? '✅' : status === 'error' ? '❌' : status === 'skip' ? '⏭️' : '<span class="ezi-spinner"></span>';
	item.innerHTML = icon + ' ' + text;
	pkgLog.appendChild(item);
	return item;
}

async function runStep(label, action, progressTo) {
	const item = pkgAddLog(label, 'active');
	try {
		const res  = await fetch('?action=' + action);
		const data = await res.json();
		if (data.success) {
			item.className = 'ezi-log-item is-done';
			item.innerHTML = '✅ ' + data.message;
		} else {
			item.className = 'ezi-log-item is-error';
			item.innerHTML = '❌ ' + data.message;
		}
		pkgBar.style.width = progressTo + '%';
		return data.success;
	} catch (e) {
		item.className = 'ezi-log-item is-error';
		item.innerHTML = '❌ خطای شبکه';
		return false;
	}
}

(async function () {
	const ok1 = await runStep('در حال استخراج فایل‌های قالب و افزونه...', 'extract_package', 35);
	if (!ok1) return;

	await runStep('در حال فعال‌سازی قالب...', 'activate_theme', 65);
	await runStep('در حال فعال‌سازی افزونه‌ها...', 'activate_plugins', 90);
	await runStep('در حال پاکسازی نهایی...', 'cleanup', 100);

	document.getElementById('ezi-pkg-next').style.display = 'block';
})();
</script>
