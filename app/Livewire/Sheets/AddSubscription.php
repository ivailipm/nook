<?php

namespace App\Livewire\Sheets;

use App\Actions\CreateSubscription;
use App\Enums\SubscriptionCategory;
use App\Enums\SubscriptionCycle;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * The "New subscription" sheet, rendered once in the layout and opened by the Subscriptions FAB, the "+ Add" button and the chooser.
 */
class AddSubscription extends Component
{
    public string $subscriptionName = '';

    public string $subscriptionPrice = '';

    public string $cycle = SubscriptionCycle::Monthly->value;

    public string $category = SubscriptionCategory::Entertainment->value;

    public string $nextPaymentDate = '';

    public bool $isTrial = false;

    public function mount(): void
    {
        $this->nextPaymentDate = today()->addDays(30)->toDateString();
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'subscriptionName' => ['required', 'string', 'max:255'],
            'subscriptionPrice' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'cycle' => ['required', Rule::enum(SubscriptionCycle::class)],
            'category' => ['required', Rule::enum(SubscriptionCategory::class)],
            'nextPaymentDate' => ['required', 'date_format:Y-m-d'],
            'isTrial' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['subscriptionName' => 'name', 'subscriptionPrice' => 'price', 'nextPaymentDate' => 'next payment date'];
    }

    public function save(CreateSubscription $createSubscription): void
    {
        $this->subscriptionName = Str::trim($this->subscriptionName);
        $this->subscriptionPrice = Str::replace(',', '.', Str::trim($this->subscriptionPrice));

        if (blank($this->nextPaymentDate)) {
            $this->nextPaymentDate = today()->addDays(30)->toDateString();
        }

        $this->validate();

        $subscription = $createSubscription->handle(
            auth()->user(),
            $this->subscriptionName,
            $this->subscriptionPrice,
            SubscriptionCycle::from($this->cycle),
            SubscriptionCategory::from($this->category),
            $this->nextPaymentDate,
            $this->isTrial,
        );

        session()->flash('toast', $createSubscription->message($subscription));

        $this->redirectRoute('subscriptions');
    }

    /**
     * Back to an empty monthly form with the date 30 days ahead, without errors. The view calls this whenever the sheet opens or closes.
     */
    public function resetForm(): void
    {
        $this->reset();
        $this->resetValidation();
        $this->mount();
    }

    public function render(): View
    {
        return view('livewire.sheets.add-subscription');
    }
}
