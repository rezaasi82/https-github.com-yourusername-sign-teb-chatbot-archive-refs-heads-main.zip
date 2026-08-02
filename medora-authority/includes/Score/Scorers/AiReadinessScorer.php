<?php

declare(strict_types=1);

namespace Medora\Authority\Score\Scorers;

use Medora\Authority\Core\Container;
use Medora\Authority\Core\Options;
use Medora\Authority\Crawler\CrawlerPolicy;
use Medora\Authority\Crawler\CrawlerRegistry;
use Medora\Authority\Schema\SchemaGraph;
use Medora\Authority\Schema\SchemaModule;
use Medora\Authority\Schema\SchemaValidator;
use Medora\Authority\Score\Deduction;
use Medora\Authority\Score\ScoreComponent;
use Medora\Authority\Score\ScorerInterface;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Can an AI system reach, parse and trust this page at all?
 *
 * This is the gating dimension. Everything else on the scorecard is wasted if
 * the page is noindexed, blocked to the crawlers that matter, or ships broken
 * structured data.
 */
final class AiReadinessScorer implements ScorerInterface
{
    public function __construct(
        private readonly Container $container,
        private readonly Options $options,
    ) {
    }

    public function id(): string
    {
        return 'ai_readiness';
    }

    public function label(): string
    {
        return __('AI Readiness', 'medora-authority');
    }

    public function weight(): float
    {
        return 0.20;
    }

    public function appliesTo(WP_Post $post): bool
    {
        return true;
    }

    public function score(WP_Post $post): ScoreComponent
    {
        $score      = 100.0;
        $deductions = [];
        $metrics    = [];

        // --- Indexability -------------------------------------------------
        if ((string) get_post_meta($post->ID, '_medora_noindex', true) === '1' || ! is_post_type_viewable($post->post_type)) {
            $score -= 40;
            $deductions[] = new Deduction(
                'noindex',
                __('Page is excluded from indexing', 'medora-authority'),
                40,
                __('Remove the noindex directive, or accept that no AI system will cite this page.', 'medora-authority'),
                Deduction::SEVERITY_CRITICAL
            );
        }

        if ((int) get_option('blog_public') === 0) {
            $score -= 30;
            $deductions[] = new Deduction(
                'site_private',
                __('Site discourages search engines', 'medora-authority'),
                30,
                __('Turn off "Discourage search engines" in Settings → Reading.', 'medora-authority'),
                Deduction::SEVERITY_CRITICAL,
                'settings'
            );
        }

        // --- Crawler access ------------------------------------------------
        $policy  = $this->container->get(CrawlerPolicy::class);
        $blocked = [];

        foreach (CrawlerRegistry::all() as $slug => $crawler) {
            if ($crawler['recommended'] && $policy->decisionFor($slug) === CrawlerPolicy::BLOCK) {
                $blocked[] = $crawler['name'];
            }
        }

        $metrics['blocked_crawlers'] = $blocked;

        if ($blocked !== []) {
            // Blocking training crawlers is a legitimate choice; blocking the
            // retrieval and search ones is what actually costs citations, so
            // the penalty scales rather than being flat.
            $penalty = min(25.0, count($blocked) * 3.0);
            $score  -= $penalty;

            $deductions[] = new Deduction(
                'crawlers_blocked',
                sprintf(
                    /* translators: %d: number of crawlers. */
                    _n('%d recommended AI crawler is blocked', '%d recommended AI crawlers are blocked', count($blocked), 'medora-authority'),
                    count($blocked)
                ),
                $penalty,
                sprintf(
                    /* translators: %s: comma-separated crawler names. */
                    __('Currently blocked: %s. Review the policy if you want to appear in AI answers.', 'medora-authority'),
                    implode(', ', array_slice($blocked, 0, 5))
                ),
                Deduction::SEVERITY_HIGH,
                'settings'
            );
        }

        // --- Structured data -----------------------------------------------
        $schemaModule = $this->container->get(SchemaModule::class);
        $context      = $schemaModule->contextFor($this->container, $post);
        $document     = $this->container->get(SchemaGraph::class)->build($context);
        $validation   = $this->container->get(SchemaValidator::class)->validate($document);

        $metrics['schema_nodes']  = $validation['node_count'];
        $metrics['schema_errors'] = count(array_filter(
            $validation['errors'],
            static fn (array $e): bool => $e['severity'] === SchemaValidator::SEVERITY_ERROR
        ));

        if (! $this->options->getBool('schema_enabled', true)) {
            $score -= 20;
            $deductions[] = new Deduction(
                'schema_disabled',
                __('Schema output is disabled', 'medora-authority'),
                20,
                __('Enable Schema Intelligence so pages ship a JSON-LD graph.', 'medora-authority'),
                Deduction::SEVERITY_HIGH,
                'settings'
            );
        } elseif ($metrics['schema_errors'] > 0) {
            $penalty = min(20.0, $metrics['schema_errors'] * 5.0);
            $score  -= $penalty;

            $deductions[] = new Deduction(
                'schema_invalid',
                __('Structured data has validation errors', 'medora-authority'),
                $penalty,
                __('Fix the missing required properties listed in the schema panel.', 'medora-authority'),
                Deduction::SEVERITY_HIGH
            );
        }

        // --- Machine-readable site documents --------------------------------
        if (! $this->options->getBool('llms_txt_enabled', true)) {
            $score -= 8;
            $deductions[] = new Deduction(
                'no_llms_txt',
                __('llms.txt is not published', 'medora-authority'),
                8,
                __('Enable the llms.txt engine so models get a curated map of the site.', 'medora-authority'),
                Deduction::SEVERITY_MEDIUM,
                'settings'
            );
        }

        if (! $this->options->getBool('sitemap_enabled', true)) {
            $score -= 7;
            $deductions[] = new Deduction(
                'no_sitemap',
                __('AI sitemap is not published', 'medora-authority'),
                7,
                __('Enable the AI Sitemap Engine.', 'medora-authority'),
                Deduction::SEVERITY_MEDIUM,
                'settings'
            );
        }

        return new ScoreComponent(
            $this->id(),
            $this->label(),
            max(0.0, $score),
            $this->weight(),
            $deductions,
            $metrics
        );
    }
}
