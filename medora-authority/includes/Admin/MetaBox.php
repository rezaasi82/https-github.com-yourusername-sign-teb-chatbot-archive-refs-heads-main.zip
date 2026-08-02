<?php

declare(strict_types=1);

namespace Medora\Authority\Admin;

use Medora\Authority\Core\Container;
use Medora\Authority\Score\AnalysisRepository;
use Medora\Authority\Score\AuthorityScoreCalculator;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The per-post authority panel.
 *
 * Rendered server-side rather than as another React island: it must be useful
 * the moment the editor opens, and it is read-mostly. The one interactive
 * control (re-analyse) is progressive — it posts to the REST endpoint and, if
 * JavaScript is unavailable, the panel still shows the last known score.
 */
final class MetaBox
{
    public function __construct(private readonly Container $container)
    {
    }

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'add']);
        add_action('save_post', [$this, 'save'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(string $hookSuffix): void
    {
        if (! in_array($hookSuffix, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $this->container->get(AssetManager::class)->enqueueEditor();
    }

    public function add(string $postType): void
    {
        if (! is_post_type_viewable($postType)) {
            return;
        }

        add_meta_box(
            'medora-authority',
            __('Medora — AI Authority', 'medora-authority'),
            [$this, 'render'],
            $postType,
            'side',
            'high'
        );
    }

    public function render(WP_Post $post): void
    {
        wp_nonce_field('medora_post_meta', 'medora_post_nonce');

        $analysis = $this->container->get(AnalysisRepository::class)->find('post', $post->ID);

        echo '<div class="medora-metabox" data-post-id="' . esc_attr((string) $post->ID) . '">';

        if ($analysis === null) {
            echo '<p>' . esc_html__('Not analysed yet. Publish or update to queue an analysis.', 'medora-authority') . '</p>';
        } else {
            $score = (float) $analysis['result']['overall'];
            $grade = AuthorityScoreCalculator::gradeFor($score);

            printf(
                '<p class="medora-score medora-grade-%1$s"><strong>%2$s</strong> <span>/ 100 — %3$s</span></p>',
                esc_attr(strtolower($grade)),
                esc_html(number_format_i18n($score, 1)),
                esc_html($grade)
            );

            $deductions = (array) ($analysis['result']['deductions'] ?? []);

            if ($deductions === []) {
                echo '<p>' . esc_html__('No issues found.', 'medora-authority') . '</p>';
            } else {
                echo '<ul class="medora-deductions">';

                foreach (array_slice($deductions, 0, 5) as $deduction) {
                    printf(
                        '<li class="medora-severity-%1$s"><strong>%2$s</strong><br><span>%3$s</span></li>',
                        esc_attr((string) ($deduction['severity'] ?? 'medium')),
                        esc_html((string) ($deduction['label'] ?? '')),
                        esc_html((string) ($deduction['recommendation'] ?? ''))
                    );
                }

                echo '</ul>';
            }
        }

        printf(
            '<p><button type="button" class="button medora-reanalyze">%s</button></p>',
            esc_html__('Re-analyse now', 'medora-authority')
        );

        echo '<hr />';

        $this->renderFields($post);

        echo '</div>';
    }

    private function renderFields(WP_Post $post): void
    {
        $answer  = (string) get_post_meta($post->ID, '_medora_canonical_answer', true);
        $noindex = (string) get_post_meta($post->ID, '_medora_noindex', true) === '1';
        $exclude = (string) get_post_meta($post->ID, '_medora_exclude_llms', true) === '1';

        printf(
            '<p><label for="medora_canonical_answer"><strong>%s</strong></label>
             <textarea id="medora_canonical_answer" name="medora_canonical_answer" rows="4" class="widefat">%s</textarea>
             <span class="description">%s</span></p>',
            esc_html__('Canonical answer', 'medora-authority'),
            esc_textarea($answer),
            esc_html__('25–60 words that answer this page\'s question on their own. This is what assistants quote.', 'medora-authority')
        );

        printf(
            '<p><label><input type="checkbox" name="medora_noindex" value="1" %s /> %s</label></p>',
            checked($noindex, true, false),
            esc_html__('Exclude from AI indexing', 'medora-authority')
        );

        printf(
            '<p><label><input type="checkbox" name="medora_exclude_llms" value="1" %s /> %s</label></p>',
            checked($exclude, true, false),
            esc_html__('Exclude from llms.txt', 'medora-authority')
        );
    }

    public function save(int $postId, WP_Post $post): void
    {
        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        $nonce = isset($_POST['medora_post_nonce'])
            ? sanitize_text_field(wp_unslash((string) $_POST['medora_post_nonce']))
            : '';

        if (! wp_verify_nonce($nonce, 'medora_post_meta') || ! current_user_can('edit_post', $postId)) {
            return;
        }

        $answer = isset($_POST['medora_canonical_answer'])
            ? sanitize_textarea_field(wp_unslash((string) $_POST['medora_canonical_answer']))
            : '';

        if ($answer !== '') {
            update_post_meta($postId, '_medora_canonical_answer', $answer);
        } else {
            delete_post_meta($postId, '_medora_canonical_answer');
        }

        foreach (['medora_noindex' => '_medora_noindex', 'medora_exclude_llms' => '_medora_exclude_llms'] as $field => $metaKey) {
            if (isset($_POST[$field])) {
                update_post_meta($postId, $metaKey, '1');
            } else {
                delete_post_meta($postId, $metaKey);
            }
        }
    }
}
