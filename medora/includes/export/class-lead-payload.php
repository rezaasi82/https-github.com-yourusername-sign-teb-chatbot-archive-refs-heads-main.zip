<?php
/**
 * Assembles a normalized export payload for one lead.
 *
 * A lead is a conversation (mdr_conversations) plus its messages
 * (mdr_messages). This is the single representation consumed by every export
 * target (PDF, webhook, Google Sheets) so they never diverge.
 *
 * @package Medora
 */

namespace Medora\Export;

if (! defined('ABSPATH')) {
    exit;
}

class LeadPayload
{
    private \Medora\Database\ConversationRepository $conversations;
    private \Medora\Database\MessageRepository $messages;

    public function __construct(?\Medora\Database\ConversationRepository $conversations = null, ?\Medora\Database\MessageRepository $messages = null)
    {
        $this->conversations = $conversations ?? new \Medora\Database\ConversationRepository();
        $this->messages      = $messages ?? new \Medora\Database\MessageRepository();
    }

    /**
     * @return array{
     *   lead_id:int, patient_name:string, phone:string, request_type:string,
     *   status:string, lead_score:string, summary:string, created_at:string,
     *   pdf_url:string, messages:array<int,array{role:string,message:string,timestamp:string}>
     * }|null
     */
    public function build(int $lead_id, string $pdf_url = ''): ?array
    {
        $lead = $this->conversations->get($lead_id);
        if (! $lead) {
            return null;
        }

        if ($pdf_url === '') {
            $pdf_url = (string) ($lead->pdf_url ?? '');
        }

        $messages = [];
        foreach ($this->messages->for_conversation($lead_id) as $m) {
            if (! in_array($m->role, ['user', 'assistant'], true)) {
                continue;
            }
            $messages[] = [
                'role'      => $m->role,
                'message'   => (string) $m->content,
                'timestamp' => (string) $m->created_at,
            ];
        }

        return [
            'lead_id'      => (int) $lead->id,
            'patient_name' => (string) ($lead->patient_name ?? ''),
            'phone'        => (string) ($lead->patient_phone ?? ''),
            'request_type' => $this->request_type($lead),
            'status'       => (string) ($lead->status ?? ''),
            'lead_score'   => (string) ($lead->lead_score ?? ''),
            'summary'      => (string) ($lead->summary ?? ''),
            'created_at'   => (string) $lead->created_at,
            'pdf_url'      => $pdf_url,
            'messages'     => $messages,
        ];
    }

    /**
     * Best-effort "request type": the services line from the stored summary,
     * otherwise the CTA type.
     */
    private function request_type(object $lead): string
    {
        $summary = (string) ($lead->summary ?? '');
        if ($summary !== '' && preg_match('/خدمات موردنیاز:\s*(.+)/u', $summary, $m)) {
            $value = trim($m[1]);
            if ($value !== '' && $value !== '—') {
                return $value;
            }
        }
        return (string) ($lead->cta_type ?? '');
    }
}
