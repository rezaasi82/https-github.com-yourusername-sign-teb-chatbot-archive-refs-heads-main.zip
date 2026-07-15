<?php
/**
 * Versioned prompt templates and JSON schemas, with per-language system
 * instructions (English, Persian, Arabic). Everything the AI layer needs to
 * build a PromptEnvelope for a given insight type lives here, so prompts are
 * auditable and versioned independently of the calling code.
 *
 * @package SEODirector
 */

namespace SEODirector\Ai;

defined( 'ABSPATH' ) || exit;

final class PromptLibrary {

	private const LANG_DIRECTIVE = [
		'en' => 'Respond in clear, professional English.',
		'fa' => 'به فارسی روان و حرفه‌ای پاسخ بده.',
		'ar' => 'أجب باللغة العربية الواضحة والمهنية.',
	];

	/**
	 * Build an envelope for one insight type.
	 *
	 * @param 'root_cause'|'growth'|'decline'|'summary_weekly'|'summary_monthly'|'content_meta'|'content_gap' $type
	 * @param array<string, mixed>                                              $evidence
	 * @param array<string, mixed>                                              $site_context niche, goals…
	 */
	public function build( string $type, array $evidence, array $site_context, string $lang = 'en' ): PromptEnvelope {
		$lang      = isset( self::LANG_DIRECTIVE[ $lang ] ) ? $lang : 'en';
		$directive = self::LANG_DIRECTIVE[ $lang ];
		$context   = $this->context_line( $site_context );

		[ $version, $role, $schema, $max_tokens ] = $this->spec( $type );

		$system = "You are an expert SEO director analyzing a website. {$role} "
			. 'You are given deterministic evidence computed from Google Search Console and analytics data. '
			. 'Never invent numbers — use only the figures in the evidence. Base every claim on that evidence. '
			. "{$context} {$directive} Output only valid JSON matching the requested schema.";

		return new PromptEnvelope( $version, $system, $evidence, $schema, $lang, $max_tokens );
	}

	/**
	 * @return array{0: string, 1: string, 2: array<string, mixed>, 3: int}
	 */
	private function spec( string $type ): array {
		return match ( $type ) {
			'root_cause' => [
				'root_cause.v1',
				'Explain why a page or keyword declined and rank the likely causes.',
				[
					'type'       => 'object',
					'required'   => [ 'summary', 'causes' ],
					'properties' => [
						'summary' => [ 'type' => 'string' ],
						'causes'  => [
							'type'  => 'array',
							'items' => [
								'type'       => 'object',
								'required'   => [ 'cause', 'confidence', 'fix' ],
								'properties' => [
									'cause'      => [ 'type' => 'string' ],
									'confidence' => [ 'type' => 'integer' ],
									'fix'        => [ 'type' => 'string' ],
								],
							],
						],
					],
				],
				1200,
			],
			'content_meta' => [
				'content_meta.v1',
				'Write an optimized SEO meta title (≤60 chars) and meta description (≤155 chars) for the page, using its top queries naturally. Do not keyword-stuff.',
				[
					'type'       => 'object',
					'required'   => [ 'title', 'description' ],
					'properties' => [
						'title'       => [ 'type' => 'string' ],
						'description' => [ 'type' => 'string' ],
					],
				],
				400,
			],
			'content_gap' => [
				'content_gap.v1',
				'Given queries the site earns impressions for but has no strong dedicated page, propose content topics that would capture them. Group related queries.',
				[
					'type'       => 'object',
					'required'   => [ 'topics' ],
					'properties' => [
						'topics' => [
							'type'  => 'array',
							'items' => [
								'type'       => 'object',
								'required'   => [ 'title', 'rationale' ],
								'properties' => [
									'title'     => [ 'type' => 'string' ],
									'rationale' => [ 'type' => 'string' ],
									'format'    => [ 'type' => 'string' ],
								],
							],
						],
					],
				],
				900,
			],
			'growth', 'decline' => [
				$type . '.v1',
				'growth' === $type
					? 'Explain why an entity grew and recommend how to protect and extend the gain.'
					: 'Explain why an entity declined and recommend a recovery action.',
				[
					'type'       => 'object',
					'required'   => [ 'explanation', 'recommendation' ],
					'properties' => [
						'explanation'    => [ 'type' => 'string' ],
						'recommendation' => [ 'type' => 'string' ],
					],
				],
				600,
			],
			default => [
				$type . '.v1',
				'Write a concise executive summary (max 3 sentences) of the period\'s SEO performance.',
				[
					'type'       => 'object',
					'required'   => [ 'summary' ],
					'properties' => [ 'summary' => [ 'type' => 'string' ] ],
				],
				400,
			],
		};
	}

	/**
	 * @param array<string, mixed> $site_context
	 */
	private function context_line( array $site_context ): string {
		$parts = [];
		if ( ! empty( $site_context['niche'] ) ) {
			$parts[] = 'Site niche: ' . sanitize_text_field( (string) $site_context['niche'] ) . '.';
		}
		if ( ! empty( $site_context['goals'] ) ) {
			$parts[] = 'Goals: ' . sanitize_text_field( (string) $site_context['goals'] ) . '.';
		}

		return implode( ' ', $parts );
	}
}
