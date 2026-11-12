<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Store;
use App\Enums\TaskTag;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'morning_nudge'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The database default, so a just-created model reads it without a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['morning_nudge' => true];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'morning_nudge' => 'boolean',
        ];
    }

    /**
     * @return HasMany<GroceryItem, $this>
     */
    public function groceryItems(): HasMany
    {
        return $this->hasMany(GroceryItem::class);
    }

    /**
     * @return HasMany<Promo, $this>
     */
    public function promos(): HasMany
    {
        return $this->hasMany(Promo::class);
    }

    /**
     * @return HasMany<CustomStore, $this>
     */
    public function customStores(): HasMany
    {
        return $this->hasMany(CustomStore::class);
    }

    /**
     * The user's stores in chip order: the built-in ones, then the user's own in the order they were added, then Other.
     *
     * @return list<string>
     */
    public function storeNames(): array
    {
        return [
            ...array_column(array_filter(Store::cases(), fn (Store $store) => $store !== Store::Other), 'value'),
            ...$this->customStores()->orderBy('id')->pluck('name')->map(fn (mixed $name) => (string) $name)->all(),
            Store::Other->value,
        ];
    }

    /**
     * @return HasMany<CustomTag, $this>
     */
    public function customTags(): HasMany
    {
        return $this->hasMany(CustomTag::class);
    }

    /**
     * The user's task tags in chip order: the built-in ones, then the user's own in the order they were added.
     *
     * @return list<string>
     */
    public function tagNames(): array
    {
        return [
            ...array_column(TaskTag::cases(), 'value'),
            ...$this->customTags()->orderBy('id')->pluck('name')->map(fn (mixed $name) => (string) $name)->all(),
        ];
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
