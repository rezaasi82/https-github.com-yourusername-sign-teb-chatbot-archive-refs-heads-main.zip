<?php
/**
 * SWC_Sms_Provider_Interface — contract every SMS gateway implements.
 *
 * Adding a new panel means writing one class that implements this interface and
 * registering it in SWC_Sms_Manager::PROVIDERS — no change to the callers.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

interface SWC_Sms_Provider_Interface
{
    /** Stable machine id, e.g. "kavenegar". */
    public function id(): string;

    /** Human label shown in the settings dropdown. */
    public function label(): string;

    /**
     * Send one text message.
     *
     * @return array{ok:bool,error?:string,code?:int}
     */
    public function send(string $to, string $text): array;
}
