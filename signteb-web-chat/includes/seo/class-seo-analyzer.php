<?php
/**
 * Mines the conversation history for SEO signals.
 *
 * Extracts the most frequent opening questions, high-value keywords (stopwords
 * removed), and which of the clinic's services are trending in chats. Heavy
 * scans are cached briefly.
 *
 * @package SignTeb_Web_Chat
 */

namespace SignTeb\WebChat\Seo;

if (! defined('ABSPATH')) {
    exit;
}

class SeoAnalyzer
{
    /** Words to ignore when ranking keywords (Persian + generic). */
    private const STOPWORDS = [
        'و', 'در', 'به', 'از', 'که', 'این', 'را', 'با', 'برای', 'است', 'می', 'آیا', 'چه', 'چطور', 'چگونه',
        'هست', 'شما', 'من', 'ما', 'یک', 'های', 'ها', 'تا', 'هم', 'یا', 'اگر', 'بود', 'شد', 'کرد', 'دارم',
        'دارد', 'دارید', 'سلام', 'ممنون', 'لطفا', 'لطفاً', 'خیلی', 'الان', 'باید', 'کنم', 'کنید', 'میشه',
        'می‌شه', 'میخوام', 'می‌خوام', 'حالا', 'یعنی', 'اون', 'اینکه', 'ولی', 'همین', 'باشه', 'بله', 'خوب',
        'the', 'and', 'for', 'you', 'are', 'with', 'что', 'how', 'what', 'can', 'have', 'this', 'حدود',
    ];

    /**
     * Most frequent opening questions.
     *
     * @return array<int,object> {q, c}
     */
    public function top_questions(int $days = 30, int $limit = 15): array
    {
        return \SignTeb\WebChat\Core\Cache::remember('seo_q_' . $days . '_' . $limit, 600, function () use ($days, $limit) {
            global $wpdb;
            $messages = \SignTeb\WebChat\Database\Schema::messages_table();
            $since    = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT LEFT(content, 90) AS q, COUNT(*) AS c FROM {$messages}
                     WHERE role = 'user' AND created_at >= %s
                     GROUP BY q ORDER BY c DESC LIMIT %d",
                    $since,
                    $limit
                )
            ) ?: [];
        });
    }

    /**
     * High-value keyword frequencies from visitor messages.
     *
     * @return array<string,int> keyword => count (sorted desc)
     */
    public function keywords(int $days = 30, int $limit = 30): array
    {
        return \SignTeb\WebChat\Core\Cache::remember('seo_kw_' . $days . '_' . $limit, 600, function () use ($days, $limit) {
            $counts = [];
            foreach ($this->recent_user_texts($days) as $text) {
                $tokens = preg_split('/[^\p{L}\p{N}\x{200c}]+/u', mb_strtolower($text)) ?: [];
                foreach ($tokens as $token) {
                    $token = trim($token, "\u{200c}");
                    if (mb_strlen($token) < 3 || is_numeric($token) || in_array($token, self::STOPWORDS, true)) {
                        continue;
                    }
                    $counts[$token] = ($counts[$token] ?? 0) + 1;
                }
            }
            arsort($counts);
            return array_slice(array_filter($counts, static fn($c) => $c >= 2), 0, $limit, true);
        });
    }

    /**
     * Which configured services are mentioned most in chats (trending topics).
     *
     * @return array<string,int> service => mentions
     */
    public function topics(int $days = 30): array
    {
        return \SignTeb\WebChat\Core\Cache::remember('seo_topics_' . $days, 600, function () use ($days) {
            $services = array_map(
                static fn($s) => (string) $s['name'],
                (new \SignTeb\WebChat\Ai\SystemPromptBuilder(new \SignTeb\WebChat\Core\Settings()))->services()
            );
            if ($services === []) {
                return [];
            }
            $blob = mb_strtolower(implode("\n", $this->recent_user_texts($days)));
            $out  = [];
            foreach ($services as $name) {
                $name = trim($name);
                if ($name === '') {
                    continue;
                }
                $count = mb_substr_count($blob, mb_strtolower($name));
                if ($count > 0) {
                    $out[$name] = $count;
                }
            }
            arsort($out);
            return $out;
        });
    }

    /**
     * @return array<int,string>
     */
    private function recent_user_texts(int $days, int $cap = 3000): array
    {
        global $wpdb;
        $messages = \SignTeb\WebChat\Database\Schema::messages_table();
        $since    = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $rows     = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT content FROM {$messages} WHERE role = 'user' AND created_at >= %s ORDER BY id DESC LIMIT %d",
                $since,
                $cap
            )
        ) ?: [];
        return array_map('strval', $rows);
    }
}
