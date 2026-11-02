<?php

namespace App\Models;

use App\Enums\SubscriptionCategory;
use App\Enums\SubscriptionCycle;
use App\Support\Money;
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
     * A trial nobody has answered yet: not cancelled and still on trial.
     */
    public function isLiveTrial(): bool
    {
        return ! $this->isCancelled() && $this->is_trial;
    }

    /**
     * Whole calendar days to the next payment: 0 today, negative once the date has passed.
     */
    public function daysUntilNextPayment(): int
    {
        return RelativeDate::daysFromToday($this->next_payment_date);
    }

    /**
     * The list's price: "€24.99/mo" or "€120.00/yr".
     */
    public function priceLabel(): string
    {
        return Money::format((float) $this->price).($this->cycle === SubscriptionCycle::Yearly ? '/yr' : '/mo');
    }

    /**
     * The receipt row's meta: "monthly · next Sat 31 Oct", "free trial · ends Mon 2 Nov",
     * or "was due" and "ended" once the date has passed. Cancelled ones aren't on the receipt.
     */
    public function receiptMeta(): string
    {
        $date = $this->next_payment_date->format('D j M');
        $passed = $this->daysUntilNextPayment() < 0;

        return $this->is_trial
            ? 'free trial · '.($passed ? 'ended' : 'ends')." {$date}"
            : $this->cycle->value.' · '.($passed ? 'was due' : 'next')." {$date}";
    }

    /**
     * The list row's meta: "Entertainment · monthly · next Sat", "Education · trial until Mon",
     * "was due" and "trial ended" once the date has passed, or "cancelled 29 Aug · Entertainment".
     */
    public function listMeta(): string
    {
        if ($this->cancelled_at !== null) {
            return 'cancelled '.RelativeDate::format($this->cancelled_at).' · '.$this->category->value;
        }

        $date = RelativeDate::format($this->next_payment_date);
        $passed = $this->daysUntilNextPayment() < 0;

        return $this->category->value.' · '.($this->is_trial
            ? ($passed ? 'trial ended' : 'trial until')." {$date}"
            : $this->cycle->value.' · '.($passed ? 'was due' : 'next')." {$date}");
    }

    /**
     * The trial card's title: "Duolingo Super turns paid on Monday", or "Duolingo Super's trial ended on Mon 26 Oct".
     */
    public function trialHeadline(): string
    {
        return $this->daysUntilNextPayment() < 0
            ? "{$this->name}'s trial ended on ".$this->next_payment_date->format('D j M')
            : "{$this->name} turns paid on ".$this->next_payment_date->format('l');
    }

    /**
     * The trial card's detail: "Free until Mon 2 Nov, then €12.99 / month.", or once the date has passed
     * "Free until Mon 26 Oct. Did it turn paid at €12.99 / month?".
     */
    public function trialDetail(): string
    {
        $date = $this->next_payment_date->format('D j M');
        $price = Money::format((float) $this->price).' / '.($this->cycle === SubscriptionCycle::Yearly ? 'year' : 'month');

        return $this->daysUntilNextPayment() < 0
            ? "Free until {$date}. Did it turn paid at {$price}?"
            : "Free until {$date}, then {$price}.";
    }

    /**
     * The remind button: "Remind me Sunday" or "Reminder set · Sun 1 Nov", for the day before the date.
     * Null from the last day on, when there's no day before left to remind on.
     */
    public function reminderLabel(): ?string
    {
        if ($this->daysUntilNextPayment() < 1) {
            return null;
        }

        $dayBefore = $this->next_payment_date->copy()->subDay();

        return $this->trial_reminder
            ? 'Reminder set · '.$dayBefore->format('D j M')
            : 'Remind me '.$dayBefore->format('l');
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
