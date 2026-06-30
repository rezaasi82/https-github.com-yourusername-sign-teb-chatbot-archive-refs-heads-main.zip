<?php

namespace STMC_Chat\Admin;

use STMC_Chat\Database\ConversationRepository;
use STMC_Chat\Database\MessageRepository;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Conversation history with a lead filter and a per-conversation transcript —
 * the key feature for proving ROI to the clinic owner.
 */
class ConversationsPage
{
    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $repo = new ConversationRepository();

        // Single-conversation transcript view.
        $view_id = isset($_GET['conversation']) ? absint($_GET['conversation']) : 0;
        if ($view_id > 0) {
            $conversation = $repo->get($view_id);
            $messages     = (new MessageRepository())->for_conversation($view_id);
            include STMC_CHAT_DIR . 'includes/Admin/views/conversation-single.php';
            return;
        }

        $leads_only = ! empty($_GET['leads']);
        $page       = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
        $per_page   = 20;

        $items = $repo->paginate($page, $per_page, ['leads_only' => $leads_only]);
        $total = $repo->count(['leads_only' => $leads_only]);
        $pages = (int) ceil($total / $per_page);

        include STMC_CHAT_DIR . 'includes/Admin/views/conversations-list.php';
    }
}
