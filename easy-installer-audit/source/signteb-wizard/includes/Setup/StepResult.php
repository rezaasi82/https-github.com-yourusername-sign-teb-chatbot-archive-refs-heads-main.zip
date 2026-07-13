<?php
/**
 * SignTeb Setup Wizard — Step Result value object
 *
 * نتیجه‌ی استانداردِ اجرای هر مرحله‌ی ویزارد. غیرقابل‌تغییر و ساده تا هم در
 * پاسخ AJAX و هم در state ذخیره شود.
 *
 * @package SignTeb_Wizard
 */

declare( strict_types=1 );

namespace SignTeb\Wizard\Setup;

defined( 'ABSPATH' ) || exit;

final class StepResult {

	public function __construct(
		public readonly bool $success,
		public readonly string $message,
		public readonly array $data = []
	) {}

	public static function ok( string $message, array $data = [] ): self {
		return new self( true, $message, $data );
	}

	public static function fail( string $message, array $data = [] ): self {
		return new self( false, $message, $data );
	}

	/** خروجی برای پاسخ AJAX. */
	public function to_array(): array {
		return [
			'success' => $this->success,
			'message' => $this->message,
			'data'    => $this->data,
		];
	}
}
