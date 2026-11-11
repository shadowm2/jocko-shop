<?php

namespace Modules\Control\Services;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Cache;

/**
 * Whether Jocko Control wants the original of each picture or a small preview.
 * It tells us with every heartbeat; until it has, or if it is an older
 * version that never says, previews are what it gets.
 */
#[Singleton]
class PictureMode
{
    private const KEY = 'control.pictures';

    public function keepsOriginals(): bool
    {
        return Cache::get(self::KEY) === 'original';
    }

    /**
     * Remember what Jocko Control asked for.
     *
     * @return bool true when that is a different answer than before, so the pictures should be sent again
     */
    public function update(mixed $answer): bool
    {
        $mode = $answer === 'original' ? 'original' : 'preview';
        $changed = (Cache::get(self::KEY) ?? 'preview') !== $mode;

        Cache::forever(self::KEY, $mode);

        return $changed;
    }
}
