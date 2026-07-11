<?php
/**
 * SWC_Conversation_Repository — repository for chat conversations.
 *
 * All SQL is prepared and centralized here (Repository pattern, not Active
 * Record) so query logic lives in one place.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Conversation_Repository
{
    /**
     * Find an open conversation by session id, or create one.
     *
     * @return int conversation id
     */
    public function find_or_create(string $session_id, array $meta = []): int
    {
        global $wpdb;
        $table = SWC_Schema::conversations_table();

        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE session_id = %s AND status = 'open' ORDER BY id DESC LIMIT 1",
                $session_id
            )
        );

        if ($existing) {
            return (int) $existing;
        }

        $now = current_time('mysql');
        $wpdb->insert(
            $table,
            [
                'session_id'    => $session_id,
                'visitor_ip'    => $meta['ip'] ?? null,
                'user_id'       => $meta['user_id'] ?? null,
                'language'      => $meta['language'] ?? 'fa',
                'page_url'      => $meta['page_url'] ?? null,
                'status'        => 'open',
                'message_count' => 0,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            ['%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    public function touch(int $conversation_id): void
    {
        global $wpdb;
        $table = SWC_Schema::conversations_table();
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET message_count = message_count + 1, updated_at = %s WHERE id = %d",
                current_time('mysql'),
                $conversation_id
            )
        );
    }

    public function mark_lead(int $conversation_id, string $cta_type): void
    {
        global $wpdb;
        $table = SWC_Schema::conversations_table();
        $wpdb->update(
            $table,
            ['is_lead' => 1, 'cta_type' => $cta_type, 'updated_at' => current_time('mysql')],
            ['id' => $conversation_id],
            ['%d', '%s', '%s'],
            ['%d']
        );
    }

    /**
     * Save captured patient identity (only overwrites non-empty values).
     */
    public function set_patient(int $conversation_id, string $name, string $phone): void
    {
        $data    = ['updated_at' => current_time('mysql')];
        $formats = ['%s'];
        if ($name !== '') {
            $data['patient_name'] = $name;
            $formats[]            = '%s';
        }
        if ($phone !== '') {
            $data['patient_phone'] = $phone;
            $formats[]             = '%s';
        }
        if (count($data) === 1) {
            return;
        }
        global $wpdb;
        $wpdb->update(SWC_Schema::conversations_table(), $data, ['id' => $conversation_id], $formats, ['%d']);
    }

    public function set_score(int $conversation_id, string $level): void
    {
        global $wpdb;
        $wpdb->update(
            SWC_Schema::conversations_table(),
            ['lead_score' => $level, 'updated_at' => current_time('mysql')],
            ['id' => $conversation_id],
            ['%s', '%s'],
            ['%d']
        );
    }

    public function set_summary(int $conversation_id, string $summary): void
    {
        global $wpdb;
        $wpdb->update(
            SWC_Schema::conversations_table(),
            ['summary' => $summary, 'updated_at' => current_time('mysql')],
            ['id' => $conversation_id],
            ['%s', '%s'],
            ['%d']
        );
    }

    public function set_booking_status(int $conversation_id, string $status): void
    {
        global $wpdb;
        $wpdb->update(
            SWC_Schema::conversations_table(),
            ['booking_status' => $status, 'updated_at' => current_time('mysql')],
            ['id' => $conversation_id],
            ['%s', '%s'],
            ['%d']
        );
    }

    /**
     * Update CRM fields with a strict whitelist of columns and formats.
     *
     * @param array<string,string> $fields
     */
    public function update_crm(int $conversation_id, array $fields): void
    {
        $allowed = ['lead_status' => '%s', 'email' => '%s', 'tags' => '%s', 'notes' => '%s'];
        $data    = ['updated_at' => current_time('mysql')];
        $formats = ['%s'];
        foreach ($fields as $key => $value) {
            if (isset($allowed[$key])) {
                $data[$key]  = $value;
                $formats[]   = $allowed[$key];
            }
        }
        if (count($data) === 1) {
            return;
        }
        global $wpdb;
        $wpdb->update(SWC_Schema::conversations_table(), $data, ['id' => $conversation_id], $formats, ['%d']);
    }

    /**
     * Count of leads per pipeline stage (for the CRM funnel).
     *
     * @return array<string,int> lead_status => count
     */
    public function funnel_counts(int $days = 30): array
    {
        global $wpdb;
        $table = SWC_Schema::conversations_table();
        $since = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT lead_status, COUNT(*) AS c FROM {$table}
                 WHERE created_at >= %s GROUP BY lead_status",
                $since
            )
        ) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->lead_status] = (int) $row->c;
        }
        return $out;
    }

    public function set_pdf_url(int $conversation_id, string $url): void
    {
        global $wpdb;
        $wpdb->update(
            SWC_Schema::conversations_table(),
            ['pdf_url' => $url, 'updated_at' => current_time('mysql')],
            ['id' => $conversation_id],
            ['%s', '%s'],
            ['%d']
        );
    }

    /**
     * WHERE clause from filters. Only fixed, whitelisted fragments are used
     * (no user input reaches SQL here), so the concatenation is safe.
     */
    private function where(array $filters): string
    {
        $clauses = [];
        if (! empty($filters['leads_only'])) {
            $clauses[] = 'is_lead = 1';
        }
        if (! empty($filters['score']) && in_array($filters['score'], ['hot', 'warm', 'cold'], true)) {
            $clauses[] = "lead_score = '" . $filters['score'] . "'";
        }
        return $clauses ? implode(' AND ', $clauses) : '1=1';
    }

    /** @return array<int,object> */
    public function paginate(int $page = 1, int $per_page = 20, array $filters = []): array
    {
        global $wpdb;
        $table  = SWC_Schema::conversations_table();
        $offset = max(0, ($page - 1) * $per_page);
        $where  = $this->where($filters);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        ) ?: [];
    }

    public function count(array $filters = []): int
    {
        global $wpdb;
        $table = SWC_Schema::conversations_table();
        $where = $this->where($filters);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where}");
    }

    public function get(int $conversation_id): ?object
    {
        global $wpdb;
        $table = SWC_Schema::conversations_table();
        $row   = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $conversation_id)
        );
        return $row ?: null;
    }

    /**
     * Count of conversations active (updated) within the last N hours.
     */
    public function active_count(int $hours = 24): int
    {
        global $wpdb;
        $table = SWC_Schema::conversations_table();
        $since = gmdate('Y-m-d H:i:s', time() - ($hours * HOUR_IN_SECONDS));
        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE updated_at >= %s", $since)
        );
    }

    /**
     * Aggregate stats for the dashboard.
     *
     * @return array{conversations:int,leads:int,conversion_rate:float}
     */
    public function stats(int $days = 30): array
    {
        global $wpdb;
        $table = SWC_Schema::conversations_table();
        $since = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        $total = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", $since)
        );
        $leads = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND is_lead = 1", $since)
        );
        $hot = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND lead_score = 'hot'", $since)
        );
        $booked = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND booking_status <> 'none'", $since)
        );

        return [
            'conversations'   => $total,
            'leads'           => $leads,
            'hot_leads'       => $hot,
            'booked'          => $booked,
            'conversion_rate' => $total > 0 ? round(($leads / $total) * 100, 1) : 0.0,
        ];
    }

    /**
     * How many conversations mentioned each configured service (from the
     * stored summaries) — the "most-requested services" learning signal.
     *
     * @param array<int,string> $names
     * @return array<string,int> service name => count, sorted desc
     */
    public function service_demand(array $names, int $days = 30): array
    {
        global $wpdb;
        $table = SWC_Schema::conversations_table();
        $since = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        $out = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $like  = '%' . $wpdb->esc_like($name) . '%';
            $count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND summary LIKE %s",
                    $since,
                    $like
                )
            );
            if ($count > 0) {
                $out[$name] = $count;
            }
        }
        arsort($out);
        return array_slice($out, 0, 10, true);
    }

    /**
     * Daily conversation counts for the last N days (trend chart).
     *
     * @return array<string,int> date (Y-m-d) => count
     */
    public function daily(int $days = 14): array
    {
        global $wpdb;
        $table = SWC_Schema::conversations_table();
        $since = gmdate('Y-m-d 00:00:00', time() - (($days - 1) * DAY_IN_SECONDS));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) AS d, COUNT(*) AS c FROM {$table}
                 WHERE created_at >= %s GROUP BY DATE(created_at)",
                $since
            )
        ) ?: [];

        $map = [];
        foreach ($rows as $row) {
            $map[$row->d] = (int) $row->c;
        }

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day       = gmdate('Y-m-d', time() - ($i * DAY_IN_SECONDS));
            $out[$day] = $map[$day] ?? 0;
        }
        return $out;
    }
}
