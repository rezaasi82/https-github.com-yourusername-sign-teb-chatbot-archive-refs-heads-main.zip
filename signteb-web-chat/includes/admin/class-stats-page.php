<?php
/**
 * SWC_Stats_Page — dashboard: conversation volume, CTA conversion rate, and
 * the most frequent opening questions (a Content-Gap signal for SEO).
 *
 * Rendered inside the tabbed settings screen.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Stats_Page
{
    public function render_inner(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $repo    = new SWC_Conversation_Repository();
        $stats   = $repo->stats(30);
        $top     = $this->top_questions();
        $license = new SWC_License_Manager();

        include SWC_DIR . 'includes/admin/views/dashboard.php';
    }

    /**
     * Most frequent first user messages over the last 30 days.
     *
     * @return array<int,object>
     */
    private function top_questions(int $limit = 10): array
    {
        global $wpdb;
        $messages = SWC_Schema::messages_table();
        $since    = gmdate('Y-m-d H:i:s', time() - (30 * DAY_IN_SECONDS));

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT LEFT(content, 80) AS q, COUNT(*) AS c
                 FROM {$messages}
                 WHERE role = 'user' AND created_at >= %s
                 GROUP BY q ORDER BY c DESC LIMIT %d",
                $since,
                $limit
            )
        ) ?: [];
    }
}
