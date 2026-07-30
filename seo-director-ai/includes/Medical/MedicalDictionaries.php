<?php
/**
 * Specialty dictionary presets for the Medical Pack. The entity engine loads
 * the "general" preset plus whichever specialty the site selected, so a
 * gastroenterology/liver clinic gets sharp detection of its own conditions
 * and procedures instead of only the broad seed list. Sites can still extend
 * any of this via the sda_medical_dictionary filter.
 *
 * @package SEODirector
 */

namespace SEODirector\Medical;

defined( 'ABSPATH' ) || exit;

final class MedicalDictionaries {

	/** Preset slug => human label (fa). */
	public const PRESETS = [
		'general'           => 'عمومی',
		'gastro_hepatology' => 'گوارش و کبد',
	];

	/**
	 * The broad, always-on base preset.
	 *
	 * @return array<string, string[]>
	 */
	public static function general(): array {
		return [
			'disease'   => [
				'فتق', 'دیابت', 'فشار خون', 'سرطان', 'کبد چرب', 'سنگ کلیه', 'سنگ صفرا', 'آپاندیس',
				'زخم معده', 'ریفلاکس', 'کولیت', 'یبوست', 'بواسیر', 'هموروئید', 'واریس', 'آرتروز',
				'میگرن', 'افسردگی', 'کم‌خونی', 'تیروئید',
			],
			'symptom'   => [
				'درد', 'تهوع', 'استفراغ', 'تب', 'سرگیجه', 'خستگی', 'ورم', 'التهاب', 'خونریزی',
				'سوزش', 'نفخ', 'اسهال', 'بی‌اشتهایی', 'کاهش وزن', 'تنگی نفس', 'سردرد', 'کمردرد',
			],
			'treatment' => [
				'جراحی', 'عمل', 'لاپاراسکوپی', 'آندوسکوپی', 'کولونوسکوپی', 'شیمی‌درمانی',
				'پرتودرمانی', 'فیزیوتراپی', 'دارودرمانی', 'رژیم غذایی', 'لیزر', 'تزریق', 'بیوپسی',
				'سونوگرافی', 'ام‌آر‌آی', 'سی‌تی اسکن',
			],
			'drug'      => [
				'آنتی‌بیوتیک', 'مسکن', 'استامینوفن', 'ایبوپروفن', 'امپرازول', 'متفورمین', 'انسولین',
				'کورتون', 'آسپرین', 'ویتامین',
			],
			'specialty' => [
				'جراح', 'فوق تخصص', 'جراح عمومی', 'متخصص داخلی', 'متخصص زنان', 'ارتوپد',
				'متخصص قلب', 'متخصص پوست', 'دندانپزشک', 'متخصص مغز و اعصاب',
			],
			'body_part' => [
				'معده', 'روده', 'کبد', 'کلیه', 'کیسه صفرا', 'مری', 'پانکراس', 'قلب', 'ریه', 'مغز',
				'ستون فقرات', 'زانو', 'شکم', 'لوزالمعده',
			],
		];
	}

	/**
	 * Gastroenterology & hepatology — deep terms for a gut/liver practice.
	 *
	 * @return array<string, string[]>
	 */
	public static function gastro_hepatology(): array {
		return [
			'disease'   => [
				'کبد چرب', 'کبد چرب غیرالکلی', 'سیروز کبدی', 'هپاتیت', 'هپاتیت بی', 'هپاتیت سی',
				'زخم معده', 'زخم اثنی‌عشر', 'ریفلاکس معده', 'ریفلاکس مری', 'گاستریت', 'ورم معده',
				'سندرم روده تحریک‌پذیر', 'کولیت اولسراتیو', 'بیماری کرون', 'سلیاک', 'پولیپ روده',
				'سرطان روده بزرگ', 'سرطان معده', 'سرطان کبد', 'سرطان پانکراس', 'سنگ کیسه صفرا',
				'یبوست مزمن', 'بواسیر', 'هموروئید', 'فیستول مقعدی', 'شقاق مقعدی', 'آشالازی',
				'واریس مری', 'خونریزی گوارشی', 'هلیکوباکتر پیلوری', 'پانکراتیت', 'کیست کبدی',
				'انسداد روده', 'دیورتیکول',
			],
			'symptom'   => [
				'سوزش سر دل', 'ترش کردن', 'نفخ شکم', 'درد شکم', 'دل درد', 'اسهال مزمن', 'یبوست',
				'خون در مدفوع', 'مدفوع سیاه', 'زردی', 'یرقان', 'تهوع', 'استفراغ', 'بی‌اشتهایی',
				'کاهش وزن', 'سیری زودرس', 'آروغ', 'بلع دردناک', 'اختلال بلع', 'خارش پوست',
			],
			'treatment' => [
				'آندوسکوپی', 'کولونوسکوپی', 'ای‌آر‌سی‌پی', 'ERCP', 'آندوسونوگرافی', 'بیوپسی کبد',
				'فیبرواسکن', 'الاستوگرافی', 'پولیپکتومی', 'کوله‌سیستکتومی', 'برداشتن کیسه صفرا',
				'اسلیو معده', 'بای‌پس معده', 'جراحی چاقی', 'رژیم غذایی', 'رژیم کم‌چرب', 'لاپاراسکوپی',
				'بستن واریس مری', 'درمان هلیکوباکتر', 'تست تنفسی اوره',
			],
			'drug'      => [
				'امپرازول', 'پنتوپرازول', 'رابپرازول', 'لانزوپرازول', 'رانیتیدین', 'فاموتیدین',
				'مزالازین', 'اورسودوکسی‌کولیک اسید', 'لاکتولوز', 'دومپریدون', 'متوکلوپرامید',
				'سیلی‌مارین', 'پروبیوتیک',
			],
			'specialty' => [
				'متخصص گوارش', 'فوق تخصص گوارش و کبد', 'فوق تخصص گوارش', 'متخصص کبد', 'هپاتولوژیست',
				'جراح دستگاه گوارش', 'آندوسکوپیست',
			],
			'body_part' => [
				'معده', 'مری', 'اثنی‌عشر', 'روده باریک', 'روده بزرگ', 'کولون', 'رکتوم', 'مقعد',
				'کبد', 'کیسه صفرا', 'مجاری صفراوی', 'پانکراس', 'لوزالمعده', 'طحال', 'دیافراگم',
			],
		];
	}

	/**
	 * Resolve a preset slug to its dictionary, merged onto the general base.
	 *
	 * @return array<string, string[]>
	 */
	public static function for_preset( string $preset ): array {
		$base = self::general();

		if ( 'gastro_hepatology' === $preset ) {
			return self::merge( $base, self::gastro_hepatology() );
		}

		return $base;
	}

	/**
	 * @param array<string, string[]> $a
	 * @param array<string, string[]> $b
	 * @return array<string, string[]>
	 */
	private static function merge( array $a, array $b ): array {
		foreach ( $b as $category => $terms ) {
			$a[ $category ] = array_values( array_unique( array_merge( $a[ $category ] ?? [], $terms ) ) );
		}

		return $a;
	}
}
