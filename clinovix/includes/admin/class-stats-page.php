<?php
/**
 * Dashboard: conversation volume, CTA conversion rate, and
 * the most frequent opening questions (a Content-Gap signal for SEO).
 *
 * Rendered inside the tabbed settings screen.
 *
 * @package Clinovix
 */

namespace Clinovix\Admin;

if (! defined('ABSPATH')) {
    exit;
}

class StatsPage
{
    public function render_inner(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $repo     = new \Clinovix\Database\ConversationRepository();
        $events   = new \Clinovix\Database\EventRepository();
        $settings = new \Clinovix\Core\Settings();
        $stats    = $repo->stats(30);
        $clicks   = $events->counts(30);
        $daily    = $repo->daily(14);
        $daily_c  = $events->daily(14);
        $top      = $this->top_questions();
        $services = array_map(
            static fn($s) => (string) $s['name'],
            (new \Clinovix\Ai\SystemPromptBuilder($settings))->services()
        );
        $demand  = $repo->service_demand($services, 30);

        include CLX_DIR . 'includes/admin/views/dashboard.php';
    }

    /**
     * Most frequent first user messages over the last 30 days.
     *
     * @return array<int,object>
     */
    private function top_questions(int $limit = 10): array
    {
        global $wpdb;
        $messages = \Clinovix\Database\Schema::messages_table();
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
