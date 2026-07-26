<?php
/**
 * Classifies a conversation as hot / warm / cold.
 *
 * A deterministic heuristic over intent signals (no extra API call, so it is
 * free and instant). It runs after every turn so the dashboard always shows a
 * live lead temperature, and estimates a booking probability for the summary.
 *
 * @package Medora
 */

namespace Medora\Ai;

if (! defined('ABSPATH')) {
    exit;
}

class LeadScorer
{
    private const HOT_TERMS = [
        'رزرو', 'نوبت', 'وقت بگیرم', 'وقت میخوام', 'وقت می‌خوام', 'ثبت نوبت', 'می‌خوام بیام', 'کی بیام',
        'appointment', 'book',
    ];
    private const WARM_TERMS = [
        'قیمت', 'هزینه', 'تعرفه', 'چند', 'ساعت کاری', 'آدرس', 'کجاست', 'واتساپ', 'واتس‌اپ', 'شماره', 'تماس',
        'price', 'cost', 'address',
    ];

    /**
     * @param array{text:string,cta:string,has_phone:bool,has_name:bool,message_count:int} $signals
     * @return array{level:string,label:string,emoji:string,probability:int}
     */
    public function score(array $signals): array
    {
        $text   = mb_strtolower((string) ($signals['text'] ?? ''));
        $points = 0;

        if (! empty($signals['has_phone'])) {
            $points += 2;
        }
        if (! empty($signals['has_name'])) {
            $points += 1;
        }

        $cta = (string) ($signals['cta'] ?? '');
        if ($cta === 'booking') {
            $points += 3;
        } elseif ($cta === 'contact') {
            $points += 1;
        }

        $points += min(3, $this->hits($text, self::HOT_TERMS) * 2);
        $points += min(2, $this->hits($text, self::WARM_TERMS));

        $count = (int) ($signals['message_count'] ?? 0);
        if ($count >= 4) {
            $points += 1;
        }
        if ($count >= 8) {
            $points += 1;
        }

        if ($points >= 5) {
            return ['level' => 'hot', 'label' => __('لید داغ', 'signteb-web-chat'), 'emoji' => '🟢', 'probability' => min(95, 78 + $points)];
        }
        if ($points >= 2) {
            return ['level' => 'warm', 'label' => __('لید متوسط', 'signteb-web-chat'), 'emoji' => '🟡', 'probability' => min(74, 45 + $points * 4)];
        }
        return ['level' => 'cold', 'label' => __('لید سرد', 'signteb-web-chat'), 'emoji' => '⚪', 'probability' => max(5, 12 + $points * 4)];
    }

    private function hits(string $haystack, array $needles): int
    {
        $n = 0;
        foreach ($needles as $needle) {
            if (mb_strpos($haystack, mb_strtolower($needle)) !== false) {
                $n++;
            }
        }
        return $n;
    }
}
