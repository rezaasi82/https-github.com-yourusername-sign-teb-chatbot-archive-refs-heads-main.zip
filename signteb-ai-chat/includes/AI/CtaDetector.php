<?php

namespace STMC_Chat\AI;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Detects booking/contact intent in a turn so the frontend can render an
 * inline CTA card and the conversation can be logged as a lead (ROI tracking).
 */
class CtaDetector
{
    private const BOOKING_TERMS = [
        'رزرو', 'نوبت', 'وقت بگیرم', 'وقت می‌خوام', 'وقت میخوام', 'appointment', 'book', 'بوک',
    ];
    private const CONTACT_TERMS = [
        'واتساپ', 'واتس‌اپ', 'whatsapp', 'تماس', 'شماره', 'زنگ', 'call',
    ];

    /**
     * @return string '' | 'booking' | 'contact'
     */
    public function detect(string $user_message, string $assistant_reply): string
    {
        $haystack = mb_strtolower($user_message . ' ' . $assistant_reply);

        foreach (self::BOOKING_TERMS as $t) {
            if (mb_strpos($haystack, mb_strtolower($t)) !== false) {
                return 'booking';
            }
        }
        foreach (self::CONTACT_TERMS as $t) {
            if (mb_strpos($haystack, mb_strtolower($t)) !== false) {
                return 'contact';
            }
        }
        return '';
    }
}
