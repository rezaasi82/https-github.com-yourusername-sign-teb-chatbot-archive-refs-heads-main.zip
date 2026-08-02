<?php

declare(strict_types=1);

namespace Medora\Authority\Eeat;

use Medora\Authority\Core\Capabilities;
use Medora\Authority\Core\Container;
use Medora\Authority\Module\AbstractModule;
use WP_User;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * E-E-A-T Engine — author credentials, verifiable profiles and trust scoring.
 *
 * Adds its fields to the native user-profile screen rather than a separate
 * page, because a credential that lives somewhere an editor never visits does
 * not get filled in.
 */
final class EeatModule extends AbstractModule
{
    public function id(): string
    {
        return 'eeat';
    }

    public function title(): string
    {
        return __('E-E-A-T Engine', 'medora-authority');
    }

    public function description(): string
    {
        return __('Capture author credentials and verifiable profiles, and score the trust they carry.', 'medora-authority');
    }

    public function register(Container $container): void
    {
        $container->singleton(TrustScorer::class, static fn (): TrustScorer => new TrustScorer());
    }

    public function boot(Container $container): void
    {
        add_action('show_user_profile', [$this, 'renderFields']);
        add_action('edit_user_profile', [$this, 'renderFields']);

        add_action('personal_options_update', [$this, 'saveFields']);
        add_action('edit_user_profile_update', [$this, 'saveFields']);
    }

    public function renderFields(WP_User $user): void
    {
        $canEdit = current_user_can('edit_user', $user->ID);

        if (! $canEdit) {
            return;
        }

        wp_nonce_field('medora_author_profile', 'medora_author_nonce');

        echo '<h2>' . esc_html__('Medora — author authority', 'medora-authority') . '</h2>';
        echo '<p class="description">' . esc_html__(
            'These fields feed the article schema, the knowledge graph and the E-E-A-T score. Leave a field empty rather than guessing.',
            'medora-authority'
        ) . '</p>';
        echo '<table class="form-table" role="presentation"><tbody>';

        foreach (AuthorProfile::FIELDS as $key => $label) {
            $value = (string) get_user_meta($user->ID, $key, true);
            $id    = esc_attr($key);

            printf(
                '<tr><th><label for="%1$s">%2$s</label></th><td><input type="text" name="%1$s" id="%1$s" value="%3$s" class="regular-text" /></td></tr>',
                $id,
                esc_html($this->translateLabel($label)),
                esc_attr($value)
            );
        }

        echo '</tbody></table>';
    }

    public function saveFields(int $userId): void
    {
        if (! current_user_can('edit_user', $userId)) {
            return;
        }

        $nonce = isset($_POST['medora_author_nonce'])
            ? sanitize_text_field(wp_unslash((string) $_POST['medora_author_nonce']))
            : '';

        if (! wp_verify_nonce($nonce, 'medora_author_profile')) {
            return;
        }

        foreach (array_keys(AuthorProfile::FIELDS) as $key) {
            if (! isset($_POST[$key])) {
                continue;
            }

            $raw = wp_unslash((string) $_POST[$key]);

            // URL fields get URL sanitisation; everything else is plain text.
            $value = str_ends_with($key, '_url') || in_array($key, ['_medora_researchgate', '_medora_linkedin'], true)
                ? esc_url_raw($raw)
                : sanitize_text_field($raw);

            update_user_meta($userId, $key, $value);
        }

        do_action('medora_author_profile_updated', $userId);
    }

    /**
     * The field labels are declared as plain strings in a constant (constants
     * cannot hold function calls), so translation happens here.
     */
    private function translateLabel(string $label): string
    {
        return match ($label) {
            'Credentials'                  => __('Credentials', 'medora-authority'),
            'Job title'                    => __('Job title', 'medora-authority'),
            'Affiliation'                  => __('Affiliation', 'medora-authority'),
            'Professional licence number'  => __('Professional licence number', 'medora-authority'),
            'ORCID'                        => __('ORCID', 'medora-authority'),
            'Google Scholar URL'           => __('Google Scholar URL', 'medora-authority'),
            'ResearchGate URL'             => __('ResearchGate URL', 'medora-authority'),
            'LinkedIn URL'                 => __('LinkedIn URL', 'medora-authority'),
            'Years of practice'            => __('Years of practice', 'medora-authority'),
            'Awards'                       => __('Awards', 'medora-authority'),
            'Education'                    => __('Education', 'medora-authority'),
            default                        => $label,
        };
    }
}
