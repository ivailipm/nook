@use('App\Enums\Section')

{{-- Each tile closes the chooser and opens its sheet in place. --}}
<x-sheet name="add-something" title="Add something">
    <div class="grid grid-cols-2 gap-2.5">
        @foreach ([
            [Section::Groceries, 'Grocery item', 'add to the list', 'bg-[rgb(47_107_72/0.35)]', 'add-item'],
            [Section::Promos, 'Promo', 'photo or screenshot', 'bg-gold/20', 'add-promo'],
            [Section::Todo, 'Task', 'with a due date', 'bg-[rgb(107_83_38/0.45)]', 'add-task'],
            [Section::Subscriptions, 'Subscription', 'or a free trial', 'bg-[rgb(17_57_60/0.6)]', 'add-subscription'],
        ] as [$section, $label, $hint, $background, $sheet])
            <button
                type="button"
                aria-haspopup="dialog"
                x-on:click="$root.close(); $dispatch('open-sheet', { name: @js($sheet) })"
                class="flex min-h-[104px] cursor-pointer flex-col items-start gap-2 rounded-[16px] border border-white/10 p-4 text-left text-cream {{ $background }}"
            >
                <x-dynamic-component :component="'lucide-'.$section->icon()" class="size-[22px] stroke-[1.75]" aria-hidden="true" />
                <span class="text-[15px] font-bold">{{ $label }}</span>
                <span class="text-[12px] text-cream/75">{{ $hint }}</span>
            </button>
        @endforeach
    </div>
</x-sheet>
