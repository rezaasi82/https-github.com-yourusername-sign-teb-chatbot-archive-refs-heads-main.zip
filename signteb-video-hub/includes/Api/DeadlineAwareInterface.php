<?php

namespace SignTeb\VideoHub\Api;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * A source whose network calls can be held inside a wall-clock deadline.
 *
 * Sync runs a budget so a cron tick cannot hold a visitor's page open, but a
 * budget only helps where it is actually consulted. Fetching from a provider
 * is the slowest part of a sync and happens before the first budget check, so
 * a source that talks to the network must be told when to stop rather than
 * being trusted to finish quickly.
 */
interface DeadlineAwareInterface
{
    /**
     * @param float|null $deadline Unix timestamp (microtime) after which no new
     *                             request may start. Null removes the limit.
     */
    public function set_deadline(?float $deadline): void;
}
