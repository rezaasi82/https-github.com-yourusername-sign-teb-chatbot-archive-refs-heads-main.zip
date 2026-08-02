<?php

declare(strict_types=1);

namespace Medora\Authority\Medical;

use Medora\Authority\Core\Container;
use Medora\Authority\Core\Options;
use Medora\Authority\License\LicenseTier;
use Medora\Authority\Module\AbstractModule;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Medical Intelligence — YMYL mode.
 *
 * Contributes the clinical vocabulary to entity extraction and adds the
 * review-metadata box that the medical trust scorer reads. Everything here is
 * inert unless the site is explicitly in medical mode: a salon booking site
 * should never be graded on clinical reviewers.
 */
final class MedicalModule extends AbstractModule
{
    public function id(): string
    {
        return 'medical';
    }

    public function title(): string
    {
        return __('Medical Intelligence', 'medora-authority');
    }

    public function description(): string
    {
        return __('Clinical vocabulary, review metadata and YMYL trust signals for health content.', 'medora-authority');
    }

    public function dependencies(): array
    {
        return ['entity'];
    }

    public function requiredTier(): string
    {
        return LicenseTier::PRO;
    }

    public function register(Container $container): void
    {
        $container->singleton(MedicalOntology::class, static fn (): MedicalOntology => new MedicalOntology());
    }

    public function boot(Container $container): void
    {
        if ($container->get(Options::class)->getString('site_mode') !== 'medical') {
            return;
        }

        // The dictionary extractor consumes whatever this filter provides, so
        // the ontology plugs in without either side knowing about the other.
        add_filter('medora_entity_dictionary', static function (array $terms) use ($container): array {
            return array_merge($terms, $container->get(MedicalOntology::class)->terms());
        });

        add_action('add_meta_boxes', [$this, 'registerMetaBox']);
        add_action('save_post', [$this, 'saveMetaBox'], 10, 2);
    }

    public function registerMetaBox(string $postType): void
    {
        if (! is_post_type_viewable($postType)) {
            return;
        }

        add_meta_box(
            'medora-medical-review',
            __('Medora — clinical review', 'medora-authority'),
            [$this, 'renderMetaBox'],
            $postType,
            'side',
            'default'
        );
    }

    public function renderMetaBox(WP_Post $post): void
    {
        wp_nonce_field('medora_medical_review', 'medora_medical_nonce');

        $reviewerId = (int) get_post_meta($post->ID, '_medora_reviewer_id', true);
        $reviewedAt = (string) get_post_meta($post->ID, '_medora_reviewed_at', true);

        echo '<p><label for="medora_reviewer_id"><strong>' . esc_html__('Medical reviewer', 'medora-authority') . '</strong></label>';

        wp_dropdown_users([
            'name'              => 'medora_reviewer_id',
            'id'                => 'medora_reviewer_id',
            'selected'          => $reviewerId,
            'show_option_none'  => __('— none —', 'medora-authority'),
            'option_none_value' => 0,
            'class'             => 'widefat',
        ]);

        echo '</p>';

        printf(
            '<p><label for="medora_reviewed_at"><strong>%s</strong></label>
             <input type="date" id="medora_reviewed_at" name="medora_reviewed_at" value="%s" class="widefat" /></p>
             <p class="description">%s</p>',
            esc_html__('Last reviewed', 'medora-authority'),
            esc_attr($reviewedAt),
            esc_html__('Published in the page schema as lastReviewed. Leave empty rather than entering a date the reviewer did not sign off.', 'medora-authority')
        );
    }

    public function saveMetaBox(int $postId, WP_Post $post): void
    {
        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        $nonce = isset($_POST['medora_medical_nonce'])
            ? sanitize_text_field(wp_unslash((string) $_POST['medora_medical_nonce']))
            : '';

        if (! wp_verify_nonce($nonce, 'medora_medical_review')) {
            return;
        }

        if (! current_user_can('edit_post', $postId)) {
            return;
        }

        $reviewerId = isset($_POST['medora_reviewer_id']) ? absint(wp_unslash($_POST['medora_reviewer_id'])) : 0;

        if ($reviewerId > 0) {
            update_post_meta($postId, '_medora_reviewer_id', $reviewerId);
        } else {
            delete_post_meta($postId, '_medora_reviewer_id');
        }

        $reviewedAt = isset($_POST['medora_reviewed_at'])
            ? sanitize_text_field(wp_unslash((string) $_POST['medora_reviewed_at']))
            : '';

        // Reject anything that is not a real ISO date rather than storing a
        // string that will silently produce invalid schema.
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $reviewedAt) === 1) {
            update_post_meta($postId, '_medora_reviewed_at', $reviewedAt);
        } else {
            delete_post_meta($postId, '_medora_reviewed_at');
        }
    }
}
