<?php

namespace SignTeb\VideoHub\Api;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * A source that can import one playlist instead of a whole channel.
 *
 * Kept separate from VideoSourceInterface on purpose: adding methods to that
 * contract would break every source already implementing it, including any a
 * third party registered through the stvh_video_sources filter. Callers test
 * with `instanceof` and fall back to whole-channel import.
 */
interface PlaylistAwareInterface
{
    /**
     * Playlists available on the configured channel.
     *
     * @return array{ok:bool,items:array<int,array{id:string,title:string,count:int}>,error?:string}
     */
    public function playlists(): array;

    /** The playlist currently selected, or '' for the whole channel. */
    public function selected_playlist(): string;
}
