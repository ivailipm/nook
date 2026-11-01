<?php

namespace App\Support;

use App\Models\Promo;

/**
 * The classes a promo's tone and days left give a polaroid, shared by the board polaroid and the promo sheet's big one.
 * Full class names, so Tailwind finds them.
 */
class PolaroidStyle
{
    private const array TONES = [
        ['photo' => 'bg-tone-0-bg text-tone-0-text', 'tape' => 'bg-tone-0-tape'],
        ['photo' => 'bg-tone-1-bg text-tone-1-text', 'tape' => 'bg-tone-1-tape'],
        ['photo' => 'bg-tone-2-bg text-tone-2-text', 'tape' => 'bg-tone-2-tape'],
        ['photo' => 'bg-tone-3-bg text-tone-3-text', 'tape' => 'bg-tone-3-tape'],
        ['photo' => 'bg-tone-4-bg text-tone-4-text', 'tape' => 'bg-tone-4-tape'],
        ['photo' => 'bg-tone-5-bg text-tone-5-text', 'tape' => 'bg-tone-5-tape'],
    ];

    /**
     * The colours of the area that holds the photo and the text.
     */
    public static function photo(Promo $promo): string
    {
        return self::TONES[$promo->tone % 6]['photo'];
    }

    public static function tape(Promo $promo): string
    {
        return self::TONES[$promo->tone % 6]['tape'];
    }

    /**
     * The colour of the handwritten label: grey once expired, red on the last two days, brown otherwise.
     */
    public static function untilColour(Promo $promo): string
    {
        return match (true) {
            $promo->daysLeft() < 0 => 'text-[#6b625a]',
            $promo->daysLeft() <= 1 => 'text-ends-soon',
            default => 'text-[#3a2a20]',
        };
    }
}
