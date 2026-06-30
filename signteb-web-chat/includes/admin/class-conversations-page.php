<?php
/**
 * SWC_Conversations_Page — conversation history with a lead filter and a
 * per-conversation transcript (the ROI-proof feature for the clinic owner).
 *
 * Rendered inside the tabbed settings screen.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Conversations_Page
{
    public function render_inner(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $repo = new SWC_Conversation_Repository();

        // Single-conversation transcript view.
        $view_id = isset($_GET['conversation']) ? absint($_GET['conversation']) : 0;
        if ($view_id > 0) {
            $conversation = $repo->get($view_id);
            $messages     = (new SWC_Message_Repository())->for_conversation($view_id);
            include SWC_DIR . 'includes/admin/views/conversation-single.php';
            return;
        }

        $leads_only = ! empty($_GET['leads']);
        $page       = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
        $per_page   = 20;

        $items = $repo->paginate($page, $per_page, ['leads_only' => $leads_only]);
        $total = $repo->count(['leads_only' => $leads_only]);
        $pages = (int) ceil($total / $per_page);

        include SWC_DIR . 'includes/admin/views/conversations-list.php';
    }
}
