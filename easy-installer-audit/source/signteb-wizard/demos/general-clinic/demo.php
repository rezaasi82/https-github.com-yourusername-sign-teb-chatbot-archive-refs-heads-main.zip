<?php
/**
 * دموی مرجع ۱ — کلینیک عمومی (General Medical Clinic)
 *
 * تعریف کامل دمو: تنظیمات، پزشکان، خدمات، سؤالات متداول، نظرات، مقالات، صفحات،
 * منو. توسط DemoContentImporter مصرف می‌شود. افزودن دموی جدید = کپی این پوشه و
 * تغییر محتوا.
 *
 * @package SignTeb_Wizard
 */

defined( 'ABSPATH' ) || exit;

$home = <<<'HTML'
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|8","bottom":"var:preset|spacing|8"}}},"backgroundColor":"trust-blue-light","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull has-trust-blue-light-background-color has-background" style="padding-top:var(--wp--preset--spacing--8);padding-bottom:var(--wp--preset--spacing--8)">
<!-- wp:heading {"textAlign":"center","level":1} -->
<h1 class="wp-block-heading has-text-align-center">سلامت شما، اولویت ما</h1>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">کلینیک سلامت پارس با کادر مجرب پزشکی و تجهیزات مدرن، خدمات درمانی باکیفیت و نوبت‌دهی آنلاین را برای شما فراهم کرده است.</p>
<!-- /wp:paragraph -->
<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/appointment">رزرو نوبت آنلاین</a></div><!-- /wp:button --></div>
<!-- /wp:buttons -->
</section>
<!-- /wp:group -->

<!-- wp:signteb/stats-counter {"columns":4,"stats":[{"value":18,"suffix":"+","label":"سال تجربه"},{"value":25000,"suffix":"+","label":"بیمار موفق"},{"value":15,"suffix":"+","label":"پزشک متخصص"},{"value":98,"suffix":"%","label":"رضایت بیماران"}]} /-->

<!-- wp:heading {"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">خدمات ما</h2><!-- /wp:heading -->
<!-- wp:signteb/service-grid {"columns":3,"showPrice":true} /-->

<!-- wp:heading {"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">پزشکان ما</h2><!-- /wp:heading -->
<!-- wp:signteb/doctor-card-grid {"columns":3} /-->

<!-- wp:signteb/faq-accordion {"title":"سؤالات متداول"} /-->

<!-- wp:signteb/testimonials-slider {"title":"نظر بیماران ما"} /-->

<!-- wp:signteb/appointment-cta {"title":"همین امروز نوبت خود را رزرو کنید"} /-->

<!-- wp:signteb/contact-cta {"title":"با ما در تماس باشید"} /-->
HTML;

return [
	'id'          => 'general-clinic',
	'order'       => 1,
	'title'       => 'کلینیک عمومی',
	'icon'        => '🏥',
	'color'       => '#1a56db',
	'description' => 'کلینیک عمومی چندپزشکی — صفحه‌ی اصلی کامل، خدمات، پروفایل پزشکان، سؤالات متداول، نظرات، بلاگ و نوبت‌دهی.',
	'pages_count' => '۷ صفحه',
	'lang'        => 'فارسی',

	'options' => [
		'blogname'           => 'کلینیک سلامت پارس',
		'blogdescription'    => 'مرکز تخصصی سلامت با کادر مجرب',
		'stmc_clinic_name'   => 'کلینیک سلامت پارس',
		'stmc_clinic_phone'  => '02191001000',
		'stmc_clinic_whatsapp' => '989120000000',
		'stmc_clinic_email'  => 'info@pars-clinic.example',
		'stmc_clinic_address'=> 'تهران، خیابان ولیعصر، پلاک ۱۲۳',
		'stmc_market'        => 'ir',
		'stmc_geo_placename' => 'تهران',
		'stmc_geo_region'    => 'IR-16',
		'stmc_country_code'  => 'IR',
	],

	'doctors' => [
		[ 'name' => 'دکتر مریم رضایی', 'specialty' => 'پزشک عمومی', 'exp' => 14, 'patients' => 12000, 'bio' => 'پزشک عمومی با تمرکز بر طب پیشگیری و مراقبت‌های اولیه.', 'content' => '<p>دکتر مریم رضایی با ۱۴ سال سابقه، خدمات ویزیت عمومی، چکاپ دوره‌ای و مشاوره‌ی سلامت را ارائه می‌دهد.</p>' ],
		[ 'name' => 'دکتر علی محمدی', 'specialty' => 'متخصص داخلی', 'exp' => 18, 'patients' => 9000, 'bio' => 'متخصص بیماری‌های داخلی و گوارش.', 'content' => '<p>دکتر علی محمدی متخصص بیماری‌های داخلی، در تشخیص و درمان بیماری‌های گوارشی و متابولیک فعالیت دارد.</p>' ],
		[ 'name' => 'دکتر سارا کریمی', 'specialty' => 'متخصص اطفال', 'exp' => 11, 'patients' => 15000, 'bio' => 'متخصص کودکان و نوزادان.', 'content' => '<p>دکتر سارا کریمی خدمات معاینه، واکسیناسیون و مشاوره‌ی رشد کودکان را ارائه می‌کند.</p>' ],
	],

	'services' => [
		[ 'title' => 'ویزیت عمومی', 'icon' => '🩺', 'duration' => '۲۰ دقیقه', 'price' => 'از ۲۵۰,۰۰۰ تومان', 'excerpt' => 'معاینه‌ی عمومی و مشاوره‌ی سلامت توسط پزشک.', 'specialty' => 'پزشک عمومی' ],
		[ 'title' => 'چکاپ کامل بدن', 'icon' => '🧪', 'duration' => '۶۰ دقیقه', 'price' => 'از ۹۰۰,۰۰۰ تومان', 'excerpt' => 'پکیج کامل آزمایش و بررسی سلامت دوره‌ای.', 'specialty' => 'متخصص داخلی' ],
		[ 'title' => 'واکسیناسیون کودکان', 'icon' => '💉', 'duration' => '۱۵ دقیقه', 'price' => 'از ۱۵۰,۰۰۰ تومان', 'excerpt' => 'اجرای برنامه‌ی واکسیناسیون طبق تقویم سلامت کودک.', 'specialty' => 'متخصص اطفال' ],
		[ 'title' => 'مشاوره‌ی تغذیه', 'icon' => '🥗', 'duration' => '۳۰ دقیقه', 'price' => 'از ۳۰۰,۰۰۰ تومان', 'excerpt' => 'برنامه‌ی غذایی شخصی‌سازی‌شده برای سلامت و کنترل وزن.', 'specialty' => 'متخصص داخلی' ],
		[ 'title' => 'نوار قلب (ECG)', 'icon' => '❤️', 'duration' => '۲۰ دقیقه', 'price' => 'از ۲۰۰,۰۰۰ تومان', 'excerpt' => 'بررسی عملکرد قلب با دستگاه نوار قلب.', 'specialty' => 'متخصص داخلی' ],
		[ 'title' => 'مشاوره‌ی رشد کودک', 'icon' => '🧒', 'duration' => '۳۰ دقیقه', 'price' => 'از ۲۵۰,۰۰۰ تومان', 'excerpt' => 'ارزیابی رشد جسمی و ذهنی کودک.', 'specialty' => 'متخصص اطفال' ],
	],

	'faqs' => [
		[ 'q' => 'چگونه می‌توانم نوبت بگیرم؟', 'a' => '<p>از طریق دکمه‌ی «رزرو نوبت آنلاین» در سایت، یا تماس تلفنی با کلینیک می‌توانید نوبت خود را ثبت کنید.</p>' ],
		[ 'q' => 'آیا ویزیت با بیمه انجام می‌شود؟', 'a' => '<p>بله، کلینیک با بیمه‌های پایه و تکمیلی اصلی طرف قرارداد است. برای اطلاع از جزئیات با پذیرش تماس بگیرید.</p>' ],
		[ 'q' => 'ساعت کاری کلینیک چیست؟', 'a' => '<p>شنبه تا پنجشنبه از ساعت ۸ صبح تا ۸ شب. جمعه‌ها تعطیل است.</p>' ],
		[ 'q' => 'آیا امکان ویزیت آنلاین وجود دارد؟', 'a' => '<p>بله، برای برخی خدمات مشاوره‌ی آنلاین از طریق واتساپ یا تماس تصویری فراهم است.</p>' ],
		[ 'q' => 'برای چکاپ باید ناشتا باشم؟', 'a' => '<p>برای پکیج چکاپ کامل، ۸ تا ۱۲ ساعت ناشتایی توصیه می‌شود. جزئیات هنگام رزرو اعلام می‌شود.</p>' ],
		[ 'q' => 'آیا پارکینگ دارید؟', 'a' => '<p>بله، پارکینگ اختصاصی برای مراجعان در دسترس است.</p>' ],
	],

	'reviews' => [
		[ 'doctor' => 0, 'name' => 'زهرا احمدی', 'city' => 'تهران', 'rating' => 5, 'text' => 'برخورد بسیار خوب و پیگیری دقیق. از نوبت‌دهی آنلاین راضی بودم.', 'treatment' => 'ویزیت عمومی' ],
		[ 'doctor' => 1, 'name' => 'محمد حسینی', 'city' => 'کرج', 'rating' => 5, 'text' => 'دکتر با حوصله همه‌چیز را توضیح دادند. چکاپ کاملی داشتم.', 'treatment' => 'چکاپ کامل' ],
		[ 'doctor' => 2, 'name' => 'نگار سلطانی', 'city' => 'تهران', 'rating' => 5, 'text' => 'برای واکسن فرزندم مراجعه کردم، محیط تمیز و کادر مهربان.', 'treatment' => 'واکسیناسیون' ],
		[ 'doctor' => 0, 'name' => 'رضا کاظمی', 'city' => 'تهران', 'rating' => 4, 'text' => 'کیفیت خدمات خوب بود و معطلی کمی داشتم.', 'treatment' => 'مشاوره تغذیه' ],
	],

	'posts' => [
		[ 'title' => '۵ نشانه‌ی هشدار که نباید نادیده بگیرید', 'excerpt' => 'برخی علائم بدن نیاز به بررسی فوری پزشکی دارند.', 'content' => '<p>در این مقاله به پنج نشانه‌ی مهم که نیازمند مراجعه به پزشک هستند می‌پردازیم؛ از خستگی مزمن تا تغییرات وزن.</p>' ],
		[ 'title' => 'راهنمای کامل چکاپ دوره‌ای', 'excerpt' => 'چکاپ منظم کلید تشخیص زودهنگام بیماری‌هاست.', 'content' => '<p>چکاپ دوره‌ای به تشخیص زودهنگام بسیاری از بیماری‌ها کمک می‌کند. در این مطلب می‌خوانید هر چند وقت یک‌بار و چه آزمایش‌هایی لازم است.</p>' ],
		[ 'title' => 'تغذیه‌ی سالم در فصل سرما', 'excerpt' => 'تقویت سیستم ایمنی با انتخاب‌های غذایی درست.', 'content' => '<p>در فصل سرما با رعایت چند اصل ساده‌ی تغذیه‌ای می‌توانید سیستم ایمنی بدن را تقویت کنید.</p>' ],
	],

	'pages' => [
		[ 'title' => 'خانه', 'slug' => 'home', 'front' => true, 'content' => $home ],
		[ 'title' => 'درباره ما', 'slug' => 'about', 'content' => '<!-- wp:heading --><h2 class="wp-block-heading">درباره کلینیک سلامت پارس</h2><!-- /wp:heading --><!-- wp:paragraph --><p>کلینیک سلامت پارس با هدف ارائه‌ی خدمات درمانی باکیفیت و در دسترس، از سال ۱۳۹۰ فعالیت خود را آغاز کرده است. تیم ما متشکل از پزشکان متخصص و کادر درمانی مجرب است.</p><!-- /wp:paragraph -->' ],
		[ 'title' => 'خدمات', 'slug' => 'services', 'content' => '<!-- wp:signteb/service-grid {"columns":3,"showPrice":true} /-->' ],
		[ 'title' => 'پزشکان', 'slug' => 'doctors', 'content' => '<!-- wp:signteb/doctor-card-grid {"columns":3,"showFilter":true} /-->' ],
		[ 'title' => 'سؤالات متداول', 'slug' => 'faq', 'content' => '<!-- wp:signteb/faq-accordion {"title":"سؤالات متداول","allowMultiple":true} /-->' ],
		[ 'title' => 'تماس با ما', 'slug' => 'contact', 'content' => '<!-- wp:signteb/contact-cta {"title":"با ما در تماس باشید"} /-->' ],
		[ 'title' => 'رزرو نوبت', 'slug' => 'appointment', 'template' => 'page-landing', 'content' => '<!-- wp:signteb/appointment-cta {"title":"رزرو نوبت آنلاین"} /-->' ],
	],

	'menu' => [
		'home'        => 'خانه',
		'services'    => 'خدمات',
		'doctors'     => 'پزشکان',
		'about'       => 'درباره ما',
		'faq'         => 'سؤالات متداول',
		'contact'     => 'تماس با ما',
		'appointment' => 'رزرو نوبت',
	],

	'front_page' => 'home',
	'blog_page'  => 'blog',
	'blog_title' => 'مقالات سلامت',
];
