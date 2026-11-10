<?php

namespace App\Livewire\Pages;

use App\Models\Subscription;
use App\Support\SubscriptionTotals;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The Subscriptions page. Every action rereads the subscription and rechecks its condition, so a double tap or a stale page does nothing.
 */
#[Title('Subscriptions')]
class Subscriptions extends Component
{
    /**
     * Saves the flag only. Nothing sends the reminder yet.
     */
    public function toggleReminder(int $id): void
    {
        $subscription = auth()->user()->subscriptions()->findOrFail($id);

        if (! $subscription->isLiveTrial() || $subscription->daysUntilNextPayment() < 1) {
            return;
        }

        $subscription->update(['trial_reminder' => ! $subscription->trial_reminder]);

        if ($subscription->trial_reminder) {
            $this->dispatch('toast', message: 'We will nudge you the day before');
        }
    }

    public function keepTrial(int $id): void
    {
        $subscription = auth()->user()->subscriptions()->findOrFail($id);

        if (! $subscription->isLiveTrial()) {
            return;
        }

        $subscription->update(['is_trial' => false]);

        $this->dispatch('toast', message: "{$subscription->name} is now a paid subscription");
    }

    public function cancelTrial(int $id): void
    {
        $subscription = auth()->user()->subscriptions()->findOrFail($id);

        if (! $subscription->isLiveTrial()) {
            return;
        }

        $subscription->update(['cancelled_at' => today()]);

        // Once the date has passed it may already have been charged.
        $this->dispatch('toast', message: $subscription->daysUntilNextPayment() < 0 ? 'Trial cancelled' : 'Trial cancelled. Nothing will be charged');
        $this->dispatch('nav-counts-changed');
    }

    public function cancel(int $id): void
    {
        $subscription = auth()->user()->subscriptions()->findOrFail($id);

        if ($subscription->isCancelled()) {
            return;
        }

        $subscription->update(['cancelled_at' => today()]);

        $this->dispatch('toast', message: "{$subscription->name} marked as cancelled");
        $this->dispatch('nav-counts-changed');
    }

    public function resume(int $id): void
    {
        $subscription = auth()->user()->subscriptions()->findOrFail($id);

        if (! $subscription->isCancelled()) {
            return;
        }

        $subscription->update(['cancelled_at' => null]);

        $this->dispatch('toast', message: "{$subscription->name} is back on");
        $this->dispatch('nav-counts-changed');
    }

    /**
     * The subscription sheet saved or deleted a row, so the page re-renders. A saved row can move, which can rebuild its
     * button, so the button takes focus back by id once the page has changed; a deleted row has no button left.
     */
    #[On('subscription-changed')]
    public function subscriptionChanged(int $id): void
    {
        $this->js("document.getElementById('subscription-open-{$id}')?.focus()");
    }

    public function render(): View
    {
        $user = auth()->user();
        [$cancelled, $live] = $user->subscriptions()->orderBy('next_payment_date')->orderBy('id')->get()
            ->partition(fn (Subscription $subscription) => $subscription->isCancelled());

        return view('livewire.pages.subscriptions', [
            'totals' => SubscriptionTotals::for($user),
            'live' => $live,
            'rows' => $live->concat($cancelled),
            'trial' => $live
                ->filter(fn (Subscription $subscription) => $subscription->is_trial && $subscription->daysUntilNextPayment() <= 7)
                ->sortBy('id')
                ->first(),
        ]);
    }
}
