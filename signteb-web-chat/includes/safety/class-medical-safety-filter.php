<?php
/**
 * SWC_Medical_Safety_Filter — post-processing safety layer.
 *
 * Runs in addition to the system-prompt rules. It screens the user message (to
 * short-circuit emergencies before spending an API call) and the model reply
 * (to append the medical disclaimer when symptoms/illness are discussed).
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Medical_Safety_Filter
{
    /** High-risk terms that must halt the AI and surface emergency contact. */
    private const EMERGENCY_TERMS = [
        'خودکشی', 'خودکُشی', 'اقدام به خودکشی', 'تنگی نفس شدید', 'درد قفسه سینه',
        'سکته', 'حمله قلبی', 'خونریزی شدید', 'تشنج', 'بیهوش', 'مسمومیت',
        'اوردوز', 'over dose', 'overdose', 'suicide', 'اورژانسی', 'نفس نمیتونم', 'نفس نمی‌تونم',
    ];

    /** Terms meaning the reply concerns symptoms => must carry the disclaimer. */
    private const MEDICAL_TERMS = [
        'علائم', 'علامت', 'بیماری', 'درد', 'دارو', 'دوز', 'قرص', 'عفونت',
        'تب', 'سرفه', 'تشخیص', 'درمان', 'عوارض', 'symptom', 'medicine', 'dose', 'diagnos',
    ];

    private const DISCLAIMER = 'ℹ️ توجه: این اطلاعات عمومی است و جایگزین مشاوره و ویزیت حضوری پزشک نیست. برای بررسی دقیق وضعیت‌تان، رزرو نوبت را پیشنهاد می‌کنم.';

    private SWC_Settings $settings;

    public function __construct(SWC_Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @return array{emergency:bool,reply?:string}
     */
    public function screen_input(string $message): array
    {
        if ($this->contains($message, self::EMERGENCY_TERMS)) {
            $emergency = (string) $this->settings->get('emergency_number', '115');
            $phone     = (string) $this->settings->get('phone', '');
            $extra     = $phone !== '' ? " یا با کلینیک ({$phone}) تماس بگیرید" : '';

            return [
                'emergency' => true,
                'reply'     => "⚠️ وضعیت شما ممکن است فوری باشد. لطفاً همین حالا با اورژانس {$emergency} تماس بگیرید{$extra}. سلامتی شما در اولویت است — این چت جایگزین کمک فوری پزشکی نیست.",
            ];
        }

        return ['emergency' => false];
    }

    public function filter_output(string $reply, string $user_message): string
    {
        $medical = $this->contains($reply, self::MEDICAL_TERMS)
            || $this->contains($user_message, self::MEDICAL_TERMS);

        if ($medical && ! str_contains($reply, 'جایگزین') && ! str_contains($reply, 'مشاوره')) {
            $reply = rtrim($reply) . "\n\n" . self::DISCLAIMER;
        }

        return $reply;
    }

    private function contains(string $haystack, array $needles): bool
    {
        $haystack = mb_strtolower($haystack);
        foreach ($needles as $needle) {
            if (mb_strpos($haystack, mb_strtolower($needle)) !== false) {
                return true;
            }
        }
        return false;
    }
}
