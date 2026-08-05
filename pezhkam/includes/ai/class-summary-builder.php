<?php
/**
 * Builds a structured conversation summary for the
 * dashboard (name, phone, topic, needed services, booking probability, status).
 *
 * Heuristic and instant (no extra API call): the topic is derived from the
 * visitor's messages and the needed services are matched against the clinic's
 * own configured service list. Stored on the conversation and shown to the
 * clinic so a human can follow up (ROI / lead quality).
 *
 * @package Pezhkam
 */

namespace Pezhkam\Ai;

if (! defined('ABSPATH')) {
    exit;
}

class SummaryBuilder
{
    private \Pezhkam\Core\Settings $settings;

    public function __construct(\Pezhkam\Core\Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @param array{name:string,phone:string,user_text:string,first_message:string} $data
     * @param array{label:string,probability:int}                                   $score
     */
    public function build(array $data, array $score): string
    {
        $name  = $data['name'] !== '' ? $data['name'] : '—';
        $phone = $data['phone'] !== '' ? $data['phone'] : '—';

        $topic = trim($data['first_message']);
        if ($topic === '') {
            $topic = '—';
        } elseif (mb_strlen($topic) > 80) {
            $topic = mb_substr($topic, 0, 80) . '…';
        }

        $services = $this->match_services($data['user_text']);
        $services = $services !== [] ? implode('، ', $services) : '—';

        return implode("\n", [
            'نام: ' . $name,
            'شماره: ' . $phone,
            'موضوع: ' . $topic,
            'خدمات موردنیاز: ' . $services,
            'احتمال رزرو: ' . $score['probability'] . '٪',
            'وضعیت: ' . $score['label'],
        ]);
    }

    /**
     * Which of the clinic's configured services the visitor mentioned.
     *
     * @return array<int,string>
     */
    private function match_services(string $text): array
    {
        $text = mb_strtolower($text);
        $out  = [];
        foreach ((new \Pezhkam\Ai\SystemPromptBuilder($this->settings))->services() as $service) {
            $name = trim((string) $service['name']);
            if ($name !== '' && mb_strpos($text, mb_strtolower($name)) !== false) {
                $out[] = $name;
            }
        }
        return array_slice(array_unique($out), 0, 5);
    }
}
