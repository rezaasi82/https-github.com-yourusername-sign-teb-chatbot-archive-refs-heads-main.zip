<?php
$admin_user = $_SESSION['ezi_admin_user'] ?? '';
$site_url   = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https://' : 'http://' )
	. ezi_safe_host();
?>
<div class="ezi-card" style="text-align:center;">
	<div class="ezi-hero-icon">🎉</div>
	<h1>سایت شما آماده است!</h1>
	<p class="ezi-muted">
		نصب با موفقیت به پایان رسید. اکنون می‌توانید از سایت خود
		دیدن کنید یا وارد پیشخوان مدیریت شوید.
	</p>

	<div class="ezi-finish-grid" style="text-align:right;">
		<div class="ezi-finish-item">
			<span class="ezi-finish-item__icon">🌐</span>
			<div class="ezi-finish-item__text"><strong>وردپرس</strong><span>نصب و راه‌اندازی شد</span></div>
		</div>
		<div class="ezi-finish-item">
			<span class="ezi-finish-item__icon">🎨</span>
			<div class="ezi-finish-item__text"><strong>قالب اصلی</strong><span>نصب و فعال شد</span></div>
		</div>
		<div class="ezi-finish-item">
			<span class="ezi-finish-item__icon">🧩</span>
			<div class="ezi-finish-item__text"><strong>افزونه‌های همراه</strong><span>نصب و فعال شدند</span></div>
		</div>
	</div>

	<div class="ezi-btn-row" style="margin-top:1.5rem;">
		<a href="<?php echo esc_attr_ezi( $site_url ); ?>/wp-admin/" class="ezi-btn ezi-btn--primary">ورود به پیشخوان</a>
		<a href="<?php echo esc_attr_ezi( $site_url ); ?>/" class="ezi-btn ezi-btn--ghost">مشاهده سایت</a>
	</div>

	<div class="ezi-notice ezi-notice--warning" style="margin-top:1.5rem;text-align:right;">
		⚠️ <strong>مرحله امنیتی مهم:</strong><br>
		برای جلوگیری از دسترسی مجدد به این نصب‌کننده، فایل
		<code>install.php</code> و پوشه <code>installer/</code> را
		از طریق File Manager یا FTP از سرور خود حذف کنید.
	</div>

	<div class="ezi-notice ezi-notice--info" style="margin-top:0.75rem;text-align:right;">
		📧 <strong>توجه مهم درباره ارسال ایمیل:</strong><br>
		سرور میزبانی شما تابع پایه‌ای PHP برای ارسال ایمیل را غیرفعال
		کرده است (محدودیت رایج در بسیاری از هاست‌های اشتراکی). این
		موضوع روی نصب اولیه تأثیری نداشت، اما باعث می‌شود ایمیل‌های
		آینده‌ی سایت — مثل بازیابی رمز عبور یا اعلان نوبت‌های پزشکی —
		ارسال نشوند، مگر اینکه یک افزونه‌ی SMTP (مثل
		<strong>WP Mail SMTP</strong>) نصب و با اطلاعات یک سرویس ایمیل
		واقعی (مثلاً Gmail یا سرویس ایمیل شرکتی) تنظیم کنید.
	</div>
</div>
