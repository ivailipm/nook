@use('App\Support\PolaroidStyle')

{{-- A close (the close button, Escape, the scrim or an action) forgets the promo. A dialog's close event doesn't bubble, hence the capture. --}}
<div x-on:close.capture="$wire.clear()">
    <x-sheet name="promo" title="The deal">
        @if ($promo)
            <div class="flex flex-col gap-3">
                <div class="-rotate-1 rounded-polaroid bg-paper px-2.5 pt-2.5 pb-2 shadow-[0_12px_26px_rgb(0_0_0/0.45)]">
                    <div @class(['relative flex aspect-[4/3] flex-col gap-1.5 overflow-hidden p-4 wrap-anywhere', PolaroidStyle::photo($promo)])>
                        @if ($promo->photoUrl() !== null)
                            <img src="{{ $promo->photoUrl() }}" alt="Photo of the {{ $promo->store->value }} deal" class="absolute inset-0 size-full bg-black object-contain">
                        @endif
                        <span class="relative text-[11px] font-bold tracking-[0.14em] uppercase">{{ $promo->store->value }}</span>
                        @if ($promo->product !== null)
                            <span class="relative font-display text-[26px] leading-[1.1] font-semibold">{{ $promo->product }}</span>
                        @endif
                        <span class="relative font-display text-[40px] leading-none font-bold">{{ $promo->deal }}</span>
                    </div>
                    <div @class(['pt-1 font-script text-[20px] font-bold', PolaroidStyle::untilColour($promo)])>{{ $promo->untilLabel() }}</div>
                </div>

                <div class="flex flex-col gap-0.5 text-[13px] wrap-anywhere text-cream/78">
                    <span>{{ $promo->validityLine() }}</span>
                    @if ($promo->note !== null)
                        <span>{{ $promo->note }}</span>
                    @endif
                    <span class="font-semibold text-cream">
                        @if ($item === null)
                            Not on your list yet
                        @else
                            On your list: {{ $item->name }}{{ $item->qty === null ? '' : " ({$item->qty})" }}
                        @endif
                    </span>
                </div>

                <div class="flex flex-wrap gap-2">
                    @if ($item !== null)
                        <button type="button" wire:click="gotIt" class="min-h-[46px] flex-[1_1_140px] cursor-pointer rounded-button bg-peach text-[14px] font-bold text-on-peach">Got it · tick it off</button>
                        <button type="button" wire:click="unlink" class="min-h-[46px] flex-[1_1_100px] cursor-pointer rounded-button border border-cream/40 text-[14px] font-bold text-cream">Unlink</button>
                    @elseif ($promo->daysLeft() >= 0)
                        <button type="button" wire:click="addToList" class="min-h-[46px] flex-[1_1_140px] cursor-pointer rounded-button bg-peach text-[14px] font-bold text-on-peach">Add to my list</button>
                    @endif
                    <button type="button" wire:click="deletePromo" class="min-h-[46px] flex-[1_1_100px] cursor-pointer rounded-button border border-destructive-border text-[14px] font-bold text-destructive">Delete promo</button>
                </div>
            </div>
        @endif
    </x-sheet>
</div>
