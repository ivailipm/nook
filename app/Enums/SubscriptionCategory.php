<?php

namespace App\Enums;

/**
 * The subscription categories, in the prototype's chip order. Each value is the display name.
 */
enum SubscriptionCategory: string
{
    case Entertainment = 'Entertainment';
    case Health = 'Health';
    case Storage = 'Storage';
    case Education = 'Education';
    case Other = 'Other';

    /**
     * The colour of its bar segment and legend square. Full class names, so Tailwind finds them.
     */
    public function swatchClass(): string
    {
        return match ($this) {
            self::Entertainment => 'bg-cat-entertainment',
            self::Health => 'bg-cat-health',
            self::Storage => 'bg-cat-storage',
            self::Education => 'bg-cat-education',
            self::Other => 'bg-cat-other',
        };
    }

    /**
     * The colour of its avatar ring. Full class names, so Tailwind finds them.
     */
    public function ringClass(): string
    {
        return match ($this) {
            self::Entertainment => 'inset-ring-cat-entertainment',
            self::Health => 'inset-ring-cat-health',
            self::Storage => 'inset-ring-cat-storage',
            self::Education => 'inset-ring-cat-education',
            self::Other => 'inset-ring-cat-other',
        };
    }
}
