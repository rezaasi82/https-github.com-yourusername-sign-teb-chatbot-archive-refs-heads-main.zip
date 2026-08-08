<?php

namespace SignTeb\VideoHub\Api;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * A video provider. Adding Vimeo or a self-hosted library later means adding
 * one class here and registering it in SourceManager — nothing in Sync, Cron,
 * Schema or Front changes.
 */
interface VideoSourceInterface
{
    /** Machine id stored in the video's _stvh_source meta ('aparat', 'youtube'). */
    public function id(): string;

    /** Human label for the admin UI. */
    public function label(): string;

    /** True when the source has everything it needs (channel id, API key…). */
    public function is_configured(): bool;

    /**
     * Fetch the latest videos, newest first.
     *
     * @param int $limit Maximum number of videos to return.
     *
     * @return array{ok:bool,videos:array<int,VideoDto>,error?:string}
     */
    public function fetch(int $limit): array;

    /**
     * Lightweight credential/connectivity probe for the dashboard's
     * "وضعیت API" panel.
     *
     * @return array{ok:bool,message:string}
     */
    public function test_connection(): array;
}
