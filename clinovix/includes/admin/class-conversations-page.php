<?php
/**
 * Conversation history with a lead filter and a
 * per-conversation transcript (the ROI-proof feature for the clinic owner).
 *
 * Rendered inside the tabbed settings screen.
 *
 * @package Clinovix
 */

namespace Clinovix\Admin;

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

        $repo     = new \Clinovix\Database\ConversationRepository();
        $branches = (new \Clinovix\Database\BranchRepository())->all();

        // Single-conversation transcript view.
        $view_id = \Clinovix\Core\Input::get_int('conversation');
        if ($view_id > 0) {
            $conversation = $repo->get($view_id);
            $messages     = (new \Clinovix\Database\MessageRepository())->for_conversation($view_id);
            include CLX_DIR . 'includes/admin/views/conversation-single.php';
            return;
        }

        $leads_only = \Clinovix\Core\Input::get_text('leads') !== '';
        $score      = \Clinovix\Core\Input::get_key('score');
        $branch     = \Clinovix\Core\Input::get_int('branch');
        $page       = max(1, \Clinovix\Core\Input::get_int('paged', 1));
        $per_page   = 20;

        $filters = ['leads_only' => $leads_only, 'score' => $score, 'branch' => $branch];
        $items   = $repo->paginate($page, $per_page, $filters);
        $total   = $repo->count($filters);
        $pages   = (int) ceil($total / $per_page);

        include CLX_DIR . 'includes/admin/views/conversations-list.php';
    }
}
