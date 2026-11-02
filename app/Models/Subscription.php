<?php

namespace App\Models;

use App\Enums\SubscriptionCategory;
use App\Enums\SubscriptionCycle;
use App\Support\RelativeDate;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'price', 'cycle', 'next_payment_date', 'category', 'is_trial', 'trial_reminder', 'cancelled_at'])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cycle' => SubscriptionCycle::class,
            'next_payment_date' => 'date',
            'category' => SubscriptionCategory::class,
            'is_trial' => 'boolean',
            'trial_reminder' => 'boolean',
            'cancelled_at' => 'date',
        ];
    }

    /**
     * What it costs a month: the price, or a twelfth of it for a yearly one. Never rounded.
     */
    public function monthlyPrice(): float
    {
        return $this->cycle === SubscriptionCycle::Yearly ? (float) $this->price / 12 : (float) $this->price;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Counts towards the totals: not cancelled and not on trial.
     */
    public function isPaying(): bool
    {
        return ! $this->isCancelled() && ! $this->is_trial;
    }

    /**
     * What cancelling has saved so far: the monthly price for every 30 days since, and at least one month.
     * Not named saved(), which Eloquent's models already have as a static event method.
     */
    public function savedAmount(): float
    {
        if ($this->cancelled_at === null) {
            return 0.0;
        }

        $days = -RelativeDate::daysFromToday($this->cancelled_at);

        return $this->monthlyPrice() * max(1, round($days / 30));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
