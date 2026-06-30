<?php

namespace STMC_Chat\License;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Demo / Trial mode for the marketplace: an unlicensed install can answer a
 * limited number of AI messages (default 50) so a buyer can evaluate the
 * plugin before activating an annual license. Once a valid license key is
 * present, the cap is lifted entirely.
 *
 * The counter is a single option so it survives across sessions/visitors and
 * cannot be reset by clearing cookies.
 */
class TrialManager
{
    private const COUNT_OPTION = 'stmc_chat_trial_count';
    private const DEFAULT_LIMIT = 50;

    private LicenseManager $license;

    public function __construct(?LicenseManager $license = null)
    {
        $this->license = $license ?? new LicenseManager();
    }

    public function is_licensed(): bool
    {
        $info = $this->license->info();
        return ($info['status'] ?? '') === 'active' && ($info['key'] ?? '') !== '';
    }

    public function limit(): int
    {
        return (int) apply_filters('stmc_chat_trial_limit', self::DEFAULT_LIMIT);
    }

    public function used(): int
    {
        return (int) get_option(self::COUNT_OPTION, 0);
    }

    public function remaining(): int
    {
        return max(0, $this->limit() - $this->used());
    }

    /**
     * True when the free trial is exhausted and no license is active.
     */
    public function exceeded(): bool
    {
        if ($this->is_licensed()) {
            return false;
        }
        return $this->used() >= $this->limit();
    }

    /**
     * Count one consumed AI message. No-op once licensed.
     */
    public function increment(): void
    {
        if ($this->is_licensed()) {
            return;
        }
        update_option(self::COUNT_OPTION, $this->used() + 1, false);
    }
}
