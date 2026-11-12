@use('App\Support\HomeDashboard')
@use('App\Support\Money')

{{-- Each link's ::before reaches 13px past its 18px line, so the hit area is 44px without moving the layout. --}}
@php($link = 'relative inline-block text-[13px] font-semibold before:absolute before:inset-x-0 before:-inset-y-[13px]')

<div class="flex flex-col gap-4.5 wide:gap-6">
    <div class="flex items-center justify-between">
        <span class="font-display text-[22px] font-semibold tracking-[-0.02em] italic wide:hidden">nook</span>

        <div class="ml-auto flex items-center gap-2">
            <form method="POST" action="{{ route('logout') }}" class="wide:hidden">
                @csrf
                <button type="submit" aria-label="Log out" class="grid size-11 cursor-pointer place-items-center rounded-full border border-white/14 bg-black/25 text-cream">
                    <x-lucide-log-out class="size-[18px] stroke-[1.75]" aria-hidden="true" />
                </button>
            </form>

            <button
                type="button"
                wire:click="toggleNudge"
                aria-label="Morning nudge"
                aria-pressed="{{ $user->morning_nudge ? 'true' : 'false' }}"
                class="grid size-11 cursor-pointer place-items-center rounded-full border border-white/14 bg-black/25 text-cream"
            >
                @if ($user->morning_nudge)
                    <x-lucide-bell class="size-[18px] stroke-[1.75]" aria-hidden="true" />
                @else
                    <x-lucide-bell-off class="size-[18px] stroke-[1.75]" aria-hidden="true" />
                @endif
            </button>
        </div>
    </div>

    <div class="flex flex-col gap-1.5">
        <span class="text-[11px] font-bold tracking-[0.14em] text-cream-muted">{{ Str::upper($now->format('l · j F')) }}</span>
        <h1 class="font-display text-[38px] leading-[1.02] font-semibold tracking-[-0.02em] break-words wide:text-[52px] [&_em]:font-medium [&_em]:text-accent [&_em]:italic">{{ HomeDashboard::greeting($now) }},<br><em>{{ $user->name }}</em></h1>
        <svg viewBox="0 0 120 12" width="120" height="12" aria-hidden="true" stroke-linecap="round" class="-mt-0.5 ml-1 fill-none stroke-accent stroke-2 wide:ml-1.5"><path d="M2 8c12-8 20 6 32 0s20-6 32 0 20 6 32 0 14-5 20-2"></path></svg>
        <span class="font-script text-[21px] text-cream/80">{{ HomeDashboard::todayLine($dashboard->todayLeft, $now) }}</span>
    </div>

    <div class="flex flex-wrap gap-2">
        <x-link-chip :href="route('groceries')" dot="bg-[#efe6d2]">{{ $dashboard->toBuy }} to buy</x-link-chip>
        <x-link-chip :href="route('promos')" dot="bg-peach">{{ $dashboard->dealsEnding }} {{ Str::plural('deal', $dashboard->dealsEnding) }} ending</x-link-chip>
        <x-link-chip :href="route('subscriptions')" dot="bg-[#9cc2c4]">{{ Money::format($dashboard->monthly) }} / mo</x-link-chip>
    </div>

    <div class="grid grid-cols-1 items-start gap-4 wide:grid-cols-2">
        <x-card title="Today">
            <x-slot:aside>{{ $dashboard->todayLeft }} left</x-slot:aside>

            @forelse ($dashboard->todayTasks as $task)
                <div wire:key="today-{{ $task->id }}" @if ($task->done) data-done @endif class="flex items-start gap-2.5 border-t border-hairline py-2">
                    {{-- $tick flips the box and the struck-through title at once and saves the tapped state. The reply moves the row, which can rebuild the checkbox, so a checkbox that had focus takes it back by id. The negative margins set the 18px box where the prototype's sits inside the 44px hit area. --}}
                    <x-checkbox
                        variant="task"
                        size="sm"
                        id="today-check-{{ $task->id }}"
                        :checked="$task->done"
                        :label="'Complete: '.$task->title"
                        :checked-label="'Mark not done: '.$task->title"
                        x-on:click="const focused = document.activeElement === $el; $tick((done, $wire) => $wire.toggleTask({{ $task->id }}, done)).then(() => focused && document.getElementById('today-check-{{ $task->id }}')?.focus())"
                        class="-mt-2.5 -mr-2 -ml-3"
                    />
                    {{-- Opens the edit sheet. The ::before covers the row's padding above and below, so the hit area is at least 44px without moving the layout, and it doesn't reach sideways over the checkbox. --}}
                    <button
                        type="button"
                        id="task-open-{{ $task->id }}"
                        aria-haspopup="dialog"
                        x-on:click="$dispatch('show-task', { id: {{ $task->id }} })"
                        class="relative flex min-w-0 flex-1 cursor-pointer flex-col text-left break-words before:absolute before:inset-x-0 before:-inset-y-2"
                    >
                        <span class="text-[14px] font-semibold in-data-done:line-through in-data-done:opacity-55">{{ $task->title }}</span>
                        <span class="text-[12px] text-cream-muted">{{ $task->homeMeta() }}</span>
                    </button>
                </div>
            @empty
                <p class="py-5 text-center font-script text-[21px] text-cream/78">nothing due today</p>
            @endforelse

            <a href="{{ route('todo') }}" wire:navigate @class([$link, 'mt-1.5'])>All tasks →</a>
        </x-card>

        <x-card title="Groceries">
            <x-slot:aside><a href="{{ route('promos') }}" wire:navigate class="{{ $link }}">Promo board →</a></x-slot:aside>

            @forelse ($dashboard->groceries as $item)
                <div wire:key="grocery-{{ $item->id }}" class="flex min-h-11 items-center gap-2.5 border-t border-hairline">
                    {{-- $tick only ticks here: it flips the box and the struck-through name at once, and a second tap does nothing. The reply removes the row, so a checkbox that had focus hands it to the one now in its place, else the last one, else the card's link. --}}
                    <x-checkbox
                        variant="grocery"
                        size="sm"
                        id="item-check-{{ $item->id }}"
                        :label="'Tick off '.$item->name"
                        x-on:click="const focused = document.activeElement === $el, card = $el.closest('section'), boxes = () => [...card.querySelectorAll('button[aria-pressed]')], place = boxes().indexOf($el); $tick((done, $wire) => $wire.tickItem({{ $item->id }}), { tickOnly: true }).then(() => focused && (boxes()[place] ?? boxes().at(-1) ?? card.querySelector('a')).focus())"
                        class="-mr-2 -ml-3"
                    />
                    <span class="min-w-0 flex-1 text-[14px] font-semibold break-words in-data-done:line-through in-data-done:opacity-55">{{ $item->name }}</span>
                    @if ($item->hasActivePromo())
                        <x-promo-badge :promo="$item->promo" variant="home" />
                    @else
                        <span class="flex-none text-[12px] text-cream-muted">{{ $item->store->value }}</span>
                    @endif
                </div>
            @empty
                <p class="py-5 text-center font-script text-[21px] text-cream/78">list is empty. nice.</p>
            @endforelse
        </x-card>
    </div>

    @if ($dashboard->soonPromos->isNotEmpty())
        <x-card class="pb-5">
            <div class="mb-[18px] flex flex-wrap items-baseline gap-2.5">
                <h2 class="font-display text-[21px] font-semibold">Deals ending soon</h2>
                <span class="font-script text-[19px] text-accent">don't miss these!</span>
            </div>

            {{-- A phone shows 4 and a laptop 5. --}}
            <ul role="list" class="grid grid-cols-2 gap-x-3.5 gap-y-[18px] wide:grid-cols-[repeat(auto-fill,minmax(170px,1fr))]">
                @foreach ($dashboard->soonPromos as $promo)
                    <li wire:key="soon-{{ $promo->id }}" @class(['hidden wide:block' => $loop->iteration === 5])>
                        <x-polaroid :promo="$promo" :linked="$promo->linked" compact />
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    <x-card title="Coming up">
        <x-slot:aside><a href="{{ route('subscriptions') }}" wire:navigate class="{{ $link }}">All subscriptions →</a></x-slot:aside>

        @forelse ($dashboard->comingUp as $subscription)
            <div wire:key="coming-up-{{ $subscription->id }}" class="flex min-h-[46px] items-center gap-2.5 border-t border-hairline">
                <x-subscription-avatar :subscription="$subscription" />
                <div class="flex min-w-0 flex-1 flex-col break-words">
                    <span class="text-[14px] font-semibold">{{ $subscription->name }}</span>
                    <span @class(['text-[12px]', 'text-accent' => $subscription->is_trial, 'text-cream-muted' => ! $subscription->is_trial])>{{ $subscription->comingUpLabel() }}</span>
                </div>
                <span class="flex-none font-mono text-[14px]">{{ $subscription->comingUpAmount() }}</span>
            </div>
        @empty
            <p class="py-5 text-center font-script text-[21px] text-cream/78">nothing in the next two weeks</p>
        @endforelse
    </x-card>
</div>
