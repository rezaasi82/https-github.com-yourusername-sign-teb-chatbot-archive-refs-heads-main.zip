<?php
/**
 * SignTeb Medical Core — Plugin Orchestrator
 *
 * تمام sub-system‌ها را لود و راه‌اندازی می‌کند.
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private Loader $loader;

	public function __construct() {
		$this->loader = new Loader();
	}

	/**
	 * راه‌اندازی همه components
	 */
	public function run(): void {
		$this->load_textdomain();
		$this->register_post_types();
		$this->register_taxonomies();
		$this->register_meta_boxes();
		$this->register_seo();
		$this->register_appointment();
		$this->register_reviews();
		$this->register_sms();
		$this->register_admin();
		$this->loader->run();
	}

	// ── i18n ──────────────────────────────────────────────────────────────────

	private function load_textdomain(): void {
		load_plugin_textdomain(
			STMC_TEXT,
			false,
			dirname( plugin_basename( STMC_FILE ) ) . '/languages'
		);
	}

	// ── Post Types ────────────────────────────────────────────────────────────

	private function register_post_types(): void {
		$cpts = [
			'PostTypes\Doctor',
			'PostTypes\Service',
			'PostTypes\Treatment',
			'PostTypes\Disease',
			'PostTypes\Clinic',
			'PostTypes\Faq',
			'PostTypes\CaseStudy',
			'PostTypes\Video',
		];

		foreach ( $cpts as $class ) {
			$fqn = 'STMC\\' . $class;
			if ( class_exists( $fqn ) ) {
				( new $fqn( $this->loader ) );
			}
		}
	}

	// ── Taxonomies ────────────────────────────────────────────────────────────

	private function register_taxonomies(): void {
		$taxes = [
			'Taxonomies\Specialty',
			'Taxonomies\Location',
			'Taxonomies\Condition',
			'Taxonomies\TreatmentType',
		];

		foreach ( $taxes as $class ) {
			$fqn = 'STMC\\' . $class;
			if ( class_exists( $fqn ) ) {
				( new $fqn( $this->loader ) );
			}
		}
	}

	// ── Meta Boxes ────────────────────────────────────────────────────────────

	private function register_meta_boxes(): void {
		if ( is_admin() ) {
			$meta_classes = [
				'Meta\Doctor',
				'Meta\Service',
				'Meta\Clinic',
				'Meta\Disease',
			];

			foreach ( $meta_classes as $class ) {
				$fqn = 'STMC\\' . $class;
				if ( class_exists( $fqn ) ) {
					( new $fqn( $this->loader ) );
				}
			}
		}
	}

	// ── SEO Engine ────────────────────────────────────────────────────────────

	private function register_seo(): void {
		new Seo\Schema( $this->loader );
		new Seo\InternalLinks( $this->loader );
		new Seo\TopicCluster( $this->loader );
		new Seo\LocalSeo( $this->loader );
	}

	// ── Appointment ───────────────────────────────────────────────────────────

	private function register_appointment(): void {
		new Appointment\Form( $this->loader );
		new Appointment\Ajax( $this->loader );
	}

	// ── Reviews (نظرات بیماران) ───────────────────────────────────────────────

	private function register_reviews(): void {
		new Reviews\Form( $this->loader );
		new Reviews\Ajax( $this->loader );
	}

	// ── SMS Notifications ────────────────────────────────────────────────────

	private function register_sms(): void {
		new Sms\Notifier( $this->loader );
	}

	// ── Admin ─────────────────────────────────────────────────────────────────

	private function register_admin(): void {
		if ( is_admin() ) {
			new Admin\Menu( $this->loader );
			new Admin\Appointments( $this->loader );
			new Admin\Availability( $this->loader );
			new Admin\Reviews( $this->loader );
			new Admin\SeoDashboard( $this->loader );
			new Admin\Settings( $this->loader );
		}
	}
}
