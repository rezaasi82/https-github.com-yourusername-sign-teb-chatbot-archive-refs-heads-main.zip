<?php
/**
 * SWC_Cta_Detector — detects booking/contact intent in a turn.
 *
 * When intent is found the frontend renders an inline CTA card and the
 * conversation is logged as a converted lead (the ROI signal the clinic owner
 * sees in the admin).
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Cta_Detector
{
    private const BOOKING_TERMS = [
        'رزرو', 'نوبت', 'وقت بگیرم', 'وقت می‌خوام', 'وقت میخوام', 'appointment', 'book', 'بوک', 'حجز', 'موعد',
    ];
    private const CONTACT_TERMS = [
        'واتساپ', 'واتس‌اپ', 'whatsapp', 'تماس', 'شماره', 'زنگ', 'call', 'اتصال', 'هاتف',
    ];

    /**
     * @return string '' | 'booking' | 'contact'
     */
    public function detect(string $user_message, string $assistant_reply): string
    {
        $haystack = mb_strtolower($user_message . ' ' . $assistant_reply);

        foreach (self::BOOKING_TERMS as $term) {
            if (mb_strpos($haystack, mb_strtolower($term)) !== false) {
                return 'booking';
            }
        }
        foreach (self::CONTACT_TERMS as $term) {
            if (mb_strpos($haystack, mb_strtolower($term)) !== false) {
                return 'contact';
            }
        }
        return '';
    }
}
