<?php
/**
 * Conversation history with a lead filter and a
 * per-conversation transcript (the ROI-proof feature for the clinic owner).
 *
 * Rendered inside the tabbed settings screen.
 *
 * @package Pezhkam
 */

namespace Pezhkam\Admin;

if (! defined('ABSPATH')) {
    exit;
}

class ConversationsPage
{
    public function render_inner(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $repo     = new \Pezhkam\Database\ConversationRepository();
        $branches = (new \Pezhkam\Database\BranchRepository())->all();

        // Single-conversation transcript view.
        $view_id = \Pezhkam\Core\Input::get_int('conversation');
        if ($view_id > 0) {
            $conversation = $repo->get($view_id);
            $messages     = (new \Pezhkam\Database\MessageRepository())->for_conversation($view_id);
            include PZK_DIR . 'includes/admin/views/conversation-single.php';
            return;
        }

        $leads_only = \Pezhkam\Core\Input::get_text('leads') !== '';
        $score      = \Pezhkam\Core\Input::get_key('score');
        $branch     = \Pezhkam\Core\Input::get_int('branch');
        $page       = max(1, \Pezhkam\Core\Input::get_int('paged', 1));
        $per_page   = 20;

        $filters = ['leads_only' => $leads_only, 'score' => $score, 'branch' => $branch];
        $items   = $repo->paginate($page, $per_page, $filters);
        $total   = $repo->count($filters);
        $pages   = (int) ceil($total / $per_page);

        include PZK_DIR . 'includes/admin/views/conversations-list.php';
    }
}
