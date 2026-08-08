<?php
/**
 * Fallback archive template for /videos/ and /videos/{topic}/.
 *
 * Copy this file into your theme as `stvh-video-archive.php` to customise it.
 *
 * @package SignTeb\VideoHub
 */

if (! defined('ABSPATH')) {
    exit;
}

use SignTeb\VideoHub\Core\PostType;
use SignTeb\VideoHub\Front\Renderer;

get_header();

$stvh_term     = is_tax(PostType::TAXONOMY) ? get_queried_object() : null;
$stvh_title    = $stvh_term instanceof WP_Term
    ? sprintf(
        /* translators: %s: topic name */
        __('ویدئوهای %s', 'signteb-video-hub'),
        $stvh_term->name
    )
    : __('ویدئوهای آموزشی', 'signteb-video-hub');
$stvh_subtitle = $stvh_term instanceof WP_Term ? wp_strip_all_tags((string) $stvh_term->description) : '';
?>
<main id="primary" class="stvh-archive site-main">
    <div class="stvh-archive__inner">
        <?php
        // Escaping happens per field inside Renderer.
        echo (new Renderer())->hub([ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            'title'    => $stvh_title,
            'subtitle' => $stvh_subtitle,
            'topic'    => $stvh_term instanceof WP_Term ? $stvh_term->slug : '',
            'layout'   => 'grid',
        ]);
        ?>
    </div>
</main>
<?php
get_footer();
