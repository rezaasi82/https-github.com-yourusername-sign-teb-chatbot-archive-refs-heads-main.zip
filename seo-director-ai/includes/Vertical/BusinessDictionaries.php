<?php
/**
 * Business (non-medical) entity dictionaries. These power the "Business &
 * services" site vertical — agencies, salons, law firms, gyms, etc. Unlike
 * the clinical dictionaries these describe services, offerings, and audiences
 * rather than diseases/procedures, so they feed entity detection and the
 * knowledge graph (which services have we covered?) and never produce clinical
 * schema. Sites can extend any preset via the sda_business_dictionary filter.
 *
 * @package SEODirector
 */

namespace SEODirector\Vertical;

defined( 'ABSPATH' ) || exit;

final class BusinessDictionaries {

	/** Preset slug => human label (fa). */
	public const PRESETS = [
		'agency'  => 'طراحی سایت، برندینگ و سئو',
		'salon'   => 'سالن زیبایی و آرایشگاه',
		'legal'   => 'وکیل و مشاور حقوقی',
		'fitness' => 'باشگاه و تناسب اندام',
	];

	/** Category slug => human label (fa) — used by the UI for grouping. */
	public const CATEGORY_LABEL = [
		'service'  => 'خدمت',
		'seo_term' => 'اصطلاح سئو',
		'branding' => 'برندینگ',
		'platform' => 'پلتفرم',
		'audience' => 'مخاطب',
		'concept'  => 'مفهوم',
	];

	/** @return array<string, string[]> */
	public static function agency(): array {
		return [
			'service'  => [
				'طراحی سایت', 'طراحی سایت پزشکی', 'طراحی سایت پزشکان', 'طراحی سایت کلینیک', 'سئو',
				'سئو سایت', 'سئو پزشکی', 'برندینگ', 'برندینگ پزشکی', 'طراحی لوگو', 'لندینگ پیج',
				'صفحه فرود', 'تولید محتوا', 'محتوانویسی', 'نوبت‌دهی آنلاین', 'اپلیکیشن',
				'دیجیتال مارکتینگ', 'بازاریابی', 'تبلیغات', 'مشاوره سئو', 'پشتیبانی سایت',
			],
			'seo_term' => [
				'کلمات کلیدی', 'بک‌لینک', 'رتبه گوگل', 'سرچ کنسول', 'گوگل آنالیتیکس', 'ترافیک ارگانیک',
				'نرخ تبدیل', 'ایندکس', 'ریسپانسیو', 'سرعت سایت', 'کور وب وایتالز', 'اسکیما',
				'سئو داخلی', 'سئو خارجی', 'سئو تکنیکال', 'سئو محلی',
			],
			'branding' => [
				'برند شخصی', 'هویت بصری', 'هویت برند', 'لوگو', 'رنگ سازمانی', 'شعار برند',
				'اعتمادسازی', 'استوری برند',
			],
			'platform' => [ 'وردپرس', 'المنتور', 'ووکامرس', 'قالب', 'افزونه', 'هاست', 'دامنه', 'CMS' ],
			'audience' => [ 'پزشک', 'دندانپزشک', 'کلینیک', 'مطب', 'کسب‌وکار', 'برند', 'فروشگاه' ],
		];
	}

	/** @return array<string, string[]> */
	public static function salon(): array {
		return [
			'service'  => [
				'کوتاهی مو', 'رنگ مو', 'مش', 'هایلایت', 'کراتینه', 'احیای مو', 'بوتاکس مو',
				'کاشت مو', 'اکستنشن مو', 'شینیون', 'میکاپ', 'گریم', 'میکروبلیدینگ', 'میکروپیگمنتیشن',
				'ناخن', 'کاشت ناخن', 'مانیکور', 'پدیکور', 'لمینت ناخن', 'اکستنشن مژه', 'لیفت مژه',
				'اپیلاسیون', 'وکس', 'پاکسازی پوست', 'میکرودرم', 'فیشیال', 'ماساژ', 'لاغری موضعی',
				'براشینگ', 'دکلره', 'آمبره', 'بالیاژ',
			],
			'audience' => [ 'عروس', 'بانوان', 'آقایان', 'خانم‌ها' ],
			'concept'  => [ 'سالن زیبایی', 'آرایشگاه', 'آرایشگاه زنانه', 'آرایشگاه مردانه', 'سالن آرایش', 'اسپا' ],
		];
	}

	/** @return array<string, string[]> */
	public static function legal(): array {
		return [
			'service'  => [
				'وکالت', 'مشاوره حقوقی', 'دعاوی', 'دعاوی حقوقی', 'دعاوی کیفری', 'دعاوی ملکی',
				'دعاوی خانواده', 'طلاق', 'طلاق توافقی', 'مهریه', 'نفقه', 'حضانت', 'ارث', 'انحصار وراثت',
				'قرارداد', 'تنظیم قرارداد', 'مهاجرت', 'ویزا', 'ثبت شرکت', 'ثبت برند', 'ثبت علامت تجاری',
				'چک', 'مطالبات', 'دیه', 'کلاهبرداری', 'ملکی', 'اجاره', 'سرقفلی', 'داوری',
			],
			'audience' => [ 'موکل', 'شرکت', 'کارفرما', 'مستأجر', 'مالک' ],
			'concept'  => [ 'وکیل', 'وکیل پایه یک', 'وکیل دادگستری', 'مشاور حقوقی', 'دفتر وکالت', 'دادگاه', 'شورای حل اختلاف' ],
		];
	}

	/** @return array<string, string[]> */
	public static function fitness(): array {
		return [
			'service'  => [
				'بدنسازی', 'فیتنس', 'کراسفیت', 'تی‌آر‌ایکس', 'TRX', 'پیلاتس', 'یوگا', 'ایروبیک',
				'زومبا', 'کاهش وزن', 'چربی‌سوزی', 'عضله‌سازی', 'حجم', 'کات', 'فانکشنال',
				'برنامه تمرین', 'برنامه بدنسازی', 'مربی خصوصی', 'مربی بدنسازی', 'رژیم ورزشی',
				'بدنسازی بانوان', 'تناسب اندام', 'اصلاح حرکات', 'فیزیوتراپی ورزشی',
			],
			'audience' => [ 'بانوان', 'آقایان', 'مبتدی', 'حرفه‌ای', 'ورزشکار' ],
			'concept'  => [ 'باشگاه', 'باشگاه بدنسازی', 'سالن ورزشی', 'باشگاه ورزشی', 'مجموعه ورزشی' ],
		];
	}

	/**
	 * Resolve a preset slug to its dictionary (standalone; business verticals
	 * do not share a common base).
	 *
	 * @return array<string, string[]>
	 */
	public static function for_preset( string $preset ): array {
		$dictionary = match ( $preset ) {
			'salon'   => self::salon(),
			'legal'   => self::legal(),
			'fitness' => self::fitness(),
			default   => self::agency(),
		};

		/**
		 * Filters the business entity dictionary.
		 *
		 * @param array<string, string[]> $dictionary category => terms.
		 * @param string                  $preset     Active business preset slug.
		 */
		return (array) apply_filters( 'sda_business_dictionary', $dictionary, $preset );
	}
}
