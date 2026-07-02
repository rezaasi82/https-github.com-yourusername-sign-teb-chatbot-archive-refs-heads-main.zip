<?php
/**
 * رندر صفحات نصب‌کننده
 */

declare( strict_types=1 );

defined( 'EZI_ROOT' ) || exit;

const EZI_STEP_LABELS = [
	'welcome'         => 'خوش‌آمدگویی',
	'requirements'    => 'بررسی سرور',
	'database'        => 'دیتابیس',
	'install_wp'      => 'نصب وردپرس',
	'site_info'       => 'اطلاعات سایت',
	'install_package' => 'نصب قالب',
	'finish'          => 'پایان',
];

function ezi_render_page( string $current_step ): void {
	?>
	<!DOCTYPE html>
	<html lang="fa" dir="rtl">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex, nofollow">
		<title>نصب‌کننده آسان — راه‌اندازی سایت</title>
		<style><?php include EZI_DIR . '/assets/style.css'; ?></style>
	</head>
	<body class="ezi-body">

	<div class="ezi-wrap">

		<header class="ezi-header">
			<div class="ezi-header__logo">⚙️</div>
			<div>
				<div class="ezi-header__title">نصب‌کننده آسان</div>
				<div class="ezi-header__sub">راه‌اندازی خودکار سایت در چند دقیقه</div>
			</div>
		</header>

		<nav class="ezi-steps">
			<?php
			$step_keys  = array_keys( EZI_STEP_LABELS );
			$cur_index  = array_search( $current_step, $step_keys, true );
			foreach ( EZI_STEP_LABELS as $key => $label ) :
				$index = array_search( $key, $step_keys, true );
				$cls   = $index === $cur_index ? 'is-active' : ( $index < $cur_index ? 'is-done' : '' );
			?>
				<span class="ezi-step-dot <?php echo esc_attr_ezi( $cls ); ?>">
					<?php echo $index < $cur_index ? '✓' : ''; ?>
					<?php echo esc_html_ezi( $label ); ?>
				</span>
			<?php endforeach; ?>
		</nav>

		<main class="ezi-main">
			<?php ezi_render_step_content( $current_step ); ?>
		</main>

		<footer class="ezi-footer">
			نصب‌کننده آسان نسخه <?php echo EZI_VERSION; ?>
		</footer>

	</div>

	<script><?php include EZI_DIR . '/assets/script.js'; ?></script>
	</body>
	</html>
	<?php
}

function ezi_render_step_content( string $step ): void {
	$file = EZI_DIR . '/steps/step-' . $step . '.php';
	if ( file_exists( $file ) ) {
		require $file;
	} else {
		echo '<div class="ezi-card"><p>مرحله یافت نشد.</p></div>';
	}
}

// ── توابع escaping سبک (بدون نیاز به وردپرس در مراحل اولیه) ──────────────────

function esc_html_ezi( string $str ): string {
	return htmlspecialchars( $str, ENT_QUOTES, 'UTF-8' );
}

function esc_attr_ezi( string $str ): string {
	return htmlspecialchars( $str, ENT_QUOTES, 'UTF-8' );
}
