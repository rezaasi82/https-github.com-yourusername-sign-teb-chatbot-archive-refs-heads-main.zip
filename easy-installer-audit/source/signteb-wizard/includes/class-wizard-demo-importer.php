<?php
/**
 * SignTeb Setup Wizard — Demo Importer
 *
 * Import demo content options برای:
 * - solo-doctor: پزشک منفرد
 * - multi-clinic: کلینیک چندتخصصی
 * - medical-tourism: گردشگری پزشکی
 *
 * @package SignTeb_Wizard
 */

declare( strict_types=1 );

namespace SignTeb\Wizard;

defined( 'ABSPATH' ) || exit;

final class DemoImporter {

	public function import( string $demo_type ): true|\WP_Error {
		$allowed = [ 'solo-doctor', 'multi-clinic', 'medical-tourism' ];
		if ( ! in_array( $demo_type, $allowed, true ) ) {
			return new \WP_Error( 'invalid_demo', __( 'نوع دمو نامعتبر است.', STWIZ_TEXT ) );
		}

		// Set demo-specific options
		$this->set_demo_options( $demo_type );

		// Create demo doctor profile
		$this->create_demo_doctor( $demo_type );

		// Create demo pages
		$this->create_demo_pages( $demo_type );

		// Set menus
		$this->setup_menus( $demo_type );

		// Flush rewrite rules
		flush_rewrite_rules();

		update_option( 'stwiz_demo_installed', $demo_type );

		return true;
	}

	// ─── Demo Options ────────────────────────────────────────────────────────

	private function set_demo_options( string $type ): void {
		$presets = [
			'solo-doctor' => [
				'blogname'           => 'دکتر علیرضا محمدی',
				'blogdescription'    => 'متخصص ارتوپدی و جراح مفاصل',
				'stmc_clinic_name'   => 'کلینیک دکتر محمدی',
				'stmc_market'        => 'ir',
				'stmc_geo_placename' => 'تهران',
				'stmc_geo_region'    => 'IR-16',
				'stmc_country_code'  => 'IR',
			],
			'multi-clinic' => [
				'blogname'           => 'کلینیک تخصصی پارس',
				'blogdescription'    => 'مرکز تخصصی درمان با ۱۲ پزشک متخصص',
				'stmc_clinic_name'   => 'کلینیک پارس',
				'stmc_market'        => 'ir',
				'stmc_geo_placename' => 'تهران',
			],
			'medical-tourism' => [
				'blogname'           => 'Iran Health Tourism',
				'blogdescription'    => 'Premium Medical Services for International Patients',
				'stmc_clinic_name'   => 'Iran Health Tourism Center',
				'stmc_market'        => 'multi',
				'stmc_primary_language' => 'en',
				'stmc_country_code'  => 'IR',
			],
		];

		foreach ( $presets[ $type ] ?? [] as $key => $val ) {
			update_option( $key, $val );
		}
	}

	// ─── Demo Doctor ─────────────────────────────────────────────────────────

	private function create_demo_doctor( string $type ): int {
		// Check if demo doctor already exists
		$existing = get_posts( [
			'post_type'  => 'doctor',
			'meta_key'   => '_stwiz_demo',
			'meta_value' => '1',
			'posts_per_page' => 1,
			'fields'     => 'ids',
		] );

		if ( ! empty( $existing ) ) {
			return $existing[0];
		}

		$demo_data = match( $type ) {
			'solo-doctor'     => [ 'name' => 'دکتر علیرضا محمدی', 'specialty' => 'متخصص ارتوپدی', 'exp' => 15 ],
			'multi-clinic'    => [ 'name' => 'دکتر سارا احمدی', 'specialty' => 'متخصص زنان', 'exp' => 12 ],
			'medical-tourism' => [ 'name' => 'Dr. Reza Karimi', 'specialty' => 'Plastic & Reconstructive Surgeon', 'exp' => 18 ],
			default           => [ 'name' => 'دکتر نمونه', 'specialty' => 'پزشک عمومی', 'exp' => 5 ],
		};

		$post_id = wp_insert_post( [
			'post_type'    => 'doctor',
			'post_title'   => $demo_data['name'],
			'post_status'  => 'publish',
			'post_content' => '<p>پروفایل نمونه پزشک. محتوای این بخش را از طریق ویرایشگر تغییر دهید.</p>',
			'post_excerpt' => 'پزشک متخصص با ' . $demo_data['exp'] . ' سال تجربه در زمینه ' . $demo_data['specialty'],
		] );

		if ( ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, 'stmc_doctor_specialty', $demo_data['specialty'] );
			update_post_meta( $post_id, 'stmc_doctor_experience_yrs', $demo_data['exp'] );
			update_post_meta( $post_id, 'stmc_doctor_patients_count', 3000 );
			update_post_meta( $post_id, '_stwiz_demo', '1' );

			// Assign to specialty term
			$term = wp_insert_term( $demo_data['specialty'], 'specialty' );
			if ( ! is_wp_error( $term ) ) {
				wp_set_object_terms( $post_id, $term['term_id'], 'specialty' );
			}
		}

		return is_wp_error( $post_id ) ? 0 : $post_id;
	}

	// ─── Demo Pages ──────────────────────────────────────────────────────────

	private function create_demo_pages( string $type ): void {
		$pages = [
			[
				'title'   => 'خانه',
				'slug'    => 'home',
				'content' => '<!-- wp:signteb/doctor-hero /--><!-- wp:signteb/stats-counter /--><!-- wp:signteb/service-grid /--><!-- wp:signteb/faq-accordion /--><!-- wp:signteb/appointment-cta /--><!-- wp:signteb/contact-cta /-->',
				'template'=> '',
			],
			[
				'title'   => 'درباره ما',
				'slug'    => 'about',
				'content' => '<p>درباره کلینیک ما...</p>',
				'template'=> '',
			],
			[
				'title'   => 'خدمات پزشکی',
				'slug'    => 'services',
				'content' => '<!-- wp:signteb/service-grid {"columns":3} /-->',
				'template'=> '',
			],
			[
				'title'   => 'رزرو نوبت',
				'slug'    => 'appointment',
				'content' => '<!-- wp:signteb/appointment-cta /-->',
				'template'=> 'page-landing',
			],
			[
				'title'   => 'تماس با ما',
				'slug'    => 'contact',
				'content' => '<!-- wp:signteb/contact-cta /-->',
				'template'=> '',
			],
		];

		$home_id = 0;

		foreach ( $pages as $page_data ) {
			// Skip if exists
			$existing = get_page_by_path( $page_data['slug'] );
			if ( $existing ) {
				if ( 'home' === $page_data['slug'] ) {
					$home_id = $existing->ID;
				}
				continue;
			}

			$pid = wp_insert_post( [
				'post_type'    => 'page',
				'post_title'   => $page_data['title'],
				'post_name'    => $page_data['slug'],
				'post_content' => $page_data['content'],
				'post_status'  => 'publish',
				'page_template'=> $page_data['template'],
			] );

			if ( ! is_wp_error( $pid ) ) {
				update_post_meta( $pid, '_stwiz_demo', '1' );
				if ( 'home' === $page_data['slug'] ) {
					$home_id = $pid;
				}
			}
		}

		// Set as static front page
		if ( $home_id ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $home_id );
		}
	}

	// ─── Menus ───────────────────────────────────────────────────────────────

	private function setup_menus( string $type ): void {
		$menu_name = __( 'منوی اصلی', STWIZ_TEXT );
		$menu_id   = wp_create_nav_menu( $menu_name );

		if ( is_wp_error( $menu_id ) ) {
			// Menu might already exist
			$menu = wp_get_nav_menu_object( $menu_name );
			if ( $menu ) {
				$menu_id = $menu->term_id;
			} else {
				return;
			}
		}

		$items = [ 'home' => 'خانه', 'services' => 'خدمات', 'about' => 'درباره ما', 'appointment' => 'رزرو نوبت', 'contact' => 'تماس' ];

		foreach ( $items as $slug => $label ) {
			$page = get_page_by_path( $slug );
			if ( $page ) {
				wp_update_nav_menu_item( $menu_id, 0, [
					'menu-item-title'     => $label,
					'menu-item-object-id' => $page->ID,
					'menu-item-object'    => 'page',
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
				] );
			}
		}

		// Assign to primary location
		$locations = get_theme_mod( 'nav_menu_locations', [] );
		$locations['primary'] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
	}
}
