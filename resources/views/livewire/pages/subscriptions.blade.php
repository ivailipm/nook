@use('App\Support\Money')

<div class="flex flex-col gap-4.5 wide:gap-6">
    <x-page-header eyebrow="MONEY" subtitle="where the money quietly goes">Subscrip<em>tions</em></x-page-header>

    <div class="grid grid-cols-1 items-start gap-4.5 wide:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
        <x-receipt :totals="$totals" :subscriptions="$live" />

        <div class="flex min-w-0 flex-col gap-4">
            @if ($trial)
                <x-card variant="money" wire:key="trial-{{ $trial->id }}" class="relative px-4 pt-[18px] pb-4">
                    <span class="absolute -top-2.5 right-[18px] rotate-4 rounded-[3px] border-[1.5px] border-ends-soon bg-paper px-[9px] py-[3px] text-[10px] font-extrabold tracking-[0.14em] text-ends-soon">TRIAL</span>
                    <h2 class="mb-1 font-display text-[20px] leading-[1.2] font-semibold break-words">{{ $trial->trialHeadline() }}</h2>
                    <p class="text-[13px] break-words text-cream/78">{{ $trial->trialDetail() }}</p>
                    {{-- Each ::before reaches past its 42px button, so the hit area is 44px; its insets start inside the 1px border of the outlined two. --}}
                    <div class="mt-3.5 flex flex-wrap gap-2">
                        @if ($trial->reminderLabel() !== null)
                            <button
                                type="button"
                                wire:click="toggleReminder({{ $trial->id }})"
                                aria-pressed="{{ $trial->trial_reminder ? 'true' : 'false' }}"
                                @class([
                                    'relative min-h-[42px] flex-[1_1_140px] cursor-pointer rounded-[10px] text-[13px] font-bold before:absolute before:inset-x-0 before:-inset-y-px',
                                    'bg-peach/18 text-peach' => $trial->trial_reminder,
                                    'bg-peach text-on-peach' => ! $trial->trial_reminder,
                                ])
                            >{{ $trial->reminderLabel() }}</button>
                        @endif
                        <button type="button" wire:click="keepTrial({{ $trial->id }})" class="relative min-h-[42px] flex-[1_1_100px] cursor-pointer rounded-[10px] border border-cream/40 text-[13px] font-bold text-cream before:absolute before:inset-x-0 before:-inset-y-0.5">Keep it</button>
                        <button type="button" wire:click="cancelTrial({{ $trial->id }})" class="relative min-h-[42px] flex-[1_1_100px] cursor-pointer rounded-[10px] border border-cream/40 text-[13px] font-bold text-cream before:absolute before:inset-x-0 before:-inset-y-0.5">Cancel trial</button>
                    </div>
                </x-card>
            @endif

            <x-card variant="money" title="By category" class="p-4">
                <x-slot:aside>per month</x-slot:aside>

                @if ($totals->categories !== [])
                    <div
                        role="img"
                        aria-label="Monthly spend by category: {{ collect($totals->categories)->map(fn (array $entry) => $entry['category']->value.' '.Money::format($entry['amount']))->implode(', ') }}"
                        class="mb-3 flex h-3 gap-0.5 overflow-hidden rounded-[4px]"
                    >
                        @foreach ($totals->categories as $entry)
                            <span title="{{ $entry['category']->value }}: {{ Money::format($entry['amount']) }} a month" style="flex: {{ max(1, (int) round($entry['amount'] * 100)) }} 1 0" class="h-full {{ $entry['category']->swatchClass() }}"></span>
                        @endforeach
                    </div>

                    @foreach ($totals->categories as $entry)
                        <div class="flex min-h-7 items-center gap-2.5 text-[13px]">
                            <span aria-hidden="true" class="size-[9px] rounded-[2px] {{ $entry['category']->swatchClass() }}"></span>
                            <span class="flex-1">{{ $entry['category']->value }}</span>
                            <span class="text-[12px] text-cream-muted">{{ $entry['share'] }}%</span>
                            <span class="min-w-16 text-right font-mono">{{ Money::format($entry['amount']) }}</span>
                        </div>
                    @endforeach
                @else
                    <p class="py-5 text-center font-script text-[21px] text-cream/78">nothing paying yet</p>
                @endif

                @if ($totals->savingsLine !== null)
                    <p class="mt-2.5 border-t border-white/10 pt-2.5 font-script text-[19px] break-words text-peach">{{ $totals->savingsLine }}</p>
                @endif
            </x-card>

            <x-card variant="money" class="px-4 pt-2 pb-3">
                <div class="flex items-center justify-between py-1.5">
                    <h2 class="font-display text-[20px] font-semibold">All subscriptions</h2>
                    {{-- The ::before reaches 5px past the 34px button, so the hit area is 44px. --}}
                    <button
                        type="button"
                        aria-label="Add a subscription"
                        aria-haspopup="dialog"
                        x-on:click="$dispatch('open-sheet', { name: 'add-subscription' })"
                        class="relative min-h-[34px] flex-none cursor-pointer rounded-full bg-peach px-3 text-[12px] font-bold text-on-peach before:absolute before:inset-x-0 before:-inset-y-[5px]"
                    >+ Add</button>
                </div>

                @forelse ($rows as $subscription)
                    <div wire:key="subscription-{{ $subscription->id }}" class="flex min-h-[54px] items-center gap-2.5 border-t border-hairline py-1">
                        <x-subscription-avatar :subscription="$subscription" />
                        <div class="flex min-w-0 flex-1 flex-col break-words">
                            <span @class(['text-[14px] font-semibold', 'line-through opacity-60' => $subscription->isCancelled()])>{{ $subscription->name }}</span>
                            <span class="text-[12px] text-cream-muted">{{ $subscription->listMeta() }}</span>
                        </div>
                        <span @class(['flex-none text-right font-mono text-[13px]', 'opacity-60' => $subscription->isCancelled()])>{{ $subscription->priceLabel() }}</span>
                        {{-- Cancelling or resuming moves the row, which can rebuild the button, so a button that had focus takes it back by id. The ::before reaches 7px past the 32px button, so the hit area is 44px; its insets start inside the 1px border. --}}
                        <button
                            type="button"
                            id="subscription-action-{{ $subscription->id }}"
                            aria-label="{{ $subscription->isCancelled() ? 'Resume' : 'Cancel' }} {{ $subscription->name }}"
                            x-on:click="const focused = document.activeElement === $el; $wire.{{ $subscription->isCancelled() ? 'resume' : 'cancel' }}({{ $subscription->id }}).then(() => focused && document.getElementById('subscription-action-{{ $subscription->id }}')?.focus())"
                            class="relative min-h-8 flex-none cursor-pointer rounded-[8px] border border-cream/35 px-2.5 text-[12px] font-semibold text-cream before:absolute before:inset-x-0 before:-inset-y-[7px]"
                        >{{ $subscription->isCancelled() ? 'Resume' : 'Cancel' }}</button>
                    </div>
                @empty
                    <p class="py-5 text-center font-script text-[21px] text-cream/78">nothing here yet. add your first one</p>
                @endforelse
            </x-card>
        </div>
    </div>
</div>
