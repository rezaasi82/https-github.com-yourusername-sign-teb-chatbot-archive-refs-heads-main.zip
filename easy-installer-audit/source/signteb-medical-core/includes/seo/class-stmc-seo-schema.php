<?php
/**
 * SignTeb Medical Core — Schema.org JSON-LD Generator
 *
 * ۱۰ نوع Schema برای همه CPT‌ها:
 * Physician, MedicalClinic, MedicalProcedure, MedicalTherapy,
 * MedicalCondition, FAQPage, Review, MedicalStudy, VideoObject, BreadcrumbList
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC\Seo;

use STMC\Loader;

defined( 'ABSPATH' ) || exit;

final class Schema {

	public function __construct( private readonly Loader $loader ) {
		$this->loader->add_action( 'wp_head', $this, 'output_schema', 2 );
	}

	// ─── Router ───────────────────────────────────────────────────────────────

	public function output_schema(): void {
		if ( ! apply_filters( 'stmc_schema_enabled', (bool) get_option( 'stmc_schema_enabled', '1' ) ) ) {
			return;
		}

		$schemas = [];

		if ( is_singular( 'doctor' ) ) {
			$schemas[] = $this->physician_schema( get_the_ID() );
			$schemas[] = $this->breadcrumb_schema();
			// نظرات تأیید شده این پزشک — هر کدام یک Review جدا
			foreach ( $this->reviews_schema_for_doctor( get_the_ID() ) as $review_schema ) {
				$schemas[] = $review_schema;
			}
		} elseif ( is_singular( 'medical-service' ) ) {
			$schemas[] = $this->medical_procedure_schema( get_the_ID() );
			$schemas[] = $this->breadcrumb_schema();
		} elseif ( is_singular( 'treatment' ) ) {
			$schemas[] = $this->medical_therapy_schema( get_the_ID() );
			$schemas[] = $this->breadcrumb_schema();
		} elseif ( is_singular( 'disease' ) ) {
			$schemas[] = $this->medical_condition_schema( get_the_ID() );
			$schemas[] = $this->breadcrumb_schema();
		} elseif ( is_singular( 'clinic' ) ) {
			$schemas[] = $this->medical_clinic_schema( get_the_ID() );
			$schemas[] = $this->breadcrumb_schema();
		} elseif ( is_singular( 'case-study' ) ) {
			$schemas[] = $this->medical_study_schema( get_the_ID() );
			$schemas[] = $this->breadcrumb_schema();
		} elseif ( is_singular( 'medical-video' ) ) {
			$schemas[] = $this->video_object_schema( get_the_ID() );
			$schemas[] = $this->breadcrumb_schema();
		} elseif ( is_front_page() || is_home() ) {
			$schemas[] = $this->website_schema();
			$schemas[] = $this->organization_schema();
		} elseif ( is_singular( 'page' ) || is_singular( 'post' ) ) {
			$schemas[] = $this->article_schema( get_the_ID() );
			$schemas[] = $this->breadcrumb_schema();
			// Add FAQ schema if post has FAQ blocks
			$faq = $this->faq_schema_from_blocks( get_the_ID() );
			if ( $faq ) {
				$schemas[] = $faq;
			}
		}

		// Apply filter to allow external modification
		$schemas = apply_filters( 'stmc_schema_data', array_filter( $schemas ), get_the_ID() );

		foreach ( $schemas as $schema ) {
			$this->print_schema( $schema );
		}
	}

	// ─── Schema Builders ──────────────────────────────────────────────────────

	private function physician_schema( int $post_id ): array {
		$post      = get_post( $post_id );
		$name      = get_the_title( $post_id );
		$url       = get_permalink( $post_id );
		$desc      = get_the_excerpt( $post_id );
		$img       = get_the_post_thumbnail_url( $post_id, 'large' );
		$specialty = get_post_meta( $post_id, 'stmc_doctor_specialty', true );
		$subspc    = get_post_meta( $post_id, 'stmc_doctor_subspecialty', true );
		$exp       = (int) get_post_meta( $post_id, 'stmc_doctor_experience_yrs', true );
		$license   = get_post_meta( $post_id, 'stmc_doctor_license_no', true );
		$edu_raw   = get_post_meta( $post_id, 'stmc_doctor_education', true );
		$phone     = get_post_meta( $post_id, 'stmc_doctor_phone', true );
		$lat       = get_post_meta( $post_id, 'stmc_doctor_lat', true );
		$lng       = get_post_meta( $post_id, 'stmc_doctor_lng', true );
		$langs     = get_post_meta( $post_id, 'stmc_doctor_languages', true );

		// Specialties array
		$medical_specialties = array_filter( [ $specialty, $subspc ] );

		// Education objects
		$education = [];
		if ( $edu_raw ) {
			foreach ( explode( "\n", $edu_raw ) as $line ) {
				$parts = array_map( 'trim', explode( '—', $line ) );
				if ( count( $parts ) >= 2 ) {
					$education[] = [
						'@type'       => 'EducationalOrganization',
						'name'        => $parts[1] ?? '',
						'description' => $parts[0] ?? '',
					];
				}
			}
		}

		// Average rating from reviews — از Repository واحد می‌خوانیم
		$review_stats  = ( new \STMC\Reviews\Repository() )->get_doctor_stats( $post_id );
		$avg_rating    = $review_stats['avg_rating'];
		$review_count  = $review_stats['total'];

		$schema = [
			'@context'          => 'https://schema.org',
			'@type'             => 'Physician',
			'name'              => $name,
			'url'               => $url,
			'description'       => $desc ?: '',
			'medicalSpecialty'  => count( $medical_specialties ) === 1 ? reset( $medical_specialties ) : array_values( $medical_specialties ),
		];

		if ( $img ) {
			$schema['image'] = [
				'@type' => 'ImageObject',
				'url'   => $img,
			];
		}

		if ( ! empty( $education ) ) {
			$schema['alumniOf'] = $education;
		}

		if ( $license ) {
			$schema['identifier'] = [
				'@type' => 'PropertyValue',
				'name'  => 'شماره نظام پزشکی',
				'value' => $license,
			];
		}

		if ( $phone ) {
			$schema['telephone'] = $phone;
		}

		if ( is_array( $langs ) && ! empty( $langs ) ) {
			$schema['knowsLanguage'] = $langs;
		}

		if ( $lat && $lng ) {
			$schema['geo'] = [
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $lat,
				'longitude' => (float) $lng,
			];
		}

		if ( $avg_rating > 0 && $review_count > 0 ) {
			$schema['aggregateRating'] = [
				'@type'       => 'AggregateRating',
				'ratingValue' => round( $avg_rating, 1 ),
				'reviewCount' => $review_count,
				'bestRating'  => '5',
				'worstRating' => '1',
			];
		}

		// Clinic affiliation
		$clinic_ids = get_post_meta( $post_id, 'stmc_doctor_clinic_ids', true );
		if ( is_array( $clinic_ids ) && ! empty( $clinic_ids ) ) {
			$affiliations = [];
			foreach ( $clinic_ids as $clinic_id ) {
				$affiliations[] = [
					'@type' => 'MedicalClinic',
					'name'  => get_the_title( (int) $clinic_id ),
					'url'   => get_permalink( (int) $clinic_id ),
				];
			}
			$schema['affiliation'] = $affiliations;
		}

		return $schema;
	}

	private function medical_clinic_schema( int $post_id ): array {
		$name    = get_the_title( $post_id );
		$url     = get_permalink( $post_id );
		$desc    = get_the_excerpt( $post_id );
		$img     = get_the_post_thumbnail_url( $post_id, 'large' );
		$phone   = get_post_meta( $post_id, 'stmc_clinic_phone', true );
		$address = get_post_meta( $post_id, 'stmc_clinic_address', true );
		$lat     = get_post_meta( $post_id, 'stmc_clinic_lat', true );
		$lng     = get_post_meta( $post_id, 'stmc_clinic_lng', true );

		$schema = [
			'@context'    => 'https://schema.org',
			'@type'       => [ 'MedicalClinic', 'LocalBusiness' ],
			'name'        => $name,
			'url'         => $url,
			'description' => $desc ?: '',
		];

		if ( $img ) {
			$schema['image'] = $img;
		}

		if ( $phone ) {
			$schema['telephone'] = $phone;
		}

		if ( $address ) {
			$schema['address'] = [
				'@type'           => 'PostalAddress',
				'streetAddress'   => $address,
				'addressCountry'  => get_option( 'stmc_country_code', 'IR' ),
			];
		}

		if ( $lat && $lng ) {
			$schema['geo'] = [
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $lat,
				'longitude' => (float) $lng,
			];
		}

		return $schema;
	}

	private function medical_procedure_schema( int $post_id ): array {
		$name     = get_the_title( $post_id );
		$url      = get_permalink( $post_id );
		$desc     = get_the_excerpt( $post_id );
		$duration = get_post_meta( $post_id, 'stmc_service_duration', true );
		$body_loc = get_post_meta( $post_id, 'stmc_service_body_location', true );

		$schema = [
			'@context'    => 'https://schema.org',
			'@type'       => 'MedicalProcedure',
			'name'        => $name,
			'url'         => $url,
			'description' => $desc ?: '',
		];

		if ( $duration ) {
			$schema['procedureDuration'] = $duration;
		}

		if ( $body_loc ) {
			$schema['bodyLocation'] = $body_loc;
		}

		return $schema;
	}

	private function medical_therapy_schema( int $post_id ): array {
		return [
			'@context'    => 'https://schema.org',
			'@type'       => 'MedicalTherapy',
			'name'        => get_the_title( $post_id ),
			'url'         => get_permalink( $post_id ),
			'description' => get_the_excerpt( $post_id ) ?: '',
		];
	}

	private function medical_condition_schema( int $post_id ): array {
		$symptoms    = get_post_meta( $post_id, 'stmc_disease_symptoms', true );
		$risk_factors = get_post_meta( $post_id, 'stmc_disease_risk_factors', true );

		$schema = [
			'@context'    => 'https://schema.org',
			'@type'       => 'MedicalCondition',
			'name'        => get_the_title( $post_id ),
			'url'         => get_permalink( $post_id ),
			'description' => get_the_excerpt( $post_id ) ?: '',
		];

		if ( $symptoms ) {
			$schema['signOrSymptom'] = array_map(
				fn( $s ) => [ '@type' => 'MedicalSignOrSymptom', 'name' => trim( $s ) ],
				explode( ',', $symptoms )
			);
		}

		if ( $risk_factors ) {
			$schema['riskFactor'] = array_map(
				fn( $r ) => [ '@type' => 'MedicalRiskFactor', 'name' => trim( $r ) ],
				explode( ',', $risk_factors )
			);
		}

		return $schema;
	}

	private function medical_study_schema( int $post_id ): array {
		return [
			'@context'    => 'https://schema.org',
			'@type'       => 'MedicalStudy',
			'name'        => get_the_title( $post_id ),
			'url'         => get_permalink( $post_id ),
			'description' => get_the_excerpt( $post_id ) ?: '',
			'status'      => 'Completed',
		];
	}

	/**
	 * یک Review schema جدا برای هر نظر تأیید شده‌ی این پزشک
	 * (جایگزین کامل متد قدیمی review_schema که از CPT testimonial می‌خواند)
	 *
	 * @return array[] هر آیتم یک Review schema کامل
	 */
	private function reviews_schema_for_doctor( int $doctor_id ): array {
		$repo    = new \STMC\Reviews\Repository();
		$reviews = $repo->get_approved( $doctor_id, 10 ); // حداکثر ۱۰ نظر در Schema برای جلوگیری از حجم زیاد

		if ( empty( $reviews ) ) {
			return [];
		}

		$doctor_url  = get_permalink( $doctor_id );
		$doctor_name = get_the_title( $doctor_id );
		$schemas     = [];

		foreach ( $reviews as $review ) {
			$schema = [
				'@context'     => 'https://schema.org',
				'@type'        => 'Review',
				'reviewRating' => [
					'@type'       => 'Rating',
					'ratingValue' => (int) $review->rating,
					'bestRating'  => '5',
					'worstRating' => '1',
				],
				'author'       => [
					'@type' => 'Person',
					'name'  => $review->reviewer_name ?: __( 'بیمار ناشناس', STMC_TEXT ),
				],
				'reviewBody'   => wp_strip_all_tags( (string) $review->content ),
				'itemReviewed' => [
					'@type' => 'Physician',
					'name'  => $doctor_name,
					'url'   => $doctor_url,
				],
			];

			if ( ! empty( $review->created_at ) ) {
				$schema['datePublished'] = wp_date( 'c', strtotime( $review->created_at ) );
			}

			$schemas[] = $schema;
		}

		return $schemas;
	}

	private function video_object_schema( int $post_id ): array {
		$video_url = get_post_meta( $post_id, 'stmc_video_url', true );
		$thumb     = get_the_post_thumbnail_url( $post_id, 'large' );
		$date      = get_the_date( 'c', $post_id );

		$schema = [
			'@context'        => 'https://schema.org',
			'@type'           => 'VideoObject',
			'name'            => get_the_title( $post_id ),
			'description'     => get_the_excerpt( $post_id ) ?: '',
			'uploadDate'      => $date,
		];

		if ( $thumb ) {
			$schema['thumbnailUrl'] = $thumb;
		}

		if ( $video_url ) {
			$schema['contentUrl'] = $video_url;
			$schema['embedUrl']   = $video_url;
		}

		return $schema;
	}

	private function article_schema( int $post_id ): array {
		$author    = get_the_author_meta( 'display_name', get_post_field( 'post_author', $post_id ) );
		$img       = get_the_post_thumbnail_url( $post_id, 'large' );
		$published = get_the_date( 'c', $post_id );
		$modified  = get_the_modified_date( 'c', $post_id );

		$schema = [
			'@context'         => 'https://schema.org',
			'@type'            => 'Article',
			'headline'         => get_the_title( $post_id ),
			'description'      => get_the_excerpt( $post_id ) ?: '',
			'url'              => get_permalink( $post_id ),
			'datePublished'    => $published,
			'dateModified'     => $modified,
			'author'           => [
				'@type' => 'Person',
				'name'  => $author,
			],
			'publisher'        => [
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
				'logo'  => [
					'@type' => 'ImageObject',
					'url'   => get_site_icon_url( 512 ) ?: '',
				],
			],
		];

		if ( $img ) {
			$schema['image'] = $img;
		}

		return $schema;
	}

	private function website_schema(): array {
		return [
			'@context'        => 'https://schema.org',
			'@type'           => 'WebSite',
			'name'            => get_bloginfo( 'name' ),
			'url'             => home_url( '/' ),
			'description'     => get_bloginfo( 'description' ),
			'potentialAction' => [
				'@type'       => 'SearchAction',
				'target'      => [
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				],
				'query-input' => 'required name=search_term_string',
			],
		];
	}

	private function organization_schema(): array {
		return [
			'@context'   => 'https://schema.org',
			'@type'      => [ 'MedicalOrganization', 'Organization' ],
			'name'       => get_option( 'stmc_clinic_name', get_bloginfo( 'name' ) ),
			'url'        => home_url( '/' ),
			'telephone'  => get_option( 'stmc_clinic_phone', '' ),
			'email'      => get_option( 'stmc_clinic_email', '' ),
			'address'    => [
				'@type'         => 'PostalAddress',
				'streetAddress' => get_option( 'stmc_clinic_address', '' ),
				'addressCountry'=> get_option( 'stmc_country_code', 'IR' ),
			],
			'sameAs'     => array_filter( [
				get_option( 'stmc_social_instagram', '' ),
				get_option( 'stmc_social_linkedin', '' ),
				get_option( 'stmc_social_youtube', '' ),
			] ),
		];
	}

	private function breadcrumb_schema(): array {
		$crumbs    = function_exists( 'stmc_get_breadcrumbs' ) ? stmc_get_breadcrumbs() : [];
		$list_items = [];

		foreach ( $crumbs as $index => $crumb ) {
			$list_items[] = [
				'@type'    => 'ListItem',
				'position' => $index + 1,
				'name'     => $crumb['name'],
				'item'     => $crumb['url'] ?: get_permalink(),
			];
		}

		if ( empty( $list_items ) ) {
			return [];
		}

		return [
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $list_items,
		];
	}

	/**
	 * سوالات متداول را از Gutenberg blocks استخراج می‌کند
	 */
	private function faq_schema_from_blocks( int $post_id ): ?array {
		$post = get_post( $post_id );
		if ( ! $post || ! has_blocks( $post->post_content ) ) {
			return null;
		}

		$blocks = parse_blocks( $post->post_content );
		$qa_pairs = [];

		foreach ( $blocks as $block ) {
			if ( 'signteb/faq-item' === $block['blockName'] ) {
				$qa_pairs[] = [
					'@type'          => 'Question',
					'name'           => wp_strip_all_tags( $block['attrs']['question'] ?? '' ),
					'acceptedAnswer' => [
						'@type' => 'Answer',
						'text'  => wp_strip_all_tags( render_block( $block ) ),
					],
				];
			}
		}

		if ( empty( $qa_pairs ) ) {
			return null;
		}

		return [
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $qa_pairs,
		];
	}

	// ─── Output ───────────────────────────────────────────────────────────────

	private function print_schema( array $schema ): void {
		if ( empty( $schema ) ) {
			return;
		}

		$json = wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			return;
		}

		// جلوگیری از شکستن زودهنگام تگ </script> در صورتی که یکی از مقادیر
		// (مثلاً خلاصه دستی پست یا متن نظر) حاوی رشته «</script» باشد — چون
		// JSON_UNESCAPED_SLASHES کاراکتر «/» را escape نمی‌کند و بدون این
		// جایگزینی خروجی می‌تواند به یک حمله XSS ذخیره‌شده تبدیل شود.
		$json = str_replace( '</', '<\/', $json );

		echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
	}

	// ─── Helpers ──────────────────────────────────────────────────────────────
	// نکته: محاسبه میانگین امتیاز و تعداد نظرات اکنون در STMC\Reviews\Repository
	// متمرکز شده (متد get_doctor_stats) تا یک منبع واحد حقیقت داشته باشیم.
}
