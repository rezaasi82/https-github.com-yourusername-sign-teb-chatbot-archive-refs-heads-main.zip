<?php
/**
 * SignTeb Setup Wizard — مرحله‌ی راه‌اندازی خودکار
 *
 * ۸ تسک راه‌اندازی را به‌صورت خودکار و پشت‌سرهم اجرا می‌کند (بررسی محیط → نصب
 * افزونه‌ها → دمو → تنظیمات → منوها → خانه → بلاگ → پایان) با نوار پیشرفت زنده.
 */

defined( 'ABSPATH' ) || exit;

$stwiz_runner = new \SignTeb\Wizard\Setup\SetupRunner();
$stwiz_steps  = $stwiz_runner->steps();
$stwiz_labels = \SignTeb\Wizard\Setup\SetupRunner::LABELS;
$stwiz_demo   = sanitize_key( $_GET['demo'] ?? 'solo-doctor' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط پیش‌فرض نمایشی؛ اجرای واقعی با nonce در AJAX.

// آماده‌سازی لیست تسک‌ها برای JS
$stwiz_task_list = [];
foreach ( $stwiz_steps as $stwiz_key ) {
	$stwiz_task_list[] = [
		'key'   => $stwiz_key,
		'label' => $stwiz_labels[ $stwiz_key ] ?? $stwiz_key,
	];
}
?>
<div class="stwiz-install-step" data-step="install">
	<h2><?php esc_html_e( 'راه‌اندازی خودکار سایت', STWIZ_TEXT ); ?></h2>
	<p class="stwiz-step-desc">
		<?php esc_html_e( 'در حال آماده‌سازی کامل سایت شما. لطفاً این صفحه را نبندید تا همه‌ی مراحل پایان یابد.', STWIZ_TEXT ); ?>
	</p>

	<div class="stwiz-progress__bar" style="margin-bottom:1.5rem;">
		<div class="stwiz-progress__fill" id="stwiz-install-fill" style="width:0%"></div>
	</div>

	<div class="stwiz-install-log" id="stwiz-install-log" role="status" aria-live="polite"></div>

	<div id="stwiz-install-error" class="stwiz-info-box" style="display:none;margin-top:1.25rem;border-color:rgba(248,113,113,0.4);background:rgba(248,113,113,0.08);"></div>

	<div class="stwiz-main__footer" style="margin-top:2rem;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=signteb-wizard&step=demo' ) ); ?>" class="stwiz-btn stwiz-btn--ghost">← <?php esc_html_e( 'بازگشت', STWIZ_TEXT ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=signteb-wizard&step=finish' ) ); ?>" id="stwiz-install-next" class="stwiz-btn stwiz-btn--gold" style="display:none;">
			🚀 <?php esc_html_e( 'مشاهده نتیجه', STWIZ_TEXT ); ?>
		</a>
	</div>
</div>

<script>
(function () {
	var tasks   = <?php echo wp_json_encode( $stwiz_task_list ); ?>;
	var demo    = <?php echo wp_json_encode( $stwiz_demo ); ?>;
	var logEl   = document.getElementById('stwiz-install-log');
	var fillEl  = document.getElementById('stwiz-install-fill');
	var errEl   = document.getElementById('stwiz-install-error');
	var nextBtn = document.getElementById('stwiz-install-next');
	var total   = tasks.length;

	function addRow(label) {
		var row = document.createElement('div');
		row.className = 'stwiz-req-item';
		row.innerHTML = '<span class="stwiz-channel-icon">⏳</span> ' + label;
		logEl.appendChild(row);
		return row;
	}

	function runTask(index) {
		if (index >= total) {
			fillEl.style.width = '100%';
			nextBtn.style.display = '';
			return;
		}
		var task = tasks[index];
		var row  = addRow(task.label);

		fetch(stWizData.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams({
				action:     'stwiz_run_setup_step',
				nonce:      stWizData.nonce,
				setup_step: task.key,
				demo:       demo
			})
		})
		.then(function (r) { return r.json(); })
		.then(function (res) {
			var ok  = res && res.success;
			var msg = (res && res.data && res.data.message) ? res.data.message : (ok ? 'انجام شد' : 'خطا');
			row.className = 'stwiz-req-item ' + (ok ? 'ok' : 'fail');
			row.innerHTML = '<span class="stwiz-channel-icon">' + (ok ? '✅' : '❌') + '</span> ' + task.label + ' — <small>' + msg + '</small>';
			fillEl.style.width = Math.round(((index + 1) / total) * 100) + '%';

			if (!ok) {
				// توقف روی خطا (مثلاً شکست بررسی محیط) با پیام روشن.
				errEl.style.display = 'block';
				errEl.textContent = '❌ ' + msg;
				return;
			}
			runTask(index + 1);
		})
		.catch(function () {
			row.className = 'stwiz-req-item fail';
			row.innerHTML = '<span class="stwiz-channel-icon">❌</span> ' + task.label + ' — <small>خطای شبکه</small>';
			errEl.style.display = 'block';
			errEl.textContent = '❌ خطای شبکه در ارتباط با سرور. صفحه را رفرش کنید تا از همان مرحله ادامه یابد.';
		});
	}

	runTask(0);
})();
</script>
