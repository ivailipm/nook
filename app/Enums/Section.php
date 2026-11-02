<?php

namespace App\Enums;

use Illuminate\Support\Facades\Route;

/**
 * The app's sections. Each route is named after its section's value.
 */
enum Section: string
{
    case Home = 'home';
    case Groceries = 'groceries';
    case Promos = 'promos';
    case Todo = 'todo';
    case Subscriptions = 'subscriptions';

    public static function current(): self
    {
        return self::from((string) Route::currentRouteName());
    }

    /**
     * The sections with a tab in the floating nav, in order.
     *
     * @return list<self>
     */
    public static function tabs(): array
    {
        return array_values(array_filter(self::cases(), fn (self $section) => $section->tabLabel() !== null));
    }

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Home',
            self::Groceries => 'Groceries',
            self::Promos => 'Promo board',
            self::Todo => 'To-do',
            self::Subscriptions => 'Subscriptions',
        };
    }

    /**
     * The floating nav label, or null when the section has no tab there.
     */
    public function tabLabel(): ?string
    {
        return match ($this) {
            self::Home => 'Home',
            self::Groceries => 'Groceries',
            self::Promos => null,
            self::Todo => 'To-do',
            self::Subscriptions => 'Subs',
        };
    }

    /**
     * The floating nav tab that is current on this section.
     */
    public function tab(): self
    {
        return match ($this) {
            self::Promos => self::Groceries,
            default => $this,
        };
    }

    /**
     * The Lucide icon name, rendered as <x-lucide-{icon} />.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Home => 'house',
            self::Groceries => 'shopping-bag',
            self::Promos => 'tag',
            self::Todo => 'list',
            self::Subscriptions => 'receipt',
        };
    }

    /**
     * The background gradient and accent colour, set per ground in app.css.
     */
    public function ground(): string
    {
        return match ($this) {
            self::Home => 'home',
            self::Groceries, self::Promos => 'groceries',
            self::Todo => 'todo',
            self::Subscriptions => 'subscriptions',
        };
    }

    public function fabLabel(): string
    {
        return match ($this) {
            self::Home => 'Add something',
            self::Groceries => 'Add to your list',
            self::Promos => 'Pin a deal',
            self::Todo => 'New task',
            self::Subscriptions => 'New subscription',
        };
    }

    /**
     * The sheet the FAB opens.
     */
    public function fabSheet(): string
    {
        return match ($this) {
            self::Home => 'add-something',
            self::Groceries => 'add-item',
            self::Promos => 'add-promo',
            self::Todo => 'add-task',
            self::Subscriptions => 'add-subscription',
        };
    }
}
