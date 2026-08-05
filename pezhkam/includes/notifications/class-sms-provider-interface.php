<?php
/**
 * Contract every SMS gateway implements.
 *
 * Adding a new panel means writing one class that implements this interface and
 * registering it in SmsManager::PROVIDERS — no change to the callers.
 *
 * @package Pezhkam
 */

namespace Pezhkam\Notifications;

if (! defined('ABSPATH')) {
    exit;
}

interface SmsProviderInterface
{
    /** Stable machine id, e.g. "kavenegar". */
    public function id(): string;

    /** Human label shown in the settings dropdown. */
    public function label(): string;

    /**
     * Send one free-text message (needs an advertising/marketing line).
     *
     * @return array{ok:bool,error?:string,code?:int}
     */
    public function send(string $to, string $text): array;

    /**
     * Whether this panel supports pattern / service-line (verified) SMS.
     * Service lines (خط خدماتی) can only send pre-approved templates identified
     * by a code, with the variable parts passed as ordered parameters.
     */
    public function supports_pattern(): bool;

    /**
     * Send a pre-approved pattern message by its code.
     *
     * @param string        $to     destination number
     * @param string        $code   the panel's template / pattern / bodyId code
     * @param array<int,string> $params ordered variable values for the template
     * @return array{ok:bool,error?:string,code?:int}
     */
    public function send_pattern(string $to, string $code, array $params): array;
}
