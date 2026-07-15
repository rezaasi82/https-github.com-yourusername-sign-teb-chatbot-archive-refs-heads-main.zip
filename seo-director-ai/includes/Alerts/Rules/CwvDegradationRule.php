<?php
/**
 * CWV rule: latest home-page audit reports "poor" Core Web Vitals.
 *
 * @package SEODirector
 */

namespace SEODirector\Alerts\Rules;

defined( 'ABSPATH' ) || exit;

use SEODirector\Alerts\AlertRuleInterface;
use SEODirector\Data\Repository\PsiAuditsRepository;

final class CwvDegradationRule implements AlertRuleInterface {

	public function __construct( private readonly PsiAuditsRepository $audits ) {}

	public function slug(): string {
		return 'cwv';
	}

	public function evaluate(): array {
		$alerts = array();

		foreach ( $this->audits->latest_for_page( '/', 2 ) as $audit ) {
			if ( 'poor' !== ( $audit['cwv_status'] ?? '' ) ) {
				continue;
			}
			$strategy = (string) $audit['strategy'];
			$alerts[] = array(
				'severity'     => 'high',
				'message'      => sprintf(
					/* translators: %s: device strategy (mobile/desktop) */
					__( 'Core Web Vitals are failing on %s for the home page.', 'seo-director-ai' ),
					$strategy
				),
				'fingerprint'  => 'home_' . $strategy . '_' . gmdate( 'oW' ),
				'entity_label' => '/',
				'data'         => array(
					'strategy' => $strategy,
					'lcp_ms'   => $audit['lcp_ms'],
					'cls'      => $audit['cls'],
					'inp_ms'   => $audit['inp_ms'],
				),
			);
		}

		return $alerts;
	}
}
