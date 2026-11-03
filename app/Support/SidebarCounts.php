<?php

namespace App\Support;

use App\Enums\Section;
use App\Models\User;

/**
 * The numbers beside the sidebar's links, following the prototype. Home has none.
 */
class SidebarCounts
{
    /**
     * @return array<string, int> Keyed by Section value.
     */
    public static function for(User $user): array
    {
        return [
            Section::Groceries->value => $user->groceryItems()->where('done', false)->count(),
            Section::Promos->value => $user->promos()->active()->count(),
            Section::Todo->value => $user->tasks()->where('done', false)->count(),
            Section::Subscriptions->value => $user->subscriptions()->whereNull('cancelled_at')->count(),
        ];
    }
}
