<?php

namespace App\Livewire\Sheets;

use App\Actions\UpdateSubscription;
use App\Enums\SubscriptionCategory;
use App\Enums\SubscriptionCycle;
use App\Models\Subscription;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The "Edit subscription" sheet, rendered once in the layout and opened by the rows of the All subscriptions list.
 * Saving and deleting reread the subscription, so a double tap or a stale sheet does nothing.
 */
class SubscriptionSheet extends Component
{
    #[Locked]
    public ?int $subscriptionId = null;

    public string $editSubscriptionName = '';

    public string $editSubscriptionPrice = '';

    public string $cycle = '';

    public string $category = '';

    public string $editNextPaymentDate = '';

    public bool $isTrial = false;

    #[On('show-subscription')]
    public function show(int $id): void
    {
        $subscription = auth()->user()->subscriptions()->findOrFail($id);

        $this->subscriptionId = $subscription->id;
        $this->editSubscriptionName = $subscription->name;
        $this->editSubscriptionPrice = $subscription->price;
        $this->cycle = $subscription->cycle->value;
        $this->category = $subscription->category->value;
        $this->editNextPaymentDate = $subscription->next_payment_date->toDateString();
        $this->isTrial = $subscription->is_trial;
        $this->resetValidation();

        $this->dispatch('open-sheet', name: 'subscription');
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'editSubscriptionName' => ['required', 'string', 'max:255'],
            'editSubscriptionPrice' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'cycle' => ['required', Rule::enum(SubscriptionCycle::class)],
            'category' => ['required', Rule::enum(SubscriptionCategory::class)],
            'editNextPaymentDate' => ['required', 'date_format:Y-m-d'],
            'isTrial' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['editSubscriptionName' => 'name', 'editSubscriptionPrice' => 'price', 'editNextPaymentDate' => 'next payment date'];
    }

    public function save(UpdateSubscription $updateSubscription): void
    {
        $subscription = $this->subscription();

        if ($subscription === null) {
            return;
        }

        $this->editSubscriptionName = Str::trim($this->editSubscriptionName);
        $this->editSubscriptionPrice = Str::replace(',', '.', Str::trim($this->editSubscriptionPrice));

        $this->validate();

        $subscription = $updateSubscription->handle(
            $subscription,
            $this->editSubscriptionName,
            $this->editSubscriptionPrice,
            SubscriptionCycle::from($this->cycle),
            SubscriptionCategory::from($this->category),
            $this->editNextPaymentDate,
            $this->isTrial,
        );

        $this->dispatch('toast', message: $updateSubscription->message($subscription));
        $this->finish($subscription);
    }

    public function deleteSubscription(): void
    {
        $subscription = $this->subscription();

        if ($subscription === null) {
            return;
        }

        $subscription->delete();

        $this->dispatch('toast', message: "{$subscription->name} deleted");
        $this->finish($subscription);
        $this->dispatch('nav-counts-changed');
    }

    /**
     * Forgets the subscription and any unsaved change or error. The view calls this whenever the sheet closes.
     */
    public function clear(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.sheets.subscription-sheet', ['subscription' => $this->subscription()]);
    }

    private function finish(Subscription $subscription): void
    {
        $this->clear();

        $this->dispatch('close-sheet', name: 'subscription');
        $this->dispatch('subscription-changed', id: $subscription->id);
    }

    private function subscription(): ?Subscription
    {
        return $this->subscriptionId === null ? null : auth()->user()->subscriptions()->find($this->subscriptionId);
    }
}
