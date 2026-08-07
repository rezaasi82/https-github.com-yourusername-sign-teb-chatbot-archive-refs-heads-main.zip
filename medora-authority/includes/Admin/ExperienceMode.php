<?php

declare(strict_types=1);

namespace Medora\Authority\Admin;

use Medora\Authority\Core\Options;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * How much of the platform a given install puts in front of its users.
 *
 * The product has one honest problem: it is a knowledge-graph tool wearing a
 * marketing tool's clothes, and the full surface — entity merging, relation
 * predicates, passage lints, raw component weights — is genuinely too much for
 * the clinic manager who installed it to "fix our Google". Hiding some of that
 * is not condescension, it is the difference between a tool that gets used and
 * one that gets deactivated in week two.
 *
 * **This is presentation, never authorisation.** The REST API is untouched:
 * every endpoint stays registered and capability-gated, so the data behind a
 * hidden screen remains fully reachable by anyone entitled to it. A hidden admin
 * page is simply not registered, so WordPress declines to render that URL — a
 * rendering outcome, not an access decision, since the same user reaches the
 * same data through the API a moment later.
 *
 * The security boundary is `Capabilities`, and duplicating it here would create
 * a second place to get authorisation wrong. Anything that must not be reachable
 * belongs behind a capability check in the controller, not behind a mode.
 */
final class ExperienceMode
{
    public const BEGINNER     = 'beginner';
    public const PROFESSIONAL = 'professional';
    public const AGENCY       = 'agency';
    public const ENTERPRISE   = 'enterprise';

    /**
     * Ordered simplest to fullest. A feature is shown when the current mode
     * ranks at or above the mode that introduces it, so adding a mode in the
     * middle later does not require revisiting every feature.
     */
    private const RANKS = [
        self::BEGINNER     => 0,
        self::PROFESSIONAL => 1,
        self::AGENCY       => 2,
        self::ENTERPRISE   => 3,
    ];

    /**
     * The mode at which each surface first appears.
     *
     * The beginner set is deliberately the whole loop and nothing else: see the
     * score, see what is wrong, fix it, see it move. Everything omitted from it
     * is a thing that answers "why" rather than "what should I do next".
     */
    private const FEATURES = [
        // The working loop.
        'overview'        => self::BEGINNER,
        'content'         => self::BEGINNER,
        'settings'        => self::BEGINNER,
        'crawlers'        => self::BEGINNER,
        'fixes'           => self::BEGINNER,
        'brief'           => self::BEGINNER,

        // Explanation and analysis.
        'entities'        => self::PROFESSIONAL,
        'analytics'       => self::PROFESSIONAL,
        'passages'        => self::PROFESSIONAL,
        'links'           => self::PROFESSIONAL,
        'score_breakdown' => self::PROFESSIONAL,

        // Operating several sites, or one with an editorial team.
        'graph'           => self::AGENCY,
        'modules'         => self::AGENCY,
        'white_label'     => self::AGENCY,
        'raw_metrics'     => self::AGENCY,

        // Compliance and operations surfaces.
        // No `api_keys`: the public API is nonce- and rate-limited rather than
        // key-based, so a flag for it advertised a system that does not exist.
        'audit_log'       => self::ENTERPRISE,
        'queue_health'    => self::ENTERPRISE,
    ];

    public function __construct(private readonly Options $options)
    {
    }

    public function current(): string
    {
        $mode = $this->options->getString('experience_mode', self::BEGINNER);

        return isset(self::RANKS[$mode]) ? $mode : self::BEGINNER;
    }

    /**
     * Whether a surface is shown in the current mode.
     *
     * An unknown feature is shown. A third-party screen that never registered
     * itself here should appear rather than vanish — failing open is right for
     * a presentational filter, and would be wrong for a capability.
     */
    public function shows(string $feature): bool
    {
        $required = self::FEATURES[$feature] ?? self::BEGINNER;

        $shown = self::RANKS[$this->current()] >= self::RANKS[$required];

        /**
         * Filter whether a surface appears in the current experience mode.
         *
         * Presentation only — returning true here does not grant access to
         * anything the user's capabilities do not already allow.
         *
         * @param bool   $shown
         * @param string $feature
         * @param string $mode Current experience mode.
         */
        return (bool) apply_filters('medora_experience_shows', $shown, $feature, $this->current());
    }

    /**
     * Every feature flag, for the dashboard bootstrap.
     *
     * Sent whole rather than queried one at a time so the React app can decide
     * its navigation without a round trip, and so the set stays in one place
     * instead of being re-derived on both sides of the wire.
     *
     * @return array<string, bool>
     */
    public function all(): array
    {
        $features = [];

        foreach (array_keys(self::FEATURES) as $feature) {
            $features[$feature] = $this->shows($feature);
        }

        return $features;
    }

    /**
     * @return list<array{id: string, label: string, description: string}>
     */
    public static function choices(): array
    {
        return [
            [
                'id'          => self::BEGINNER,
                'label'       => __('Beginner', 'medora-authority'),
                'description' => __('The score, what is wrong with each page, and how to fix it. Nothing else.', 'medora-authority'),
            ],
            [
                'id'          => self::PROFESSIONAL,
                'label'       => __('Professional', 'medora-authority'),
                'description' => __('Adds the entity explorer, referral analytics, passage-level detail and the full score breakdown.', 'medora-authority'),
            ],
            [
                'id'          => self::AGENCY,
                'label'       => __('Agency', 'medora-authority'),
                'description' => __('Adds the knowledge graph, module control, white-labelling and raw metrics.', 'medora-authority'),
            ],
            [
                'id'          => self::ENTERPRISE,
                'label'       => __('Enterprise', 'medora-authority'),
                'description' => __('Adds the audit log and background queue health.', 'medora-authority'),
            ],
        ];
    }
}
