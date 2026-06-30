<?php

namespace STMC_Chat\Admin;

use STMC_Chat\Database\ConversationRepository;
use STMC_Chat\Database\Schema;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Dashboard: conversation volume, CTA conversion rate, and the most frequent
 * opening questions (a Content-Gap signal that can feed SEO strategy).
 */
class StatsPage
{
    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $repo  = new ConversationRepository();
        $stats = $repo->stats(30);
        $top   = $this->top_questions();

        include STMC_CHAT_DIR . 'includes/Admin/views/dashboard.php';
    }

    /**
     * Most frequent first user messages over the last 30 days.
     *
     * @return array<int,object>
     */
    private function top_questions(int $limit = 10): array
    {
        global $wpdb;
        $messages = Schema::messages_table();
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
