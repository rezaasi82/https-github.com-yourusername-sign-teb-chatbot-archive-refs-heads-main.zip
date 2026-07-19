<?php
/**
 * Versioned prompt templates + output schemas, with per-language system framing.
 * Bumping a prompt version invalidates its cached insights (evidence_hash includes it).
 *
 * @package SEODirector
 */

namespace SEODirector\Ai;

defined( 'ABSPATH' ) || exit;

final class PromptLibrary {

	/** Bump when a template's wording or schema changes materially. */
	public const VERSION = '1.0.0';

	/**
	 * Build an envelope for a given insight type.
	 *
	 * @param 'explain_decline'|'explain_growth'|'summary_weekly'|'root_cause' $type
	 * @param array<string, mixed>                                            $evidence Structured evidence packet.
	 * @param string                                                          $language Target language code.
	 */
	public function build( string $type, array $evidence, string $language ): PromptEnvelope {
		$system = $this->system_prompt( $language );

		return match ( $type ) {
			'explain_growth'  => $this->explanation_envelope( 'explain_growth', $system, $evidence, $language, true ),
			'summary_weekly'  => $this->weekly_summary_envelope( $system, $evidence, $language ),
			'root_cause'      => $this->root_cause_envelope( $system, $evidence, $language ),
			default           => $this->explanation_envelope( 'explain_decline', $system, $evidence, $language, false ),
		};
	}

	private function system_prompt( string $language ): string {
		$lang_name = $this->language_name( $language );
		return 'You are a senior SEO analyst embedded in a WordPress plugin. '
			. 'You are given DETERMINISTIC metrics computed from Google Search Console data — treat every number as ground truth and never invent or restate figures that are not in the evidence. '
			. 'Explain causes and recommend concrete, prioritized actions a site owner can take. '
			. 'Be specific and concise. '
			. "Write ALL human-readable text fields in {$lang_name}. "
			. 'Respond with ONLY the requested JSON object.';
	}

	/**
	 * Growth/decline explanation for a single entity or the whole site.
	 */
	private function explanation_envelope( string $id, string $system, array $evidence, string $language, bool $growth ): PromptEnvelope {
		$direction = $growth ? 'improvement' : 'decline';
		$user      = "Analyze this {$direction} in organic search performance. "
			. 'Identify the most likely causes ranked by confidence, and give an ordered set of recommended actions.';

		$schema = array(
			'type'       => 'object',
			'required'   => array( 'headline', 'explanation', 'causes', 'actions' ),
			'properties' => array(
				'headline'    => array( 'type' => 'string', 'maxLength' => 160 ),
				'explanation' => array( 'type' => 'string', 'maxLength' => 900 ),
				'causes'      => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'required'   => array( 'cause', 'confidence' ),
						'properties' => array(
							'cause'      => array( 'type' => 'string', 'maxLength' => 300 ),
							'confidence' => array( 'type' => 'string', 'enum' => array( 'high', 'medium', 'low' ) ),
						),
					),
				),
				'actions'     => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'required'   => array( 'title', 'impact', 'effort' ),
						'properties' => array(
							'title'  => array( 'type' => 'string', 'maxLength' => 200 ),
							'impact' => array( 'type' => 'string', 'enum' => array( 'high', 'medium', 'low' ) ),
							'effort' => array( 'type' => 'string', 'enum' => array( 'high', 'medium', 'low' ) ),
						),
					),
				),
			),
		);

		return new PromptEnvelope( $id, self::VERSION, $system, $user, $evidence, $schema, $language, 1500 );
	}

	private function weekly_summary_envelope( string $system, array $evidence, string $language ): PromptEnvelope {
		$user = 'Write a concise weekly executive summary of organic search performance for a non-technical stakeholder. '
			. 'Cover the headline trend, one or two notable wins, one or two risks, and the single most important focus for next week.';

		$schema = array(
			'type'       => 'object',
			'required'   => array( 'summary', 'wins', 'risks', 'focus' ),
			'properties' => array(
				'summary' => array( 'type' => 'string', 'maxLength' => 700 ),
				'wins'    => array( 'type' => 'array', 'items' => array( 'type' => 'string', 'maxLength' => 200 ) ),
				'risks'   => array( 'type' => 'array', 'items' => array( 'type' => 'string', 'maxLength' => 200 ) ),
				'focus'   => array( 'type' => 'string', 'maxLength' => 300 ),
			),
		);

		return new PromptEnvelope( 'summary_weekly', self::VERSION, $system, $user, $evidence, $schema, $language, 1200 );
	}

	private function root_cause_envelope( string $system, array $evidence, string $language ): PromptEnvelope {
		$user = 'You are given candidate causes each with a supporting evidence packet. '
			. 'Rank them by how well the evidence supports them, assign a confidence, and note what additional signal would confirm each.';

		$schema = array(
			'type'       => 'object',
			'required'   => array( 'ranked_causes' ),
			'properties' => array(
				'ranked_causes' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'required'   => array( 'cause', 'confidence', 'reasoning' ),
						'properties' => array(
							'cause'         => array( 'type' => 'string', 'maxLength' => 200 ),
							'confidence'    => array( 'type' => 'string', 'enum' => array( 'high', 'medium', 'low' ) ),
							'reasoning'     => array( 'type' => 'string', 'maxLength' => 400 ),
							'confirming_signal' => array( 'type' => 'string', 'maxLength' => 200 ),
						),
					),
				),
			),
		);

		return new PromptEnvelope( 'root_cause', self::VERSION, $system, $user, $evidence, $schema, $language, 1500 );
	}

	private function language_name( string $code ): string {
		return match ( substr( $code, 0, 2 ) ) {
			'fa'    => 'Persian (فارسی)',
			'ar'    => 'Arabic (العربية)',
			'es'    => 'Spanish',
			'fr'    => 'French',
			'de'    => 'German',
			default => 'English',
		};
	}
}
