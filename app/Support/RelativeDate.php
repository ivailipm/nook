<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * The app's relative dates, following the prototype's rel(): "today", "tomorrow", "yesterday",
 * the short weekday for 2 to 6 days ahead, otherwise "10 Nov". Always in English.
 */
class RelativeDate
{
    public static function format(CarbonInterface $date): string
    {
        $days = self::daysFromToday($date);

        return match (true) {
            $days === 0 => 'today',
            $days === 1 => 'tomorrow',
            $days === -1 => 'yesterday',
            $days >= 2 && $days <= 6 => $date->format('D'),
            default => $date->format('j M'),
        };
    }

    /**
     * Whole calendar days from today to the date in the app timezone: 0 today, 1 tomorrow, negative in the past.
     * Both ends are midnights, so a clock change in between doesn't shift the count.
     */
    public static function daysFromToday(CarbonInterface $date): int
    {
        return (int) today()->diffInDays($date->toDateString(), false);
    }
}
