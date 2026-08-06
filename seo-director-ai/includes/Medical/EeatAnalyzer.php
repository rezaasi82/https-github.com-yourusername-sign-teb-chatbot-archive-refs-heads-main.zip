<?php
/**
 * Medical E-E-A-T analyzer (Medical Pack). YMYL medical pages are held to a
 * higher bar by Google: real authorship, medical review, citations to
 * authoritative sources, freshness, and a disclaimer. This runs deterministic
 * checks against a post and returns a 0–100 trust score with a per-check
 * breakdown — no AI, so it is reproducible and cheap.
 *
 * @package SEODirector
 */

namespace SEODirector\Medical;

defined( 'ABSPATH' ) || exit;

final class EeatAnalyzer {

	/** Authoritative medical domains a citation link earns trust from. */
	private const AUTHORITATIVE = [
		'who.int', 'nih.gov', 'ncbi.nlm.nih.gov', 'pubmed.ncbi.nlm.nih.gov', 'mayoclinic.org',
		'medlineplus.gov', 'cdc.gov', 'cochrane.org', 'nice.org.uk', 'uptodate.com',
		'hopkinsmedicine.org', 'clevelandclinic.org', 'health.harvard.edu', 'bmj.com',
		'thelancet.com', 'nejm.org', 'irimc.org', // Iranian Medical Council
	];

	/** Phrases that signal a medical disclaimer (fa + en). */
	private const DISCLAIMER_MARKERS = [
		'مشورت با پزشک', 'با پزشک خود مشورت', 'جایگزین توصیه پزشک', 'جنبه اطلاع‌رسانی',
		'consult your doctor', 'medical advice', 'informational purposes',
	];

	/** Phrases that signal a medical reviewer (fa + en). */
	private const REVIEWER_MARKERS = [
		'بازبینی توسط', 'بازبینی پزشکی', 'تأیید پزشک', 'reviewed by', 'medically reviewed',
	];

	public function __construct( private MedicalEntityEngine $entities ) {}

	/** Agency trust markers (fa) for the marketing preset. */
	private const AGENCY_MARKERS = [
		'portfolio'   => [ 'نمونه کار', 'نمونه‌کار', 'نمونه‌کارها', 'نمونه کارها', 'نمونه‌ها', 'پروژه', 'portfolio' ],
		'pricing'     => [ 'تعرفه', 'قیمت', 'هزینه', 'پکیج', 'پلن', 'plans', 'pricing' ],
		'testimonial' => [ 'نظر مشتری', 'نظرات مشتری', 'رضایت مشتری', 'رضایت مشتریان', 'بازخورد', 'testimonial' ],
		'about'       => [ 'درباره ما', 'تیم ما', 'سابقه', 'تجربه', 'چرا ما', 'رزومه' ],
		'cta'         => [ 'مشاوره رایگان', 'تماس بگیرید', 'ثبت سفارش', 'درخواست', 'همین حالا', 'رزرو', 'استعلام قیمت', 'شروع کنید' ],
	];

	/**
	 * @return array{score: int, checks: array<int, array{code: string, label: string, points: int, max: int, ok: bool, detail: string}>, entities_found: int, is_medical: bool, mode: string}|\WP_Error
	 */
	public function analyze( int $post_id ): array|\WP_Error {
		$post = get_post( $post_id );
		if ( null === $post || 'publish' !== $post->post_status ) {
			return new \WP_Error( 'sda_not_found', __( 'Post not found or not published.', 'seo-director-ai' ), [ 'status' => 404 ] );
		}

		// The marketing preset is a non-clinical (agency) site — score trust
		// signals that matter for a service business, not clinical YMYL ones.
		if ( 'medical_marketing' === $this->entities->active_preset() ) {
			return $this->analyze_agency( $post );
		}

		$content = (string) $post->post_content;
		$text    = mb_strtolower( wp_strip_all_tags( $content ) );
		$found   = $this->entities->detect_in_post( $post_id );

		$checks = [];

		// Author present and not the generic "admin".
		$author      = get_userdata( (int) $post->post_author );
		$author_name = $author ? (string) $author->display_name : '';
		$author_ok   = '' !== $author_name && ! in_array( mb_strtolower( $author_name ), [ 'admin', 'administrator', 'مدیر' ], true );
		$checks[]    = $this->check( 'author', __( 'Named author', 'seo-director-ai' ), $author_ok ? 20 : 0, 20, $author_ok, $author_ok ? $author_name : __( 'Assign a real author (not “admin”).', 'seo-director-ai' ) );

		// Author bio / description filled in — Experience & Expertise signal.
		$bio_ok   = $author && '' !== trim( (string) $author->description );
		$checks[] = $this->check( 'author_bio', __( 'Author bio', 'seo-director-ai' ), $bio_ok ? 10 : 0, 10, (bool) $bio_ok, $bio_ok ? __( 'Present.', 'seo-director-ai' ) : __( 'Add credentials to the author profile (Biographical Info).', 'seo-director-ai' ) );

		// Medical reviewer mentioned.
		$reviewer_ok = $this->contains_any( $text, self::REVIEWER_MARKERS );
		$checks[]    = $this->check( 'reviewer', __( 'Medical reviewer', 'seo-director-ai' ), $reviewer_ok ? 15 : 0, 15, $reviewer_ok, $reviewer_ok ? __( 'Mentioned.', 'seo-director-ai' ) : __( 'State who medically reviewed the article.', 'seo-director-ai' ) );

		// Citations to authoritative domains.
		$citations = $this->authoritative_links( $content );
		$cite_ok   = $citations > 0;
		$checks[]  = $this->check(
			'citations',
			__( 'Authoritative citations', 'seo-director-ai' ),
			$citations >= 2 ? 20 : ( $cite_ok ? 12 : 0 ),
			20,
			$cite_ok,
			$cite_ok
				? sprintf(
					/* translators: %d: number of citations */
					__( '%d link(s) to authoritative medical sources.', 'seo-director-ai' ),
					$citations
				)
				: __( 'Cite WHO, PubMed, Mayo Clinic, the Iranian Medical Council, etc.', 'seo-director-ai' )
		);

		// Freshness — updated within 12 months.
		$age_days = ( time() - (int) get_post_timestamp( $post, 'modified' ) ) / DAY_IN_SECONDS;
		$fresh_ok = $age_days <= 365;
		$checks[] = $this->check(
			'freshness',
			__( 'Recently updated', 'seo-director-ai' ),
			$fresh_ok ? 15 : 0,
			15,
			$fresh_ok,
			sprintf(
				/* translators: %d: days since last update */
				__( 'Last updated %d days ago.', 'seo-director-ai' ),
				(int) $age_days
			)
		);

		// Disclaimer present.
		$disclaimer_ok = $this->contains_any( $text, self::DISCLAIMER_MARKERS );
		$checks[]      = $this->check( 'disclaimer', __( 'Medical disclaimer', 'seo-director-ai' ), $disclaimer_ok ? 10 : 0, 10, $disclaimer_ok, $disclaimer_ok ? __( 'Present.', 'seo-director-ai' ) : __( 'Add a “consult your doctor” disclaimer.', 'seo-director-ai' ) );

		// Depth of medical substance — at least 3 distinct entities.
		$depth_ok = count( $found ) >= 3;
		$checks[] = $this->check(
			'entity_depth',
			__( 'Topic depth', 'seo-director-ai' ),
			$depth_ok ? 10 : ( count( $found ) > 0 ? 5 : 0 ),
			10,
			$depth_ok,
			sprintf(
				/* translators: %d: number of medical entities */
				__( '%d medical concept(s) covered.', 'seo-director-ai' ),
				count( $found )
			)
		);

		$score = array_sum( array_column( $checks, 'points' ) );

		return [
			'score'          => $score,
			'checks'         => $checks,
			'entities_found' => count( $found ),
			'is_medical'     => count( $found ) > 0,
			'mode'           => 'medical',
		];
	}

	/**
	 * Agency trust score for the marketing preset — measures the signals a
	 * service business needs (portfolio, pricing, testimonials, contact,
	 * about, and a clear call to action) instead of clinical YMYL signals.
	 *
	 * @return array{score: int, checks: array<int, array<string, mixed>>, entities_found: int, is_medical: bool, mode: string}
	 */
	private function analyze_agency( \WP_Post $post ): array {
		$content = (string) $post->post_content;
		$text    = mb_strtolower( wp_strip_all_tags( $content ) );
		$found   = $this->entities->detect_in_post( (int) $post->ID );

		$checks = [];

		// Contact: a tel:/mailto:/wa.me link or contact wording.
		$has_contact = (bool) preg_match( '/href=["\'](tel:|mailto:|https?:\/\/wa\.me)/i', $content )
			|| $this->contains_any( $text, [ 'تماس', 'شماره تماس', 'واتساپ', 'واتس اپ', 'ایمیل', 'آدرس دفتر' ] );
		$checks[] = $this->check( 'contact', __( 'Contact information', 'seo-director-ai' ), $has_contact ? 20 : 0, 20, $has_contact, $has_contact ? __( 'Present (phone/email/WhatsApp).', 'seo-director-ai' ) : __( 'Add a visible phone / WhatsApp / email so visitors can reach you.', 'seo-director-ai' ) );

		// Pricing / tariff.
		$has_pricing = $this->contains_any( $text, self::AGENCY_MARKERS['pricing'] );
		$checks[] = $this->check( 'pricing', __( 'Pricing / tariff', 'seo-director-ai' ), $has_pricing ? 15 : 0, 15, $has_pricing, $has_pricing ? __( 'Mentioned.', 'seo-director-ai' ) : __( 'State pricing or a tariff/quote path — a strong trust and conversion signal.', 'seo-director-ai' ) );

		// Portfolio / samples.
		$has_portfolio = $this->contains_any( $text, self::AGENCY_MARKERS['portfolio'] );
		$checks[] = $this->check( 'portfolio', __( 'Portfolio / work samples', 'seo-director-ai' ), $has_portfolio ? 15 : 0, 15, $has_portfolio, $has_portfolio ? __( 'Referenced.', 'seo-director-ai' ) : __( 'Show sample work / case studies to prove capability.', 'seo-director-ai' ) );

		// Testimonials.
		$has_testimonial = $this->contains_any( $text, self::AGENCY_MARKERS['testimonial'] );
		$checks[] = $this->check( 'testimonial', __( 'Client testimonials', 'seo-director-ai' ), $has_testimonial ? 15 : 0, 15, $has_testimonial, $has_testimonial ? __( 'Present.', 'seo-director-ai' ) : __( 'Add client reviews / satisfaction quotes (social proof).', 'seo-director-ai' ) );

		// About / experience.
		$has_about = $this->contains_any( $text, self::AGENCY_MARKERS['about'] );
		$checks[] = $this->check( 'about', __( 'About / experience', 'seo-director-ai' ), $has_about ? 10 : 0, 10, $has_about, $has_about ? __( 'Present.', 'seo-director-ai' ) : __( 'Describe your team, track record, and why-choose-us.', 'seo-director-ai' ) );

		// Call to action.
		$has_cta = $this->contains_any( $text, self::AGENCY_MARKERS['cta'] );
		$checks[] = $this->check( 'cta', __( 'Call to action', 'seo-director-ai' ), $has_cta ? 10 : 0, 10, $has_cta, $has_cta ? __( 'Present.', 'seo-director-ai' ) : __( 'Add a clear next step (free consultation / request a quote / order).', 'seo-director-ai' ) );

		// Freshness — updated within 12 months.
		$age_days = ( time() - (int) get_post_timestamp( $post, 'modified' ) ) / DAY_IN_SECONDS;
		$fresh_ok = $age_days <= 365;
		$checks[] = $this->check(
			'freshness',
			__( 'Recently updated', 'seo-director-ai' ),
			$fresh_ok ? 15 : 0,
			15,
			$fresh_ok,
			sprintf(
				/* translators: %d: days since last update */
				__( 'Last updated %d days ago.', 'seo-director-ai' ),
				(int) $age_days
			)
		);

		return [
			'score'          => array_sum( array_column( $checks, 'points' ) ),
			'checks'         => $checks,
			'entities_found' => count( $found ),
			'is_medical'     => false,
			'mode'           => 'agency',
		];
	}

	/**
	 * @param 'high'|'medium'|'low'|bool $ok
	 * @return array{code: string, label: string, points: int, max: int, ok: bool, detail: string}
	 */
	private function check( string $code, string $label, int $points, int $max, bool $ok, string $detail ): array {
		return [ 'code' => $code, 'label' => $label, 'points' => $points, 'max' => $max, 'ok' => $ok, 'detail' => $detail ];
	}

	/** @param string[] $markers */
	private function contains_any( string $haystack, array $markers ): bool {
		foreach ( $markers as $marker ) {
			if ( str_contains( $haystack, mb_strtolower( $marker ) ) ) {
				return true;
			}
		}

		return false;
	}

	private function authoritative_links( string $content ): int {
		if ( ! preg_match_all( '/href=["\']([^"\']+)["\']/i', $content, $matches ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $matches[1] as $url ) {
			$host = (string) wp_parse_url( (string) $url, PHP_URL_HOST );
			$host = preg_replace( '/^www\./', '', mb_strtolower( $host ) );
			foreach ( self::AUTHORITATIVE as $domain ) {
				if ( $host === $domain || str_ends_with( $host, '.' . $domain ) ) {
					$count++;
					break;
				}
			}
		}

		return $count;
	}
}
