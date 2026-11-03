<?php

namespace App\Actions;

use App\Models\User;

/**
 * Flips the morning nudge setting and builds its toast. It saves the setting only: nothing sends the nudge yet.
 */
class ToggleMorningNudge
{
    public function handle(User $user): void
    {
        $user->update(['morning_nudge' => ! $user->morning_nudge]);
    }

    /**
     * The toast after a toggle: "Morning nudge on · 08:00" or "Morning nudge off".
     */
    public function message(User $user): string
    {
        return $user->morning_nudge ? 'Morning nudge on · 08:00' : 'Morning nudge off';
    }
}
