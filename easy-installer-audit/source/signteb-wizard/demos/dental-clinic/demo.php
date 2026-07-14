<?php
/**
 * دموی مرجع ۲ — کلینیک دندان‌پزشکی (Dental Clinic)
 *
 * @package SignTeb_Wizard
 */

defined( 'ABSPATH' ) || exit;

$home = <<<'HTML'
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|8","bottom":"var:preset|spacing|8"}}},"backgroundColor":"trust-blue-light","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull has-trust-blue-light-background-color has-background" style="padding-top:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--8)">
<!-- wp:heading {"textAlign":"center","level":1} -->
<h1 class="wp-block-heading has-text-align-center">لبخندی که آرزویش را داشتید</h1>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">کلینیک دندان‌پزشکی لبخند سفید با جدیدترین تکنولوژی‌های دندان‌پزشکی زیبایی و درمانی، در کنار شماست.</p>
<!-- /wp:paragraph -->
<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/appointment">مشاوره‌ی رایگان</a></div><!-- /wp:button --></div>
<!-- /wp:buttons -->
</section>
<!-- /wp:group -->

<!-- wp:signteb/stats-counter {"columns":4,"stats":[{"value":12,"suffix":"+","label":"سال تجربه"},{"value":8000,"suffix":"+","label":"لبخند بازسازی‌شده"},{"value":6,"suffix":"","label":"دندان‌پزشک متخصص"},{"value":99,"suffix":"%","label":"رضایت مراجعان"}]} /-->

<!-- wp:heading {"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">خدمات دندان‌پزشکی</h2><!-- /wp:heading -->
<!-- wp:signteb/service-grid {"columns":3,"showPrice":true} /-->

<!-- wp:heading {"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">نمونه کارها (قبل و بعد)</h2><!-- /wp:heading -->
<!-- wp:signteb/before-after-slider {"beforeImageUrl":"%%IMG_BEFORE%%","afterImageUrl":"%%IMG_AFTER%%","initialPosition":50} /-->

<!-- wp:heading {"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">تیم ما</h2><!-- /wp:heading -->
<!-- wp:signteb/doctor-card-grid {"columns":3} /-->

<!-- wp:signteb/faq-accordion {"title":"سؤالات متداول دندان‌پزشکی"} /-->

<!-- wp:signteb/testimonials-slider {"title":"نظر مراجعان"} /-->

<!-- wp:signteb/appointment-cta {"title":"وقت مشاوره‌ی رایگان بگیرید"} /-->

<!-- wp:signteb/contact-cta {"title":"آدرس و تماس"} /-->
HTML;

return [
	'id'          => 'dental-clinic',
	'order'       => 2,
	'title'       => 'دندان‌پزشکی',
	'icon'        => '🦷',
	'color'       => '#059669',
	'description' => 'کلینیک دندان‌پزشکی زیبایی و درمانی — گالری قبل/بعد، خدمات، تیم، سؤالات متداول، نظرات، بلاگ و نوبت‌دهی.',
	'pages_count' => '۷ صفحه',
	'lang'        => 'فارسی',

	'options' => [
		'blogname'           => 'دندان‌پزشکی لبخند سفید',
		'blogdescription'    => 'دندان‌پزشکی زیبایی و درمانی',
		'stmc_clinic_name'   => 'کلینیک لبخند سفید',
		'stmc_clinic_phone'  => '02188008000',
		'stmc_clinic_whatsapp' => '989121111111',
		'stmc_clinic_email'  => 'info@labkhand.example',
		'stmc_clinic_address'=> 'تهران، سعادت‌آباد، بلوار دریا، پلاک ۴۵',
		'stmc_market'        => 'ir',
		'stmc_geo_placename' => 'تهران',
		'stmc_geo_region'    => 'IR-16',
		'stmc_country_code'  => 'IR',
	],

	'doctors' => [
		[ 'name' => 'دکتر نیما تهرانی', 'specialty' => 'دندان‌پزشک زیبایی', 'exp' => 13, 'patients' => 6000, 'bio' => 'متخصص طراحی لبخند و لمینیت.', 'content' => '<p>دکتر نیما تهرانی در زمینه‌ی طراحی لبخند، لمینیت و کامپوزیت تخصص دارد.</p>' ],
		[ 'name' => 'دکتر شیما قاسمی', 'specialty' => 'متخصص ارتودنسی', 'exp' => 15, 'patients' => 4500, 'bio' => 'متخصص ارتودنسی ثابت و متحرک.', 'content' => '<p>دکتر شیما قاسمی خدمات ارتودنسی برای کودکان و بزرگسالان را ارائه می‌دهد.</p>' ],
		[ 'name' => 'دکتر بابک نوری', 'specialty' => 'متخصص ایمپلنت', 'exp' => 17, 'patients' => 3800, 'bio' => 'جراح ایمپلنت و درمان ریشه.', 'content' => '<p>دکتر بابک نوری در کاشت ایمپلنت و جراحی‌های دهان و فک فعالیت می‌کند.</p>' ],
	],

	'services' => [
		[ 'title' => 'طراحی لبخند', 'icon' => '😁', 'duration' => '۹۰ دقیقه', 'price' => 'از ۵,۰۰۰,۰۰۰ تومان', 'excerpt' => 'طراحی دیجیتال لبخند متناسب با چهره‌ی شما.', 'specialty' => 'دندان‌پزشک زیبایی' ],
		[ 'title' => 'لمینیت و کامپوزیت', 'icon' => '✨', 'duration' => '۱۲۰ دقیقه', 'price' => 'از ۳,۵۰۰,۰۰۰ تومان', 'excerpt' => 'زیبایی و اصلاح فرم دندان‌ها.', 'specialty' => 'دندان‌پزشک زیبایی' ],
		[ 'title' => 'ارتودنسی', 'icon' => '🦷', 'duration' => 'دوره‌ای', 'price' => 'از ۲۵,۰۰۰,۰۰۰ تومان', 'excerpt' => 'مرتب‌سازی دندان‌ها با براکت ثابت یا نامرئی.', 'specialty' => 'متخصص ارتودنسی' ],
		[ 'title' => 'ایمپلنت دندان', 'icon' => '🔩', 'duration' => 'دوره‌ای', 'price' => 'از ۱۵,۰۰۰,۰۰۰ تومان', 'excerpt' => 'جایگزینی دندان از دست رفته با ایمپلنت.', 'specialty' => 'متخصص ایمپلنت' ],
		[ 'title' => 'بلیچینگ (سفید کردن)', 'icon' => '🌟', 'duration' => '۶۰ دقیقه', 'price' => 'از ۲,۰۰۰,۰۰۰ تومان', 'excerpt' => 'سفید کردن حرفه‌ای دندان‌ها در مطب.', 'specialty' => 'دندان‌پزشک زیبایی' ],
		[ 'title' => 'درمان ریشه (عصب‌کشی)', 'icon' => '🦷', 'duration' => '۹۰ دقیقه', 'price' => 'از ۲,۵۰۰,۰۰۰ تومان', 'excerpt' => 'درمان ریشه‌ی دندان بدون درد.', 'specialty' => 'متخصص ایمپلنت' ],
	],

	'faqs' => [
		[ 'q' => 'آیا مشاوره‌ی اولیه رایگان است؟', 'a' => '<p>بله، اولین جلسه‌ی مشاوره و معاینه برای طراحی برنامه‌ی درمان رایگان است.</p>' ],
		[ 'q' => 'درمان‌ها با بی‌حسی انجام می‌شوند؟', 'a' => '<p>تمام درمان‌های تهاجمی با بی‌حسی کامل و بدون درد انجام می‌شوند.</p>' ],
		[ 'q' => 'امکان پرداخت اقساطی هست؟', 'a' => '<p>بله، برای درمان‌های بزرگ مانند ارتودنسی و ایمپلنت امکان پرداخت اقساطی فراهم است.</p>' ],
		[ 'q' => 'لمینیت چند سال دوام دارد؟', 'a' => '<p>با رعایت بهداشت مناسب، لمینیت‌ها معمولاً بین ۱۰ تا ۱۵ سال دوام دارند.</p>' ],
		[ 'q' => 'ارتودنسی چقدر طول می‌کشد؟', 'a' => '<p>مدت درمان ارتودنسی بسته به شرایط بین ۱۲ تا ۲۴ ماه متغیر است.</p>' ],
		[ 'q' => 'آیا خدمات اورژانسی دارید؟', 'a' => '<p>بله، برای دردهای اورژانسی دندان امکان مراجعه‌ی فوری با هماهنگی تلفنی وجود دارد.</p>' ],
	],

	'reviews' => [
		[ 'doctor' => 0, 'name' => 'مینا رستمی', 'city' => 'تهران', 'rating' => 5, 'text' => 'طراحی لبخندم فوق‌العاده طبیعی شد. واقعاً ممنونم.', 'treatment' => 'طراحی لبخند' ],
		[ 'doctor' => 1, 'name' => 'امیر جعفری', 'city' => 'تهران', 'rating' => 5, 'text' => 'ارتودنسی نامرئی گرفتم و نتیجه عالی بود.', 'treatment' => 'ارتودنسی' ],
		[ 'doctor' => 2, 'name' => 'فاطمه یوسفی', 'city' => 'کرج', 'rating' => 5, 'text' => 'ایمپلنت بدون درد و با پیگیری خوب انجام شد.', 'treatment' => 'ایمپلنت' ],
	],

	'posts' => [
		[ 'title' => 'چگونه از لمینیت مراقبت کنیم؟', 'excerpt' => 'نکات نگهداری برای دوام بیشتر لمینیت.', 'content' => '<p>با رعایت چند نکته‌ی ساده می‌توانید عمر لمینیت‌های خود را افزایش دهید.</p>' ],
		[ 'title' => 'ایمپلنت بهتر است یا بریج؟', 'excerpt' => 'مقایسه‌ی دو روش جایگزینی دندان.', 'content' => '<p>در این مقاله مزایا و معایب ایمپلنت و بریج را با هم مقایسه می‌کنیم.</p>' ],
		[ 'title' => 'راهنمای سفید کردن دندان', 'excerpt' => 'روش‌های ایمن بلیچینگ.', 'content' => '<p>سفید کردن دندان روش‌های مختلفی دارد؛ در این مطلب ایمن‌ترین‌ها را بررسی می‌کنیم.</p>' ],
	],

	'pages' => [
		[ 'title' => 'خانه', 'slug' => 'home', 'front' => true, 'content' => $home ],
		[ 'title' => 'درباره ما', 'slug' => 'about', 'content' => '<!-- wp:heading --><h2 class="wp-block-heading">درباره کلینیک لبخند سفید</h2><!-- /wp:heading --><!-- wp:paragraph --><p>کلینیک دندان‌پزشکی لبخند سفید با تجهیزات روز و تیمی از متخصصان، خدمات زیبایی و درمانی دندان را با بالاترین استاندارد ارائه می‌دهد.</p><!-- /wp:paragraph -->' ],
		[ 'title' => 'خدمات', 'slug' => 'services', 'content' => '<!-- wp:signteb/service-grid {"columns":3,"showPrice":true} /-->' ],
		[ 'title' => 'تیم ما', 'slug' => 'doctors', 'content' => '<!-- wp:signteb/doctor-card-grid {"columns":3} /-->' ],
		[ 'title' => 'سؤالات متداول', 'slug' => 'faq', 'content' => '<!-- wp:signteb/faq-accordion {"title":"سؤالات متداول","allowMultiple":true} /-->' ],
		[ 'title' => 'تماس با ما', 'slug' => 'contact', 'content' => '<!-- wp:signteb/contact-cta {"title":"آدرس و تماس"} /-->' ],
		[ 'title' => 'رزرو نوبت', 'slug' => 'appointment', 'template' => 'page-landing', 'content' => '<!-- wp:signteb/appointment-cta {"title":"رزرو مشاوره‌ی رایگان"} /-->' ],
	],

	'menu' => [
		'home'        => 'خانه',
		'services'    => 'خدمات',
		'doctors'     => 'تیم ما',
		'about'       => 'درباره ما',
		'faq'         => 'سؤالات متداول',
		'contact'     => 'تماس با ما',
		'appointment' => 'رزرو نوبت',
	],

	'front_page' => 'home',
	'blog_page'  => 'blog',
	'blog_title' => 'مقالات دندان‌پزشکی',
];
